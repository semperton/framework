<?php

declare(strict_types=1);

namespace Semperton\Framework\Tests\Security;

use Eris\Generator;
use Eris\Generators;
use Eris\TestTrait;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Uri;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Semperton\Framework\ApplicationBuilder;
use Semperton\Framework\Exception\HttpBadRequestException;
use Semperton\Framework\Middleware\ProxyMiddleware;
use Semperton\Framework\Middleware\RequestBodyMiddleware;
use Semperton\Framework\Middleware\TrailingSlashMiddleware;
use Semperton\Framework\RouteResult;
use Semperton\Framework\Tests\Fixtures\RawPathUri;

use function filter_var;
use function implode;
use function in_array;
use function inet_ntop;
use function inet_pton;
use function is_array;
use function str_ends_with;
use function str_starts_with;
use function strlen;

use const FILTER_VALIDATE_IP;

/**
 * Invariants for untrusted input, checked with generated requests (eris)
 * A failure prints the seed, rerun it with ERIS_SEED=<seed>
 */
final class PropertyTest extends TestCase
{
    use TestTrait;

    /** characters that confuse path handling */
    private const PATH = ['/', '/', '/', '\\', '.', '..', '%', '%2F', '%5C', '%00', '?', 'a', ' ', "\0", "\t", ':', '@', '~', "\xc3\xa9", "\xff"];

    private const REMOTE_ADDRESSES = ['10.0.0.1', '::ffff:10.0.0.2', '203.0.113.5', '2001:DB8::1', 'unknown', ''];
    private const FORWARDED_ADDRESSES = ['198.51.100.1', '10.0.0.3', '::ffff:198.51.100.2', '2001:db8::3', 'unknown', '1.2.3.4:80', '[2001:db8::2]', ' ', ''];
    private const PROTOS = ['http', 'https', 'HTTPS', 'ftp', ''];

    private const CONTENT_TYPES = ['application/json', 'application/merge-patch+json', 'APPLICATION/JSON; charset=utf-8', 'application/x-www-form-urlencoded', 'text/plain', ''];
    private const BODY = ['{', '}', '[', ']', '[[[[[[[[', '"', ':', ',', '1', '1e999', 'a', '=', '&', '%', '%zz', ' ', "\0", "\xff", 'true', 'null'];

    private const PARAM = ['a', "\xc3\xa9", '/', '%', '%2F', '?', '#', ' ', '+', '.', '..', ':', '@', "\0", "\xff", '~'];

    public function testTrailingSlashRedirectsStayOnTheHost(): void
    {
        $factory = new Psr17Factory();

        // leading and trailing slashes are where it gets dangerous
        $this->forAll(
            Generators::elements('', '/', '//', '\\', '/\\'),
            self::text(self::PATH),
            Generators::elements('', '/', '//'),
        )->then(function (string $prefix, string $path, string $suffix) use ($factory): void {
            $request = new ServerRequest('GET', new RawPathUri('http://localhost', '/' . $prefix . $path . $suffix));

            $redirect = (new TrailingSlashMiddleware($factory))->process($request, self::handler());
            if ($redirect->getStatusCode() !== 200) {
                // '//host' and '/\host' are read as another host by browsers
                self::assertMatchesRegularExpression('~^/(?![/\\\\])~', $redirect->getHeaderLine('Location'));
            }

            // paths with a trailing slash are rewritten without it, others are passed on
            $handler = self::handler();
            (new TrailingSlashMiddleware($factory, redirect: false))->process($request, $handler);
            $original = $request->getUri()->getPath();
            $rewritten = $handler->request?->getUri()->getPath() ?? '';
            if ($original === '/' || !str_ends_with($original, '/')) {
                self::assertSame($original, $rewritten);
            } else {
                self::assertTrue($rewritten === '/' || (!str_ends_with($rewritten, '/') && !str_starts_with($rewritten, '//')), $rewritten);
            }
        });
    }

    public function testUntrustedPeersCannotChangeTheClient(): void
    {
        $this->forAll(
            Generators::elements(self::REMOTE_ADDRESSES),
            self::list(self::FORWARDED_ADDRESSES),
            self::list(self::PROTOS),
        )->then(function (string $remoteAddress, string $forwardedFor, string $forwardedProto): void {
            $request = (new ServerRequest('GET', 'http://localhost/', [], null, '1.1', ['REMOTE_ADDR' => $remoteAddress]))
                ->withHeader('X-Forwarded-For', $forwardedFor)
                ->withHeader('X-Forwarded-Proto', $forwardedProto);
            $handler = self::handler();

            (new ProxyMiddleware(['10.0.0.0/8']))->process($request, $handler);

            /** @var mixed $clientIp */
            $clientIp = $handler->request?->getAttribute(ProxyMiddleware::CLIENT_IP);
            $peer = self::canonical($remoteAddress);

            if ($peer === null || !str_starts_with($peer, '10.')) {
                // the headers of untrusted peers are ignored
                self::assertSame($peer ?? $remoteAddress, $clientIp);
                self::assertSame('http', $handler->request?->getUri()->getScheme());
                return;
            }

            // always one of the given addresses, in canonical form
            $candidates = [$peer];
            foreach (explode(',', $forwardedFor) as $address) {
                $candidates[] = self::canonical(trim($address));
            }
            self::assertContains($clientIp, $candidates);
        });
    }

