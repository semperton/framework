<?php

declare(strict_types=1);

namespace Semperton\Framework;

use Closure;
use InvalidArgumentException;
use LogicException;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Semperton\Framework\Error\ErrorHandler;
use Semperton\Framework\Error\ErrorHandlerInterface;
use Semperton\Framework\Internal\EndpointHandler;
use Semperton\Framework\Internal\LazyHandler;
use Semperton\Framework\Internal\MiddlewareHandler;
use Semperton\Framework\Internal\RoutingMiddleware;
use Semperton\Framework\Middleware\ErrorMiddleware;
use Semperton\Routing\Collection\RouteCollection;
use Semperton\Routing\Matcher\RouteMatcher;

use function is_string;
use function rtrim;
use function str_starts_with;

final class ApplicationBuilder implements RouteCollectorInterface
{
    private readonly string $basePath;

    private readonly RouteCollection $routeCollection;

    private ?UrlGenerator $urlGenerator = null;

    /** @var list<MiddlewareInterface> */
    private array $middleware = [];

    private bool $routing = false;

    private bool $built = false;

    // state of the current group() callback

    private int $groupDepth = 0;

    /** @var list<MiddlewareInterface> */
    private array $groupMiddleware = [];

    private string $pathPrefix = '';

    private string $namePrefix = '';

    /**
     * @param string $basePath path prefix of all routes as it appears in the url, e.g. '/app' for sub-directory installs
     */
    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly ?ContainerInterface $container = null,
        string $basePath = '',
    ) {
        $basePath = rtrim($basePath, '/');

        if ($basePath !== '' && !str_starts_with($basePath, '/')) {
            throw new InvalidArgumentException("The base path < $basePath > must start with a slash");
        }

        $this->basePath = $basePath;
        $this->routeCollection = new RouteCollection();
    }

    /**
     * Adds global middleware, the first one added is the outermost
     */
    public function add(MiddlewareInterface|string ...$middleware): static
    {
        $this->assertNotBuilt();
        $this->assertNotInGroup();

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
        // before the flag is set, build() would leave out the routing otherwise
        $this->assertNotInGroup();

        if ($this->routing) {
            throw new LogicException('The routing middleware is already added');
        }

        $this->routing = true;

        return $this->add($this->routingMiddleware());
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
        return $this->urlGenerator ??= new UrlGenerator($this->routeCollection, $this->basePath);
    }

    /**
     * Locks the builder
     */
    public function build(): Application
    {
        $this->assertNotBuilt();
        $this->assertNotInGroup();

        $this->built = true;

        $middleware = $this->middleware;

        if (!$this->routing) {
            $middleware[] = $this->routingMiddleware();
        }

        return new Application($middleware, new EndpointHandler($this->responseFactory));
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
        $this->groupDepth++;

        try {
            $callback($this);
        } finally {
            $this->groupDepth--;
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

    /**
     * Maps GET, POST, PUT, PATCH and DELETE (HEAD falls back to GET, OPTIONS is answered automatically)
     */
    public function any(string $path, RequestHandlerInterface|string $handler, array $middleware = [], string $name = ''): static
    {
        return $this->map(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], $path, $handler, $middleware, $name);
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

    private function routingMiddleware(): RoutingMiddleware
    {
        return new RoutingMiddleware(new RouteMatcher($this->routeCollection), $this->basePath);
    }

    private function assertNotBuilt(): void
    {
        if ($this->built) {
            throw new LogicException('The application is already built, configure the builder before calling build()');
        }
    }

    private function assertNotInGroup(): void
    {
        if ($this->groupDepth > 0) {
            throw new LogicException('Global middleware cannot be added inside a group, pass it to group() instead');
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
