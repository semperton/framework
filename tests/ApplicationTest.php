<?php

declare(strict_types=1);

namespace Semperton\Framework\Tests;

use LogicException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\AbstractLogger;
use RuntimeException;
use Semperton\Framework\Application;
use Semperton\Framework\ApplicationBuilder;
use Semperton\Framework\Exception\HttpNotFoundException;
use Semperton\Framework\Handler\EndpointHandler;
use Semperton\Framework\Handler\ErrorHandler;
use Semperton\Framework\Interfaces\RouteCollectorInterface;
use Semperton\Framework\Middleware\RequestBodyMiddleware;
use Semperton\Framework\Tests\Misc\TestHandler;
use Semperton\Framework\Tests\Misc\TestMiddleware;
use Semperton\Routing\Collection\RouteCollection;

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

    private function builder(?ContainerInterface $container = null, ?RouteCollection $routes = null): ApplicationBuilder
    {
        return new ApplicationBuilder($this->factory, $container, $routes ?? new RouteCollection());
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
        $routes = new RouteCollection();
        $handler = new TestHandler($this->factory);

        $this->builder(null, $routes)->group('/blog', function (RouteCollectorInterface $blog) use ($handler) {
            $blog->get('/:id:d', $handler, name: 'article');
        }, name: 'blog.');

        $this->assertSame('/blog/42', $routes->reverse('blog.article', ['id' => 42]));
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
        $this->assertSame('{"status":404,"message":"Not Found"}', $this->body($response));

        $response = $app->handle(new ServerRequest('DELETE', '/item'));
        $this->assertSame(405, $response->getStatusCode());
        $this->assertSame('GET, POST, HEAD', $response->getHeaderLine('Allow'));
    }

    public function testMethodNotAllowedForGet(): void
    {
        $app = $this->builder()
            ->addErrorMiddleware()
            ->post('/item', new TestHandler($this->factory))
            ->build();

        $response = $app->handle(new ServerRequest('GET', '/item'));
        $this->assertSame(405, $response->getStatusCode());
        $this->assertSame('POST', $response->getHeaderLine('Allow'));
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
        (new Application([], new EndpointHandler()))->handle(new ServerRequest('GET', '/'));
    }

    public function testLazyResolution(): void
    {
        $entries = [
            'handler' => new TestHandler($this->factory),
            'middleware' => new TestMiddleware('lazy'),
        ];

        $container = new class ($entries) implements ContainerInterface {
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

        $app = $this->builder($container)
            ->add('middleware')
            ->addRoutingMiddleware()
            ->get('/', 'handler', ['middleware'])
            ->build();

        $this->assertSame('handler > lazy > lazy', $this->body($app->handle(new ServerRequest('GET', '/'))));
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
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame('{"status":500,"message":"Internal Server Error"}', $this->body($response));

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
                    throw new HttpNotFoundException($request, 'User not found');
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
        $this->assertSame('{"status":404,"message":"User not found"}', $this->body($response));
        $this->assertSame([], $logger->messages);

        $response = $app->handle(new ServerRequest('GET', '/fail'));
        $this->assertSame('{"status":500,"message":"Internal Server Error"}', $this->body($response));
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
}
