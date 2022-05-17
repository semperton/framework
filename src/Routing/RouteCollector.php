<?php

declare(strict_types=1);

namespace Semperton\Framework\Routing;

use Closure;
use Semperton\Framework\Interfaces\RouteCollectorInterface;
use Semperton\Routing\RouteCollection;
use Semperton\Routing\RouteCollectionInterface;

final class RouteCollector implements RouteCollectorInterface
{
	protected RouteCollection $routeCollection;

	protected array $groupMiddleware = [];

	public function __construct(?RouteCollection $routeCollection = null)
	{
		$this->routeCollection = $routeCollection ?? new RouteCollection();
	}

	public function getRouteCollection(): RouteCollectionInterface
	{
		return $this->routeCollection;
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

	public function map(array $methods, string $path, $handler, array $middleware = []): self
	{
		$middleware = array_merge($this->groupMiddleware, $middleware);

		/** @psalm-suppress MixedArgumentTypeCoercion */
		$route = new RouteObject($handler, $middleware);

		$this->routeCollection->map($methods, $path, $route);

		return $this;
	}

	public function get(string $path, $handler, array $middleware = []): self
	{
		return $this->map(['GET'], $path, $handler, $middleware);
	}

	public function post(string $path, $handler, array $middleware = []): self
	{
		return $this->map(['POST'], $path, $handler, $middleware);
	}

	public function put(string $path, $handler, array $middleware = []): self
	{
		return $this->map(['PUT'], $path, $handler, $middleware);
	}

	public function delete(string $path, $handler, array $middleware = []): self
	{
		return $this->map(['DELETE'], $path, $handler, $middleware);
	}

	public function patch(string $path, $handler, array $middleware = []): self
	{
		return $this->map(['PATCH'], $path, $handler, $middleware);
	}

	public function head(string $path, $handler, array $middleware = []): self
	{
		return $this->map(['HEAD'], $path, $handler, $middleware);
	}

	public function options(string $path, $handler, array $middleware = []): self
	{
		return $this->map(['OPTIONS'], $path, $handler, $middleware);
	}
}
