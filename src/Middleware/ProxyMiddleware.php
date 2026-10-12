<?php

declare(strict_types=1);

namespace Semperton\Framework\Middleware;

use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function array_map;
use function array_reverse;
use function explode;
use function filter_var;
use function in_array;
use function inet_pton;
use function intdiv;
use function ord;
use function strlen;
use function strtolower;
use function substr;
use function trim;

use const FILTER_VALIDATE_INT;
use const FILTER_VALIDATE_IP;

/**
 * Adds the client ip as request attribute and sets the scheme of the uri
 * X-Forwarded-For and X-Forwarded-Proto are only used if the request comes from a trusted proxy
 */
final class ProxyMiddleware implements MiddlewareInterface
{
    public const CLIENT_IP = 'client_ip';

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
        $ip = (string) ($server['REMOTE_ADDR'] ?? '');
        $https = !in_array($server['HTTPS'] ?? '', ['', 'off'], true);

        if ($this->trusted($ip)) {
            // the client is the last address not added by a trusted proxy, invalid entries end the chain
            foreach (array_reverse($this->list($request, 'X-Forwarded-For')) as $forwarded) {
                if (self::address($forwarded) === null) {
                    break;
                }

                $ip = $forwarded;

                if (!$this->trusted($forwarded)) {
                    break;
                }
            }

            $proto = $this->list($request, 'X-Forwarded-Proto');
            $https = $proto === [] ? $https : strtolower($proto[0]) === 'https';
        }

        // also replaces a scheme from a forged header, e.g. set by nyholm/psr7-server
        $scheme = $https ? 'https' : 'http';
        $uri = $request->getUri();
        if ($uri->getScheme() !== $scheme) {
            $request = $request->withUri($uri->withScheme($scheme), true);
        }

        return $handler->handle($request->withAttribute(self::CLIENT_IP, $ip));
    }

    private function trusted(string $ip): bool
    {
        $address = self::address($ip);
        if ($address === null) {
            return false;
        }

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
     * Binary form of a valid ip, header values are untrusted, so they are validated first
     */
    private static function address(string $ip): ?string
    {
        return filter_var($ip, FILTER_VALIDATE_IP) === false ? null : (string) inet_pton($ip);
    }
}
