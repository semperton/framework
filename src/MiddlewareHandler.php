<?php

declare(strict_types=1);

namespace Semperton\Framework;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function count;

/**
 * Immutable link of a middleware chain, safe to reuse across requests
 */
final class MiddlewareHandler implements RequestHandlerInterface
{
    public function __construct(
        private readonly MiddlewareInterface $middleware,
        private readonly RequestHandlerInterface $next
    ) {}

    /**
     * @param list<MiddlewareInterface> $middleware the first entry is the outermost
     */
    public static function pipe(array $middleware, RequestHandlerInterface $handler): RequestHandlerInterface
    {
        for ($i = count($middleware) - 1; $i >= 0; $i--) {
            $handler = new self($middleware[$i], $handler);
        }

        return $handler;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->middleware->process($request, $this->next);
    }
}
