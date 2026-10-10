<?php

declare(strict_types=1);

namespace Semperton\Framework;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Semperton\Framework\Middleware\RoutingMiddleware;
use Semperton\Routing\MatchResult;

/**
 * Matched route, the handler includes the route and group middleware
 */
final class Route
{
    public function __construct(
        public readonly string $pattern,
        public readonly string $name,
        public readonly RequestHandlerInterface $handler,
    ) {}

    /**
     * The matched route, null without a match
     */
    public static function fromRequest(ServerRequestInterface $request): ?self
    {
        /** @var mixed */
        $matchResult = $request->getAttribute(RoutingMiddleware::ATTRIBUTE);

        if ($matchResult instanceof MatchResult) {
            /** @var mixed */
            $handler = $matchResult->getHandler();
            return $handler instanceof self ? $handler : null;
        }

        return null;
    }
}
