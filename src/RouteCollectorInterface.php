<?php

declare(strict_types=1);

namespace Semperton\Framework;

use Closure;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Type hint for group callbacks, not meant to be implemented, methods may be added in minor versions
 */
interface RouteCollectorInterface
{
    /**
     * @param Closure(RouteCollectorInterface): void $callback
     * @param list<MiddlewareInterface|string> $middleware
     */
    public function group(string $path, Closure $callback, array $middleware = [], string $name = ''): static;

    /**
     * @param list<string> $methods uppercase, e.g. 'GET'
     * @param list<MiddlewareInterface|string> $middleware
     */
    public function map(
        array $methods,
        string $path,
        RequestHandlerInterface|string $handler,
        array $middleware = [],
        string $name = '',
    ): static;

    /**
     * Maps GET, POST, PUT, PATCH, DELETE and OPTIONS (HEAD falls back to GET)
     *
     * @param list<MiddlewareInterface|string> $middleware
     */
    public function any(string $path, RequestHandlerInterface|string $handler, array $middleware = [], string $name = ''): static;

    /**
     * @param list<MiddlewareInterface|string> $middleware
     */
    public function get(string $path, RequestHandlerInterface|string $handler, array $middleware = [], string $name = ''): static;

    /**
     * @param list<MiddlewareInterface|string> $middleware
     */
    public function post(string $path, RequestHandlerInterface|string $handler, array $middleware = [], string $name = ''): static;

    /**
     * @param list<MiddlewareInterface|string> $middleware
     */
    public function put(string $path, RequestHandlerInterface|string $handler, array $middleware = [], string $name = ''): static;

    /**
     * @param list<MiddlewareInterface|string> $middleware
     */
    public function delete(string $path, RequestHandlerInterface|string $handler, array $middleware = [], string $name = ''): static;

    /**
     * @param list<MiddlewareInterface|string> $middleware
     */
    public function patch(string $path, RequestHandlerInterface|string $handler, array $middleware = [], string $name = ''): static;

    /**
     * @param list<MiddlewareInterface|string> $middleware
     */
    public function options(string $path, RequestHandlerInterface|string $handler, array $middleware = [], string $name = ''): static;
}
