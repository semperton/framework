<?php

declare(strict_types=1);

namespace Semperton\Framework\Interfaces;

use Closure;
use Psr\Http\Server\MiddlewareInterface;

interface RouteCollectorInterface
{
	/**
	 * @param array<int, string|callable|MiddlewareInterface> $middleware
	 */
	public function group(string $path, Closure $callback, array $middleware = []): RouteCollectorInterface;
	/**
	 * @param array<int, string> $methods
	 * @param mixed $handler
	 * @param array<int, string|callable|MiddlewareInterface> $middleware
	 */
	public function map(array $methods, string $path, $handler, array $middleware = []): RouteCollectorInterface;
	/**
	 * @param mixed $handler
	 * @param array<int, string|callable|MiddlewareInterface> $middleware
	 */
	public function get(string $path, $handler, array $middleware = []): RouteCollectorInterface;
	/**
	 * @param mixed $handler
	 * @param array<int, string|callable|MiddlewareInterface> $middleware
	 */
	public function post(string $path, $handler, array $middleware = []): RouteCollectorInterface;
	/**
	 * @param mixed $handler
	 * @param array<int, string|callable|MiddlewareInterface> $middleware
	 */
	public function put(string $path, $handler, array $middleware = []): RouteCollectorInterface;
	/**
	 * @param mixed $handler
	 * @param array<int, string|callable|MiddlewareInterface> $middleware
	 */
	public function delete(string $path, $handler, array $middleware = []): RouteCollectorInterface;
	/**
	 * @param mixed $handler
	 * @param array<int, string|callable|MiddlewareInterface> $middleware
	 */
	public function patch(string $path, $handler, array $middleware = []): RouteCollectorInterface;
	/**
	 * @param mixed $handler
	 * @param array<int, string|callable|MiddlewareInterface> $middleware
	 */
	public function head(string $path, $handler, array $middleware = []): RouteCollectorInterface;
	/**
	 * @param mixed $handler
	 * @param array<int, string|callable|MiddlewareInterface> $middleware
	 */
	public function options(string $path, $handler, array $middleware = []): RouteCollectorInterface;
}
