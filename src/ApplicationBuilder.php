<?php

declare(strict_types=1);

namespace Semperton\Framework;

use Closure;
use LogicException;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Semperton\Framework\Handler\EndpointHandler;
use Semperton\Framework\Handler\ErrorHandler;
use Semperton\Framework\Interfaces\ErrorHandlerInterface;
use Semperton\Framework\Interfaces\RouteCollectorInterface;
use Semperton\Framework\Middleware\ErrorMiddleware;
use Semperton\Framework\Middleware\RoutingMiddleware;
use Semperton\Routing\Collection\RouteCollection;
use Semperton\Routing\Matcher\RouteMatcher;

use function is_string;

final class ApplicationBuilder implements RouteCollectorInterface
{
    /** @var list<MiddlewareInterface> */
    private array $middleware = [];

    /** @var list<MiddlewareInterface> */
    private array $groupMiddleware = [];

    private string $pathPrefix = '';

    private string $namePrefix = '';

    private bool $routing = false;

    private bool $built = false;

    private readonly RouteCollection $routeCollection;

    private ?UrlGenerator $urlGenerator = null;

    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly ?ContainerInterface $container = null,
    ) {
        $this->routeCollection = new RouteCollection();
    }

    /**
     * The first middleware added is the outermost
     */
    public function add(MiddlewareInterface|string ...$middleware): static
    {
        $this->assertNotBuilt();

        $this->middleware = [...$this->middleware, ...$this->resolveMiddleware($middleware)];

        return $this;
    }

    public function addErrorMiddleware(?ErrorHandlerInterface $errorHandler = null): static
    {
        return $this->add(new ErrorMiddleware($errorHandler ?? new ErrorHandler($this->responseFactory)));
    }

    /**
     * Optional, build() adds the routing as innermost middleware otherwise
     */
    public function addRoutingMiddleware(): static
    {
        $this->assertNotBuilt();

        if ($this->routing) {
            throw new LogicException('The routing middleware is already added');
        }

        $this->routing = true;

        return $this->add(new RoutingMiddleware(new RouteMatcher($this->routeCollection)));
    }

    /**
     * Validators MUST be added before the routes using them, e.g. '/blog/:slug:slug'
     *
     * @param callable(string): bool $validator receives the decoded path segment
     */
    public function addRouteValidator(string $id, callable $validator): static
    {
        $this->assertNotBuilt();

        $this->routeCollection->setValidator($id, $validator);

        return $this;
    }

    /**
     * Builds the paths of named routes, also usable before build()
     */
    public function getUrlGenerator(): UrlGenerator
    {
        return $this->urlGenerator ??= new UrlGenerator($this->routeCollection);
    }

    /**
     * Locks the builder
     */
    public function build(): Application
    {
        $this->built = true;

        $middleware = $this->middleware;

        if (!$this->routing) {
            $middleware[] = new RoutingMiddleware(new RouteMatcher($this->routeCollection));
        }

        return new Application($middleware, new EndpointHandler());
    }

    public function group(string $path, Closure $callback, array $middleware = [], string $name = ''): static
    {
        $this->assertNotBuilt();

        $currentMiddleware = $this->groupMiddleware;
        $currentPath = $this->pathPrefix;
        $currentName = $this->namePrefix;

        $this->groupMiddleware = [...$currentMiddleware, ...$this->resolveMiddleware($middleware)];
        $this->pathPrefix .= $path;
        $this->namePrefix .= $name;

        try {
            $callback($this);
        } finally {
            $this->groupMiddleware = $currentMiddleware;
            $this->pathPrefix = $currentPath;
            $this->namePrefix = $currentName;
        }

        return $this;
    }

    public function map(
        array $methods,
        string $path,
        RequestHandlerInterface|string $handler,
        array $middleware = [],
        string $name = '',
    ): static {
        $this->assertNotBuilt();

        $handler = is_string($handler) ? $this->lazy($handler) : $handler;
        $middleware = [...$this->groupMiddleware, ...$this->resolveMiddleware($middleware)];

        $path = $this->pathPrefix . $path;
        $name = $name === '' ? '' : $this->namePrefix . $name;
        $route = new Route($path, $name, MiddlewareHandler::pipe($middleware, $handler));

        $this->routeCollection->map($methods, $path, $route, $name);

        return $this;
    }

    public function get(string $path, RequestHandlerInterface|string $handler, array $middleware = [], string $name = ''): static
    {
        return $this->map(['GET'], $path, $handler, $middleware, $name);
    }

    public function post(string $path, RequestHandlerInterface|string $handler, array $middleware = [], string $name = ''): static
    {
        return $this->map(['POST'], $path, $handler, $middleware, $name);
    }

    public function put(string $path, RequestHandlerInterface|string $handler, array $middleware = [], string $name = ''): static
    {
        return $this->map(['PUT'], $path, $handler, $middleware, $name);
    }

    public function delete(string $path, RequestHandlerInterface|string $handler, array $middleware = [], string $name = ''): static
    {
        return $this->map(['DELETE'], $path, $handler, $middleware, $name);
    }

    public function patch(string $path, RequestHandlerInterface|string $handler, array $middleware = [], string $name = ''): static
    {
        return $this->map(['PATCH'], $path, $handler, $middleware, $name);
    }

    public function options(string $path, RequestHandlerInterface|string $handler, array $middleware = [], string $name = ''): static
    {
        return $this->map(['OPTIONS'], $path, $handler, $middleware, $name);
    }

    /**
     * @param array<MiddlewareInterface|string> $middleware
     * @return list<MiddlewareInterface>
     */
    private function resolveMiddleware(array $middleware): array
    {
        $resolved = [];

        foreach ($middleware as $entry) {
            $resolved[] = is_string($entry) ? $this->lazy($entry) : $entry;
        }

        return $resolved;
    }

    private function assertNotBuilt(): void
    {
        if ($this->built) {
            throw new LogicException('The application is already built, configure the builder before calling build()');
        }
    }

    private function lazy(string $id): LazyHandler
    {
        if ($this->container === null) {
            throw new LogicException("Unable to resolve < $id >, no container defined");
        }

        return new LazyHandler($this->container, $id);
    }
}