    public function testRequestBodiesOnlyFailAsBadRequests(): void
    {
        $this->forAll(
            Generators::elements(self::CONTENT_TYPES),
            Generators::elements('POST', 'PUT', 'PATCH'),
            self::text(self::BODY),
        )->then(function (string $contentType, string $method, string $body): void {
            $request = (new Psr17Factory())->createServerRequest($method, 'http://localhost/')
                ->withHeader('Content-Type', $contentType)
                ->withBody((new Psr17Factory())->createStream($body));
            $handler = self::handler();

            try {
                (new RequestBodyMiddleware())->process($request, $handler);
            } catch (HttpBadRequestException) {
                return;
            }

            $parsedBody = $handler->request?->getParsedBody();
            self::assertTrue($parsedBody === null || is_array($parsedBody));
            // the raw body stays readable
            self::assertSame($body, (string) $handler->request?->getBody());
        });
    }

    public function testGeneratedPathsRouteToTheirParams(): void
    {
        $factory = new Psr17Factory();
        $builder = (new ApplicationBuilder($factory, basePath: '/app'))
            ->get('/blog/:slug', self::paramHandler('slug'), name: 'post')
            ->get('/files/*path', self::paramHandler('path'), name: 'file');
        $urls = $builder->getUrlGenerator();
        $app = $builder->build();

        $this->forAll(
            Generators::suchThat(static fn(string $value): bool => $value !== '', self::text(self::PARAM)),
        )->then(function (string $value) use ($urls, $app): void {
            $response = $app->handle(new ServerRequest('GET', $urls->path('post', ['slug' => $value])));
            self::assertSame($value, (string) $response->getBody());

            // wildcards keep their slashes, empty segments can't be routed
            if (!in_array('', explode('/', $value), true)) {
                $response = $app->handle(new ServerRequest('GET', $urls->path('file', ['path' => $value])));
                self::assertSame($value, (string) $response->getBody());
            }
        });
    }

    public function testNoPathCausesAServerError(): void
    {
        $factory = new Psr17Factory();
        $app = (new ApplicationBuilder($factory, basePath: '/app'))
            ->addErrorMiddleware()
            ->add(new TrailingSlashMiddleware($factory))
            ->get('/blog/:slug', self::paramHandler('slug'))
            ->get('/files/*path', self::paramHandler('path'))
            ->post('/blog', self::handler())
            ->build();

        $this->forAll(
            Generators::elements('GET', 'HEAD', 'POST', 'OPTIONS', 'DELETE'),
            self::text(['/app', '/blog', '/files', ...self::PATH]),
        )->then(function (string $method, string $path) use ($app): void {
            $request = new ServerRequest($method, (new Uri('http://localhost'))->withPath('/' . $path));

            self::assertLessThan(500, $app->handle($request)->getStatusCode(), $path);
        });
    }

    /**
     * @param non-empty-list<string> $alphabet
     * @return Generator<string>
     */
    private static function text(array $alphabet): Generator
    {
        return Generators::map(static fn(array $parts): string => implode('', $parts), Generators::seq(Generators::elements($alphabet)));
    }

    /**
     * Comma separated values of a header
     *
     * @param non-empty-list<string> $values
     * @return Generator<string>
     */
    private static function list(array $values): Generator
    {
        return Generators::map(static fn(array $parts): string => implode(', ', $parts), Generators::seq(Generators::elements($values)));
    }

    private static function canonical(string $ip): ?string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        $address = (string) inet_pton($ip);
        // ipv4-mapped ipv6
        if (strlen($address) === 16 && str_starts_with($address, "\0\0\0\0\0\0\0\0\0\0\xff\xff")) {
            $address = substr($address, 12);
        }

        return (string) inet_ntop($address);
    }

    /**
     * @return RequestHandlerInterface&object{request: ?ServerRequestInterface}
     */
    private static function handler(): RequestHandlerInterface
    {
        return new class implements RequestHandlerInterface {
            public ?ServerRequestInterface $request = null;

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->request = $request;
                return new Response();
            }
        };
    }

    private static function paramHandler(string $name): RequestHandlerInterface
    {
        return new class ($name) implements RequestHandlerInterface {
            public function __construct(
                private readonly string $name,
            ) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(body: RouteResult::fromRequest($request)?->param($this->name) ?? '');
            }
        };
    }
}
