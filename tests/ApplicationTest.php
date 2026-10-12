<?php

declare(strict_types=1);

namespace Semperton\Framework\Tests;

use ErrorException;
use InvalidArgumentException;
use LogicException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Uri;
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
use Semperton\Framework\Error\ErrorHandler;
use Semperton\Framework\Exception\HttpBadRequestException;
use Semperton\Framework\Exception\HttpException;
use Semperton\Framework\Exception\HttpNotFoundException;
use Semperton\Framework\Exception\HttpServiceUnavailableException;
use Semperton\Framework\Exception\HttpTooManyRequestsException;
use Semperton\Framework\Exception\HttpUnauthorizedException;
use Semperton\Framework\Internal\EndpointHandler;
use Semperton\Framework\Middleware\RequestBodyMiddleware;
use Semperton\Framework\Middleware\TrailingSlashMiddleware;
use Semperton\Framework\RouteCollectorInterface;
use Semperton\Framework\RouteResult;
use Semperton\Framework\Tests\Fixtures\RawPathUri;
use Semperton\Framework\Tests\Fixtures\TestHandler;
use Semperton\Framework\Tests\Fixtures\TestMiddleware;
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
            ->addErrorMiddleware()
            ->addRoutingMiddleware()
            ->get('/', new TestHandler($this->factory))
            ->build();

        // the response of GET, the server drops the content (rfc 9110 9.3.2) and sends the length of it (rfc 9110 8.6)
        $response = $app->handle(new ServerRequest('HEAD', '/'));
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('handler', $this->body($response));
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

    public function testRequestBodyLimits(): void
    {
        $middleware = new RequestBodyMiddleware();
        $handler = new TestHandler($this->factory);
        $form = new ServerRequest('PUT', '/', ['Content-Type' => 'application/x-www-form-urlencoded']);

        // parse_str would drop input above the limits of php.ini, empty pairs don't count
        $fields = array_map(static fn(int $i): string => "f$i=1", range(1, (int) ini_get('max_input_vars')));
        $limit = $form->withBody($this->factory->createStream(implode('&&', $fields)));
        $spy = new class implements RequestHandlerInterface {
            public mixed $parsedBody = null;

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->parsedBody = $request->getParsedBody();
                return (new Psr17Factory())->createResponse();
            }
        };
        $middleware->process($limit, $spy);
        $this->assertCount(count($fields), (array) $spy->parsedBody);

        $tooMany = $form->withBody($this->factory->createStream(implode('&', $fields) . '&x=1'));
        $tooDeep = $form->withBody($this->factory->createStream('a' . str_repeat('[a]', (int) ini_get('max_input_nesting_level') + 1) . '=1'));

        foreach ([$tooMany, $tooDeep] as $request) {
            // the error handler of the application stays in place
            $applicationHandler = static fn(): bool => false;
            set_error_handler($applicationHandler);
            try {
                $middleware->process($request, $handler);
                $this->fail('HttpBadRequestException expected');
            } catch (HttpBadRequestException $exception) {
                // the warning of php, e.g. shown in debug mode
                $this->assertInstanceOf(ErrorException::class, $exception->getPrevious());
            } finally {
                $this->assertSame($applicationHandler, set_error_handler(null));
                restore_error_handler();
                restore_error_handler();
            }
        }
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
            fn() => $builder->build(),
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

        foreach (['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $this->assertSame('any', $this->body($app->handle(new ServerRequest($method, '/any'))), $method);
        }

        // preflights get the automatic answer, e.g. for cors
        $response = $app->handle(new ServerRequest('OPTIONS', '/any'));
        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('GET, POST, PUT, PATCH, DELETE, HEAD, OPTIONS', $response->getHeaderLine('Allow'));
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

    public function testRoutingMiddlewareInsideGroup(): void
    {
        $builder = $this->builder();

        try {
            $builder->group('/api', function (RouteCollectorInterface $api) {
                /** @var ApplicationBuilder $api */
                $api->addRoutingMiddleware();
            });
            $this->fail('Expected a LogicException');
        } catch (LogicException) {
            // the routing is still added by build()
            $app = $builder->get('/', new TestHandler($this->factory))->build();
            $this->assertSame('handler', $this->body($app->handle(new ServerRequest('GET', '/'))));
        }
    }

    public function testExceptionHeaders(): void
    {
        $request = new ServerRequest('GET', '/');
        $handler = new ErrorHandler($this->factory);

        $response = $handler->handle($request, new HttpUnauthorizedException('Bearer realm="api"', 'Token expired'));
        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('Bearer realm="api"', $response->getHeaderLine('WWW-Authenticate'));
        $this->assertSame('{"type":"about:blank","title":"Unauthorized","status":401,"detail":"Token expired"}', $this->body($response));

        $response = $handler->handle($request, new HttpTooManyRequestsException(60));
        $this->assertSame(429, $response->getStatusCode());
        $this->assertSame('60', $response->getHeaderLine('Retry-After'));

        $response = $handler->handle($request, new HttpServiceUnavailableException());
        $this->assertSame(503, $response->getStatusCode());
        $this->assertFalse($response->hasHeader('Retry-After'));
        $this->assertSame('application/problem+json', $response->getHeaderLine('Content-Type'));
    }

    public function testInvalidExceptionHeaders(): void
    {
        $logger = new class extends AbstractLogger {
            /** @var list<string> */
            public array $messages = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->messages[] = "$level: $message";
            }
        };
        $handler = new ErrorHandler($this->factory, logger: $logger);

        // invalid headers of custom exceptions are left out, none of them is applied
        $invalid = new class extends HttpException {
            protected const STATUS_CODE = 401;

            protected const REASON_PHRASE = 'Unauthorized';

            public function getHeaders(): array
            {
                return ['X-Valid' => 'yes', 'WWW-Authenticate' => "Bearer\r\nX-Injected: 1"];
            }
        };
        $response = $handler->handle(new ServerRequest('GET', '/'), $invalid);
        $this->assertSame(401, $response->getStatusCode());
        $this->assertFalse($response->hasHeader('X-Valid'));
        $this->assertFalse($response->hasHeader('WWW-Authenticate'));
        $this->assertSame('application/problem+json', $response->getHeaderLine('Content-Type'));
        $this->assertSame('{"type":"about:blank","title":"Unauthorized","status":401}', $this->body($response));
        // a bug of the application, it is logged
        $this->assertSame(['error: Invalid headers of < ' . $invalid::class . ' >, the response is sent without them'], $logger->messages);
    }

    public function testUrlGeneration(): void
    {
        $builder = (new ApplicationBuilder($this->factory, basePath: '/app'))
            ->get('/blog/:id:d', new TestHandler($this->factory), name: 'article');
        $urls = $builder->getUrlGenerator();

        $this->assertSame('/app/blog/42', $urls->path('article', ['id' => 42]));
        $this->assertSame('/app/blog/42?q=a%20b&tags%5B0%5D=x', $urls->path('article', ['id' => 42], ['q' => 'a b', 'tags' => ['x']]));

        // user info and fragment are not taken over
        $base = new Uri('https://user:secret@example.com:8443/old?x=1#fragment');
        $this->assertSame('https://example.com:8443/app/blog/42', $urls->url($base, 'article', ['id' => 42]));
        $this->assertSame('https://example.com:8443/app/blog/42?page=2', $urls->url($base, 'article', ['id' => 42], ['page' => 2]));
    }

    public function testTrailingSlashRedirect(): void
    {
        $app = $this->builder()
            ->addErrorMiddleware()
            ->add(new TrailingSlashMiddleware($this->factory))
            ->get('/', new TestHandler($this->factory, 'index'))
            ->map(['GET', 'POST'], '/users', new TestHandler($this->factory, 'users'))
            ->build();

        $response = $app->handle(new ServerRequest('GET', '/users/?a=1'));
        $this->assertSame(301, $response->getStatusCode());
        $this->assertSame('/users?a=1', $response->getHeaderLine('Location'));

        $response = $app->handle(new ServerRequest('POST', '/users/'));
        $this->assertSame(308, $response->getStatusCode());
        $this->assertSame('Permanent Redirect', $response->getReasonPhrase());
        $this->assertSame('/users', $response->getHeaderLine('Location'));

        $this->assertSame('index', $this->body($app->handle(new ServerRequest('GET', '/'))));
        $this->assertSame('users', $this->body($app->handle(new ServerRequest('GET', '/users'))));

        // no protocol-relative redirect to another host
        $uri = (new Uri('http://localhost'))->withPath('//evil.com/');
        $this->assertSame('/evil.com', $app->handle(new ServerRequest('GET', $uri))->getHeaderLine('Location'));

        // browsers read '/\' as '//', the uri keeps the backslash percent-encoded
        $uri = (new Uri('http://localhost'))->withPath('/\evil.com/');
        $this->assertSame('/%5Cevil.com', $app->handle(new ServerRequest('GET', $uri))->getHeaderLine('Location'));

        // also with PSR-7 implementations that keep the backslash
        $uri = new RawPathUri('http://localhost', '/\evil.com/');
        $this->assertSame('/%5Cevil.com', $app->handle(new ServerRequest('GET', $uri))->getHeaderLine('Location'));

        // no response splitting
        $uri = new RawPathUri('http://localhost', "/a\r\nSet-Cookie: b/");
        $this->assertSame('/a%0D%0ASet-Cookie:%20b', $app->handle(new ServerRequest('GET', $uri))->getHeaderLine('Location'));
    }

    public function testTrailingSlashWithBasePath(): void
    {
        $app = (new ApplicationBuilder($this->factory, basePath: '/app'))
            ->add(new TrailingSlashMiddleware($this->factory))
            ->get('/users', new TestHandler($this->factory, 'users'))
            ->build();

        $this->assertSame('/app/users', $app->handle(new ServerRequest('GET', '/app/users/'))->getHeaderLine('Location'));
        $this->assertSame('/app', $app->handle(new ServerRequest('GET', '/app/'))->getHeaderLine('Location'));
    }

    public function testTrailingSlashRewrite(): void
    {
        $app = $this->builder()
            ->add(new TrailingSlashMiddleware($this->factory, redirect: false))
            ->post('/users', new TestHandler($this->factory, 'users'))
            ->build();

        $response = $app->handle(new ServerRequest('POST', '/users/'));
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('users', $this->body($response));
    }
}
