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
     * @param string|callable|ActionInterface $action
     * @param array<int, string|callable|MiddlewareInterface> $middleware
     */
    public function map(array $methods, string $path, $action, array $middleware = []): RouteCollectorInterface;

    /**
     * @param string|callable|ActionInterface $action
     * @param array<int, string|callable|MiddlewareInterface> $middleware
     */
    public function get(string $path, $action, array $middleware = []): RouteCollectorInterface;

    /**
     * @param string|callable|ActionInterface $action
     * @param array<int, string|callable|MiddlewareInterface> $middleware
     */
    public function post(string $path, $action, array $middleware = []): RouteCollectorInterface;

    /**
     * @param string|callable|ActionInterface $action
     * @param array<int, string|callable|MiddlewareInterface> $middleware
     */
    public function put(string $path, $action, array $middleware = []): RouteCollectorInterface;

    /**
     * @param string|callable|ActionInterface $action
     * @param array<int, string|callable|MiddlewareInterface> $middleware
     */
    public function delete(string $path, $action, array $middleware = []): RouteCollectorInterface;

    /**
     * @param string|callable|ActionInterface $action
     * @param array<int, string|callable|MiddlewareInterface> $middleware
     */
    public function patch(string $path, $action, array $middleware = []): RouteCollectorInterface;

    /**
     * @param string|callable|ActionInterface $action
     * @param array<int, string|callable|MiddlewareInterface> $middleware
     */
    public function options(string $path, $action, array $middleware = []): RouteCollectorInterface;
}
