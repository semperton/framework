<?php

declare(strict_types=1);

namespace Semperton\Framework;

use Psr\Http\Server\RequestHandlerInterface;

/**
 * Matched route, get it from RouteResult::fromRequest()
 */
final class Route
{
    /**
     * @internal
     * @param RequestHandlerInterface $handler includes the route and group middleware
     */
    public function __construct(
        public readonly string $pattern,
        public readonly string $name,
        public readonly RequestHandlerInterface $handler,
    ) {}
}
