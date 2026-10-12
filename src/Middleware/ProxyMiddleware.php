<?php

declare(strict_types=1);

namespace Semperton\Framework\Middleware;

use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function array_map;
use function count;
use function explode;
use function filter_var;
use function in_array;
use function inet_ntop;
use function inet_pton;
use function intdiv;
use function ltrim;
use function ord;
use function preg_match;
use function str_starts_with;
use function strlen;
use function strtolower;
use function substr;
use function trim;

use const FILTER_VALIDATE_INT;
use const FILTER_VALIDATE_IP;

/**
 * Adds the client ip as request attribute and sets the scheme of the uri
 * X-Forwarded-For and X-Forwarded-Proto are only used if the request comes from a trusted proxy
 * The port of the uri is taken from the Host header of trusted proxies, some request factories set the one of the proxy connection
 * Forwarded (rfc 7239), X-Forwarded-Host and -Port are not used, proxies are expected to keep the Host header
 */
final class ProxyMiddleware implements MiddlewareInterface
{
    public const CLIENT_IP = 'client_ip';

    /** prefix of ipv4-mapped ipv6 addresses, ::ffff:0:0/96 */
    private const IPV4_MAPPED = "\0\0\0\0\0\0\0\0\0\0\xff\xff";

    /** @var list<array{string, int}> binary address and prefix length */
    private readonly array $proxies;

    /**
     * @param list<string> $trustedProxies ips or ranges, e.g. '10.0.0.1', '10.0.0.0/8', 'fd00::/8'
     */
    public function __construct(array $trustedProxies)
    {
        $proxies = [];

        foreach ($trustedProxies as $proxy) {
            $parts = explode('/', $proxy, 2);
            $address = self::address($parts[0]);
            $maxPrefix = $address === null ? 0 : strlen($address) * 8;
            $prefix = isset($parts[1])
                ? filter_var($parts[1], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => $maxPrefix]])
                : $maxPrefix;

            if ($address === null || $prefix === false) {
                throw new InvalidArgumentException("Invalid trusted proxy < $proxy >");
            }

            $proxies[] = [$address, $prefix];
        }

        $this->proxies = $proxies;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $server = $request->getServerParams();
        $remoteAddress = (string) ($server['REMOTE_ADDR'] ?? '');
        $address = self::peer($remoteAddress);
        $https = !in_array($server['HTTPS'] ?? '', ['', 'off'], true);
        $uri = $request->getUri();
        $port = $uri->getPort();

        if ($address !== null && $this->trusted($address)) {
            $forwardedFor = $this->list($request, 'X-Forwarded-For');
            // the index of the client in X-Forwarded-For, the peer comes after the list
            $hop = count($forwardedFor);

            // the client is the last address not added by a trusted proxy, invalid entries end the chain
            while ($hop > 0) {
                $forwarded = self::peer($forwardedFor[$hop - 1]);
                if ($forwarded === null) {
                    break;
                }

                $address = $forwarded;
                $hop--;

                if (!$this->trusted($forwarded)) {
                    break;
                }
            }

            $forwardedProto = $this->list($request, 'X-Forwarded-Proto');
            if ($forwardedProto !== []) {
                // proxies appending to both headers keep them aligned, otherwise the last entry
                // is the one of the nearest trusted proxy, earlier ones can be forged by the client
                $proto = count($forwardedProto) === count($forwardedFor) && isset($forwardedProto[$hop])
                    ? $forwardedProto[$hop]
                    : $forwardedProto[count($forwardedProto) - 1];
                $https = strtolower($proto) === 'https';
            }

            // some request factories take the port from SERVER_PORT, e.g. 8080 of the proxy connection
            // more than one Host header is invalid (rfc 9112 3.2)
            $host = $request->getHeader('Host');
            $hostPort = count($host) === 1 ? self::port($host[0]) : false;
            if ($hostPort !== false) {
                $port = $hostPort;
            }
        }

        // also replaces a scheme from a forged header, e.g. set by nyholm/psr7-server
        $scheme = $https ? 'https' : 'http';
        if ($uri->getScheme() !== $scheme || $uri->getPort() !== $port) {
            $request = $request->withUri($uri->withScheme($scheme)->withPort($port), true);
        }

        // in canonical form, e.g. '10.0.0.1' for '::ffff:10.0.0.1'
        $clientIp = $address === null ? $remoteAddress : (string) inet_ntop($address);

        return $handler->handle($request->withAttribute(self::CLIENT_IP, $clientIp));
    }

    /**
     * @param string $address binary form
     */
    private function trusted(string $address): bool
    {
        foreach ($this->proxies as [$proxy, $prefix]) {
            // ipv4 and ipv6 addresses never match
            if (strlen($proxy) !== strlen($address)) {
                continue;
            }

            // compares the whole bytes of the prefix, then the remaining bits
            $bytes = intdiv($prefix, 8);
            if (substr($address, 0, $bytes) !== substr($proxy, 0, $bytes)) {
                continue;
            }

            $bits = $prefix % 8;
            $mask = (0xff << (8 - $bits)) & 0xff;
            if ($bits === 0 || (ord($address[$bytes]) & $mask) === (ord($proxy[$bytes]) & $mask)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function list(ServerRequestInterface $request, string $header): array
    {
        $line = $request->getHeaderLine($header);

        return $line === '' ? [] : array_map(trim(...), explode(',', $line));
    }

    /**
     * Port of a Host header (rfc 9110 7.2, rfc 3986 3.2.2), null for the default port, false if it is invalid
     * e.g. 8080 for 'example.com:8080' or '[::1]:8080', null for 'example.com'
     */
    private static function port(string $host): int|false|null
    {
        // ip-literal or reg-name (unreserved, pct-encoded, sub-delims), only ip-literals contain colons
        if (preg_match('~^(?:\[[^\[\]\s]+\]|[A-Za-z0-9\-._\~%!$&\'()*+,;=]+)(?::(\d*))?$~D', $host, $matches) !== 1) {
            return false;
        }

        if (($matches[1] ?? '') === '') {
            return null;
        }

        // port = *DIGIT allows leading zeros, withPort() throws for invalid ports and the header is untrusted
        return filter_var(ltrim($matches[1], '0'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
    }

    /**
     * Binary form of a valid ip, header values are untrusted, so they are validated first
     */
    private static function address(string $ip): ?string
    {
        return filter_var($ip, FILTER_VALIDATE_IP) === false ? null : (string) inet_pton($ip);
    }

    /**
     * Binary form of a received address, dual-stack sockets report ipv4 peers as ipv4-mapped ipv6, e.g. '::ffff:10.0.0.1'
     */
    private static function peer(string $ip): ?string
    {
        $address = self::address($ip);

        return $address !== null && strlen($address) === 16 && str_starts_with($address, self::IPV4_MAPPED)
            ? substr($address, 12)
            : $address;
    }
}
