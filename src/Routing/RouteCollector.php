<?php

declare(strict_types=1);

namespace Semperton\Framework\Routing;

use Closure;
use Psr\Http\Server\MiddlewareInterface;
use Semperton\Framework\Interfaces\RouteCollectorInterface;
use Semperton\Routing\Collection\RouteCollection;
use Semperton\Routing\Collection\RouteCollectionInterface;
use Semperton\Routing\RouteNode;

use function array_merge;

final class RouteCollector implements RouteCollectorInterface, RouteCollectionInterface
{
    protected RouteCollection $routeCollection;

    /** @var array<int, string|callable|MiddlewareInterface> */
    protected array $groupMiddleware = [];

    public function __construct(?RouteCollection $routeCollection = null)
    {
        $this->routeCollection = $routeCollection ?? new RouteCollection();
    }

    public function getRouteTree(): RouteNode
    {
        return $this->routeCollection->getRouteTree();
    }

    public function group(string $path, Closure $callback, array $middleware = []): self
    {
        $currentMiddleware = $this->groupMiddleware;

        $this->groupMiddleware = array_merge($this->groupMiddleware, $middleware);

        $this->routeCollection->group($path, function () use ($callback) {
            $callback($this);
        });

        $this->groupMiddleware = $currentMiddleware;

        return $this;
    }

    public function map(array $methods, string $path, $action, array $middleware = []): self
    {
        $middleware = array_merge($this->groupMiddleware, $middleware);

        $route = new RouteObject($action, $middleware);

        $this->routeCollection->map($methods, $path, $route);

        return $this;
    }

    public function get(string $path, $action, array $middleware = []): self
    {
        return $this->map(['GET'], $path, $action, $middleware);
    }

    public function post(string $path, $action, array $middleware = []): self
    {
        return $this->map(['POST'], $path, $action, $middleware);
    }

    public function put(string $path, $action, array $middleware = []): self
    {
        return $this->map(['PUT'], $path, $action, $middleware);
    }

    public function delete(string $path, $action, array $middleware = []): self
    {
        return $this->map(['DELETE'], $path, $action, $middleware);
    }

    public function patch(string $path, $action, array $middleware = []): self
    {
        return $this->map(['PATCH'], $path, $action, $middleware);
    }

    public function options(string $path, $action, array $middleware = []): self
    {
        return $this->map(['OPTIONS'], $path, $action, $middleware);
    }
}
