<?php

declare(strict_types=1);

namespace Semperton\Framework\Interfaces;

use Closure;
use Psr\Http\Server\MiddlewareInterface;
use Semperton\Routing\RouteCollectionInterface;

interface RouteCollectorInterface
{
	public function getRouteCollection(): RouteCollectionInterface;
	/**
	 * @param array<int, string|callable|MiddlewareInterface
	 * @return static
	 */
	public function group(string $path, Closure $callback, array $middleware = []): self;
	/**
	 * @param array<int, string> $methods
	 * @param mixed $handler
	 * @param array<int, string|callable|MiddlewareInterface
	 * @return static
	 */
	public function map(array $methods, string $path, $handler, array $middleware = []): self;
	/**
	 * @param mixed $handler
	 * @param array<int, string|callable|MiddlewareInterface
	 * @return static
	 */
	public function get(string $path, $handler, array $middleware = []): self;
	/**
	 * @param mixed $handler
	 * @param array<int, string|callable|MiddlewareInterface
	 * @return static
	 */
	public function post(string $path, $handler, array $middleware = []): self;
	/**
	 * @param mixed $handler
	 * @param array<int, string|callable|MiddlewareInterface
	 * @return static
	 */
	public function put(string $path, $handler, array $middleware = []): self;
	/**
	 * @param mixed $handler
	 * @param array<int, string|callable|MiddlewareInterface
	 * @return static
	 */
	public function delete(string $path, $handler, array $middleware = []): self;
	/**
	 * @param mixed $handler
	 * @param array<int, string|callable|MiddlewareInterface
	 * @return static
	 */
	public function patch(string $path, $handler, array $middleware = []): self;
	/**
	 * @param mixed $handler
	 * @param array<int, string|callable|MiddlewareInterface
	 * @return static
	 */
	public function head(string $path, $handler, array $middleware = []): self;
	/**
	 * @param mixed $handler
	 * @param array<int, string|callable|MiddlewareInterface
	 * @return static
	 */
	public function options(string $path, $handler, array $middleware = []): self;
}
