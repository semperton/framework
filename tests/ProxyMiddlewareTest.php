<?php

declare(strict_types=1);

namespace Semperton\Framework\Tests;

use InvalidArgumentException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Semperton\Framework\Middleware\ProxyMiddleware;

final class ProxyMiddlewareTest extends TestCase
{
    public function testUntrustedPeer(): void
    {
        $request = $this->process(['10.0.0.1'], '192.0.2.1', 'https://localhost/', [
            'X-Forwarded-For' => '198.51.100.1',
            'X-Forwarded-Proto' => 'https',
        ]);

        self::assertSame('192.0.2.1', $request->getAttribute(ProxyMiddleware::CLIENT_IP));
        // e.g. set from the forged header by nyholm/psr7-server
        self::assertSame('http', $request->getUri()->getScheme());
    }

    public function testHttpsServerParam(): void
    {
        $request = $this->process([], '192.0.2.1', 'http://localhost/', [], ['HTTPS' => 'on']);

        self::assertSame('https', $request->getUri()->getScheme());
    }

    public function testTrustedProxies(): void
    {
        $request = $this->process(['10.0.0.0/8'], '10.0.0.1', 'http://localhost/', [
            'X-Forwarded-For' => '198.51.100.1, 203.0.113.1, 10.0.0.2',
            'X-Forwarded-Proto' => 'https',
        ]);

        // the last address not added by a trusted proxy, the first one can be forged by the client
        self::assertSame('203.0.113.1', $request->getAttribute(ProxyMiddleware::CLIENT_IP));
        self::assertSame('https', $request->getUri()->getScheme());
    }

    public function testInvalidForwardedAddress(): void
    {
        $request = $this->process(['10.0.0.1'], '10.0.0.1', 'http://localhost/', [
            'X-Forwarded-For' => '203.0.113.1, unknown',
        ]);

        self::assertSame('10.0.0.1', $request->getAttribute(ProxyMiddleware::CLIENT_IP));
    }

    #[TestWith(['192.168.0.0/23', '192.168.1.255', true])]
    #[TestWith(['192.168.0.0/23', '192.168.2.1', false])]
    #[TestWith(['0.0.0.0/0', '203.0.113.1', true])]
    #[TestWith(['fd00::/8', 'fd12::1', true])]
    #[TestWith(['fd00::/8', 'fe80::1', false])]
    #[TestWith(['::1', '::1', true])]
    #[TestWith(['::ffff:0:0/96', '10.0.0.1', false])]
    public function testRanges(string $proxy, string $remoteAddress, bool $trusted): void
    {
        $request = $this->process([$proxy], $remoteAddress, 'http://localhost/', [
            'X-Forwarded-For' => '198.51.100.1',
        ]);

        $clientIp = $trusted ? '198.51.100.1' : $remoteAddress;
        self::assertSame($clientIp, $request->getAttribute(ProxyMiddleware::CLIENT_IP));
    }

    #[TestWith(['localhost'])]
    #[TestWith(['10.0.0.0/33'])]
    #[TestWith(['::/129'])]
    #[TestWith(['10.0.0.0/a'])]
    public function testInvalidProxy(string $proxy): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid trusted proxy < $proxy >");

        new ProxyMiddleware([$proxy]);
    }

    /**
     * @param list<string> $trustedProxies
     * @param array<string, string> $headers
     * @param array<string, string> $serverParams
     */
    private function process(
        array $trustedProxies,
        string $remoteAddress,
        string $uri,
        array $headers,
        array $serverParams = [],
    ): ServerRequestInterface {
        $request = (new Psr17Factory())->createServerRequest('GET', $uri, ['REMOTE_ADDR' => $remoteAddress] + $serverParams);
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        $handler = new class implements RequestHandlerInterface {
            public ?ServerRequestInterface $request = null;

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->request = $request;
                return new Response();
            }
        };

        (new ProxyMiddleware($trustedProxies))->process($request, $handler);

        self::assertNotNull($handler->request);

        return $handler->request;
    }
}
