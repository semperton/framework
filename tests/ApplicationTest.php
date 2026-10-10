<?php

declare(strict_types=1);

namespace Semperton\Framework\Tests;

use InvalidArgumentException;
use LogicException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use OutOfBoundsException;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\AbstractLogger;
use RuntimeException;
use Semperton\Framework\Application;
use Semperton\Framework\ApplicationBuilder;
use Semperton\Framework\Exception\HttpException;
use Semperton\Framework\Exception\HttpNotFoundException;
use Semperton\Framework\Handler\EndpointHandler;
use Semperton\Framework\Handler\ErrorHandler;
use Semperton\Framework\Interfaces\RouteCollectorInterface;
use Semperton\Framework\Middleware\RequestBodyMiddleware;
use Semperton\Framework\RouteResult;
use Semperton\Framework\Tests\Misc\TestHandler;
use Semperton\Framework\Tests\Misc\TestMiddleware;
use stdClass;
use UnexpectedValueException;

final class ApplicationTest extends TestCase
{
    private Psr17Factory $factory;

    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
    }

    private function body(ResponseInterface $response): string
    {
        return (string) $response->getBody();
    }

    private function builder(?ContainerInterface $container = null): ApplicationBuilder
    {
        return new ApplicationBuilder($this->factory, $container);
    }

    /**
     * @param array<string, mixed> $entries
     */
    private function container(array $entries): ContainerInterface
    {
        return new class ($entries) implements ContainerInterface {
            public function __construct(private array $entries) {}

            public function get(string $id): mixed
            {
                return $this->entries[$id] ?? throw new class ($id) extends RuntimeException implements NotFoundExceptionInterface {};
            }

            public function has(string $id): bool
            {
                return isset($this->entries[$id]);
            }
        };
    }

    public function testRouteAndParams(): void
    {
        $app = $this->builder()
            ->get('/', new TestHandler($this->factory, 'index'))
            ->get('/hello/:name', new TestHandler($this->factory))
            ->build();

        $this->assertSame('index', $this->body($app->handle(new ServerRequest('GET', '/'))));
        $this->assertSame('world', $this->body($app->handle(new ServerRequest('GET', '/hello/world'))));
    }

    public function testMiddlewareOrder(): void
    {
        $handler = new TestHandler($this->factory);

        $app = $this->builder()
            ->add(new TestMiddleware('global'))
            ->addRoutingMiddleware()
            ->group('/api', function (RouteCollectorInterface $api) use ($handler) {
                $api->get('/route', $handler, [new TestMiddleware('route')]);
                $api->group('/v1', function (RouteCollectorInterface $v1) use ($handler) {
                    $v1->get('/route', $handler);
                }, [new TestMiddleware('nested')]);
                $api->get('/after', $handler);
            }, [new TestMiddleware('group')])
            ->get('/outside', $handler)
            ->build();

        $this->assertSame('handler > route > group > global', $this->body($app->handle(new ServerRequest('GET', '/api/route'))));
        $this->assertSame('handler > nested > group > global', $this->body($app->handle(new ServerRequest('GET', '/api/v1/route'))));
        $this->assertSame('handler > group > global', $this->body($app->handle(new ServerRequest('GET', '/api/after'))));
        $this->assertSame('handler > global', $this->body($app->handle(new ServerRequest('GET', '/outside'))));
    }

    public function testNamedRoutes(): void
    {
        $handler = new TestHandler($this->factory);
        $builder = $this->builder();
        $urls = $builder->getUrlGenerator(); // available before the routes exist

        $builder->group('/blog', function (RouteCollectorInterface $blog) use ($handler) {
            $blog->get('/:id:d', $handler, name: 'article');
        }, name: 'blog.');

        $this->assertSame('/blog/42', $urls->path('blog.article', ['id' => 42]));
        $this->assertSame($urls, $builder->getUrlGenerator());
    }

    public function testRouteValidator(): void
    {
        $app = $this->builder()
            ->addErrorMiddleware()
            ->addRouteValidator('slug', fn(string $value): bool => preg_match('/^[a-z0-9-]+$/', $value) === 1)
            ->get('/blog/:name:slug', new TestHandler($this->factory))
            ->build();

        $this->assertSame('hello-world', $this->body($app->handle(new ServerRequest('GET', '/blog/hello-world'))));
        $this->assertSame(404, $app->handle(new ServerRequest('GET', '/blog/Hello_World'))->getStatusCode());
    }

    public function testNotFoundAndMethodNotAllowed(): void
    {
        $app = $this->builder()
            ->addErrorMiddleware()
            ->addRoutingMiddleware()
            ->map(['GET', 'POST'], '/item', new TestHandler($this->factory))
            ->build();

        $response = $app->handle(new ServerRequest('GET', '/missing'));
        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('{"type":"about:blank","title":"Not Found","status":404}', $this->body($response));

        $response = $app->handle(new ServerRequest('DELETE', '/item'));
        $this->assertSame(405, $response->getStatusCode());
        $this->assertSame('GET, POST, HEAD, OPTIONS', $response->getHeaderLine('Allow'));
    }

    public function testMethodNotAllowedForGet(): void
    {
        $app = $this->builder()
            ->addErrorMiddleware()
            ->post('/item', new TestHandler($this->factory))
            ->build();

        $response = $app->handle(new ServerRequest('GET', '/item'));
        $this->assertSame(405, $response->getStatusCode());
        $this->assertSame('POST, OPTIONS', $response->getHeaderLine('Allow'));
    }

    public function testHeadFallsBackToGet(): void
    {
        $app = $this->builder()
            ->addRoutingMiddleware()
            ->get('/', new TestHandler($this->factory))
            ->build();

        $this->assertSame(200, $app->handle(new ServerRequest('HEAD', '/'))->getStatusCode());
    }

    public function testMissingRoutingMiddleware(): void
    {
        $this->expectException(LogicException::class);
        (new Application([], new EndpointHandler($this->factory)))->handle(new ServerRequest('GET', '/'));
    }

    public function testLazyResolution(): void
    {
        $container = $this->container([
            'handler' => new TestHandler($this->factory),
            'middleware' => new TestMiddleware('lazy'),
        ]);

        $app = $this->builder($container)
            ->add('middleware')
            ->addRoutingMiddleware()
            ->get('/', 'handler', ['middleware'])
            ->build();

        $this->assertSame('handler > lazy > lazy', $this->body($app->handle(new ServerRequest('GET', '/'))));
    }

    public function testLazyResolutionInvalidEntry(): void
    {
        $app = $this->builder($this->container(['handler' => new stdClass()]))
            ->get('/', 'handler')
            ->build();

        $this->expectException(UnexpectedValueException::class);
        $app->handle(new ServerRequest('GET', '/'));
    }

    public function testLazyResolutionWithoutContainer(): void
    {
        $this->expectException(LogicException::class);
        $this->builder()->get('/', 'handler');
    }

    public function testErrorHandlerDebug(): void
    {
        $failing = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw new RuntimeException('outer', 0, new LogicException('inner'));
            }
        };

        $build = fn(bool $debug) => $this->builder()
            ->addErrorMiddleware(new ErrorHandler($this->factory, $debug))
            ->addRoutingMiddleware()
            ->get('/', $failing)
            ->build();

        $response = $build(false)->handle(new ServerRequest('GET', '/'));
        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame('application/problem+json', $response->getHeaderLine('Content-Type'));
        $this->assertSame('{"type":"about:blank","title":"Internal Server Error","status":500}', $this->body($response));

        $data = json_decode($this->body($build(true)->handle(new ServerRequest('GET', '/'))), true);
        $this->assertSame(['outer', 'inner'], array_column($data['errors'], 'message'));
    }

    public function testErrorMessagesAndLogging(): void
    {
        $logger = new class extends AbstractLogger {
            /** @var list<string> */
            public array $messages = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->messages[] = (string) $message;
            }
        };

        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                if ($request->getUri()->getPath() === '/user') {
                    throw new HttpNotFoundException('User not found');
                }
                throw new RuntimeException('Secret internal error');
            }
        };

        $app = $this->builder()
            ->addErrorMiddleware(new ErrorHandler($this->factory, logger: $logger))
            ->get('/user', $handler)
            ->get('/fail', $handler)
            ->build();

        $response = $app->handle(new ServerRequest('GET', '/user'));
        $this->assertSame('{"type":"about:blank","title":"Not Found","status":404,"detail":"User not found"}', $this->body($response));
        $this->assertSame([], $logger->messages);

        $response = $app->handle(new ServerRequest('GET', '/fail'));
        $this->assertSame('{"type":"about:blank","title":"Internal Server Error","status":500}', $this->body($response));
        $this->assertSame(['Secret internal error'], $logger->messages);
    }

    public function testRequestBody(): void
    {
        $echo = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $response = (new Psr17Factory())->createResponse();
                $response->getBody()->write((string) json_encode($request->getParsedBody()));
                return $response;
            }
        };

        $app = $this->builder()
            ->addErrorMiddleware()
            ->add(new RequestBodyMiddleware())
            ->addRoutingMiddleware()
            ->post('/', $echo)
            ->build();

        $json = new ServerRequest('POST', '/', ['Content-Type' => 'application/json; charset=utf-8'], '{"a":1}');
        $this->assertSame('{"a":1}', $this->body($app->handle($json)));

        $patch = new ServerRequest('POST', '/', ['Content-Type' => 'application/merge-patch+json'], '{"b":2}');
        $this->assertSame('{"b":2}', $this->body($app->handle($patch)));

        $form = new ServerRequest('POST', '/', ['Content-Type' => 'application/x-www-form-urlencoded'], 'a=1&b[]=2');
        $this->assertSame('{"a":"1","b":["2"]}', $this->body($app->handle($form)));

        $parsed = $form->withParsedBody(['c' => 3]);
        $this->assertSame('{"c":3}', $this->body($app->handle($parsed)));

        $invalid = new ServerRequest('POST', '/', ['Content-Type' => 'application/json'], '{a');
        $this->assertSame(400, $app->handle($invalid)->getStatusCode());

        $empty = new ServerRequest('POST', '/', ['Content-Type' => 'application/json']);
        $this->assertSame('null', $this->body($app->handle($empty)));

        // e.g. laminas fromGlobals() passes an empty $_POST
        $emptyPost = $json->withParsedBody([]);
        $this->assertSame('{"a":1}', $this->body($app->handle($emptyPost)));
    }

    public function testRequestBodyKeepsOtherBodiesUnread(): void
    {
        $position = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $response = (new Psr17Factory())->createResponse();
                $response->getBody()->write((string) $request->getBody()->tell());
                return $response;
            }
        };

        $app = $this->builder()
            ->add(new RequestBodyMiddleware())
            ->post('/', $position)
            ->build();

        $binary = new ServerRequest('POST', '/', ['Content-Type' => 'application/octet-stream'], 'binary');
        $this->assertSame('0', $this->body($app->handle($binary)));
    }

    public function testBuilderIsLockedAfterBuild(): void
    {
        $builder = $this->builder();
        $builder->build();

        $calls = [
            fn() => $builder->get('/', new TestHandler($this->factory)),
            fn() => $builder->add(new TestMiddleware('late')),
            fn() => $builder->addRoutingMiddleware(),
            fn() => $builder->addRouteValidator('id', fn(string $value): bool => true),
            fn() => $builder->group('/api', fn() => null),
        ];

        foreach ($calls as $call) {
            try {
                $call();
                $this->fail('Expected a LogicException');
            } catch (LogicException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testRouteMetadata(): void
    {
        $metadata = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                $route = RouteResult::fromRequest($request)?->route;

                $response = $handler->handle($request);
                $response->getBody()->write($route === null ? ' > none' : " > {$route->name} {$route->pattern}");
                return $response;
            }
        };

        $app = $this->builder()
            ->addRoutingMiddleware()
            ->add($metadata)
            ->group('/blog', function (RouteCollectorInterface $blog) {
                $blog->get('/:id:d', new TestHandler($this->factory), name: 'article');
                $blog->get('/latest', new TestHandler($this->factory));
            }, name: 'blog.')
            ->build();

        $this->assertSame('handler > blog.article /blog/:id:d', $this->body($app->handle(new ServerRequest('GET', '/blog/42'))));
        $this->assertSame('handler >  /blog/latest', $this->body($app->handle(new ServerRequest('GET', '/blog/latest'))));
        $this->assertNull(RouteResult::fromRequest(new ServerRequest('GET', '/')));
    }

    public function testReuseAcrossRequests(): void
    {
        $app = $this->builder()
            ->addErrorMiddleware()
            ->addRoutingMiddleware()
            ->get('/hello/:name', new TestHandler($this->factory), [new TestMiddleware('mw')])
            ->build();

        for ($i = 0; $i < 3; $i++) {
            $this->assertSame("$i > mw", $this->body($app->handle(new ServerRequest('GET', "/hello/$i"))));
            $this->assertSame(404, $app->handle(new ServerRequest('GET', '/'))->getStatusCode());
        }
    }

    public function testRouteResult(): void
    {
        $spy = new class implements MiddlewareInterface {
            public ?RouteResult $result = null;
            public mixed $attribute = null;

            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                $this->result = RouteResult::fromRequest($request);
                $this->attribute = $request->getAttribute('id');
                return $handler->handle($request);
            }
        };

        $app = $this->builder()
            ->addErrorMiddleware()
            ->addRoutingMiddleware()
            ->add($spy)
            ->map(['GET', 'POST'], '/item/:id', new TestHandler($this->factory), name: 'item')
            ->build();

        $app->handle(new ServerRequest('GET', '/item/a%20b'));
        $result = $spy->result;
        $this->assertNotNull($result);
        $this->assertSame('item', $result->route?->name);
        $this->assertSame(['id' => 'a b'], $result->params);
        $this->assertSame('a b', $result->param('id'));
        $this->assertNull($result->param('missing'));
        $this->assertSame('default', $result->param('missing', 'default'));
        $this->assertSame([], $result->allowedMethods);
        // params are no top-level attributes
        $this->assertNull($spy->attribute);

        $app->handle(new ServerRequest('DELETE', '/item/1'));
        $this->assertNull($spy->result?->route);
        $this->assertSame(['GET', 'POST', 'HEAD', 'OPTIONS'], $spy->result?->allowedMethods);

        $app->handle(new ServerRequest('GET', '/missing'));
        $this->assertNull($spy->result?->route);
        $this->assertSame([], $spy->result?->allowedMethods);
    }

    public function testRoutingMiddlewareAddedTwice(): void
    {
        $this->expectException(LogicException::class);
        $this->builder()->addRoutingMiddleware()->addRoutingMiddleware();
    }

    public function testErrorHandlerInvalidStatusCode(): void
    {
        $request = new ServerRequest('GET', '/');
        $exception = new class ('Not for the client') extends HttpException {
            protected const STATUS_CODE = 200;
            protected const REASON_PHRASE = 'OK';
        };

        $response = (new ErrorHandler($this->factory))->handle($request, $exception);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame('{"type":"about:blank","title":"Internal Server Error","status":500}', $this->body($response));
    }

    public function testRequestBodyStaysReadable(): void
    {
        $echo = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $response = (new Psr17Factory())->createResponse();
                $response->getBody()->write(json_encode($request->getParsedBody()) . ' ' . $request->getBody()->getContents());
                return $response;
            }
        };

        $app = $this->builder()
            ->add(new RequestBodyMiddleware())
            ->post('/', $echo)
            ->build();

        $json = new ServerRequest('POST', '/', ['Content-Type' => 'application/json'], '{"a":1}');
        $this->assertSame('{"a":1} {"a":1}', $this->body($app->handle($json)));
    }

    public function testUrlGeneratorErrors(): void
    {
        $builder = $this->builder()->get('/item/:id:d', new TestHandler($this->factory), name: 'item');
        $urls = $builder->getUrlGenerator();

        $calls = [
            OutOfBoundsException::class => fn() => $urls->path('missing'),
            InvalidArgumentException::class => fn() => $urls->path('item'),
            InvalidArgumentException::class . ' (invalid)' => fn() => $urls->path('item', ['id' => 'abc']),
        ];

        foreach ($calls as $class => $call) {
            try {
                $call();
                $this->fail("Expected $class");
            } catch (OutOfBoundsException|InvalidArgumentException $exception) {
                $this->assertStringStartsWith($exception::class, $class);
            }
        }
    }

    public function testOptions(): void
    {
        $app = $this->builder()
            ->addErrorMiddleware()
            ->add(new TestMiddleware('global'))
            ->group('/api', function (RouteCollectorInterface $api) {
                $api->map(['GET', 'POST'], '/items', new TestHandler($this->factory), [new TestMiddleware('route')]);
                $api->options('/custom', new TestHandler($this->factory, 'custom'));
            }, [new TestMiddleware('group')])
            ->build();

        // answered without route and group middleware, global middleware (e.g. cors) still runs
        $response = $app->handle(new ServerRequest('OPTIONS', '/api/items'));
        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('GET, POST, HEAD, OPTIONS', $response->getHeaderLine('Allow'));
        $this->assertSame(' > global', $this->body($response));

        // an OPTIONS route takes precedence
        $response = $app->handle(new ServerRequest('OPTIONS', '/api/custom'));
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('custom > group > global', $this->body($response));

        $this->assertSame(404, $app->handle(new ServerRequest('OPTIONS', '/api/missing'))->getStatusCode());
    }

    public function testAny(): void
    {
        $app = $this->builder()
            ->any('/any', new TestHandler($this->factory, 'any'))
            ->build();

        foreach (['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'] as $method) {
            $this->assertSame('any', $this->body($app->handle(new ServerRequest($method, '/any'))), $method);
        }
    }

    public function testBasePath(): void
    {
        $builder = new ApplicationBuilder($this->factory, basePath: '/app/');
        $app = $builder
            ->addErrorMiddleware()
            ->get('/', new TestHandler($this->factory, 'index'))
            ->get('/hello/:name', new TestHandler($this->factory), name: 'hello')
            ->build();

        $this->assertSame('index', $this->body($app->handle(new ServerRequest('GET', '/app'))));
        $this->assertSame('index', $this->body($app->handle(new ServerRequest('GET', '/app/'))));
        $this->assertSame('world', $this->body($app->handle(new ServerRequest('GET', '/app/hello/world'))));

        foreach (['/', '/hello/world', '/application', ''] as $path) {
            $this->assertSame(404, $app->handle(new ServerRequest('GET', $path))->getStatusCode(), $path);
        }

        $this->assertSame('/app/hello/world', $builder->getUrlGenerator()->path('hello', ['name' => 'world']));
    }

    public function testInvalidBasePath(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ApplicationBuilder($this->factory, basePath: 'app');
    }

    public function testGlobalMiddlewareInsideGroup(): void
    {
        $builder = $this->builder();

        try {
            $builder->group('/api', function (RouteCollectorInterface $api) {
                /** @var ApplicationBuilder $api */
                $api->add(new TestMiddleware('global'));
            });
            $this->fail('Expected a LogicException');
        } catch (LogicException) {
            // the group state is reset, the builder stays usable
            $app = $builder->get('/', new TestHandler($this->factory))->build();
            $this->assertSame('handler', $this->body($app->handle(new ServerRequest('GET', '/'))));
        }
    }
}
