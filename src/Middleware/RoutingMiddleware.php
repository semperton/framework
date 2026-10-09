<?php

declare(strict_types=1);

namespace Semperton\Framework\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Semperton\Routing\Matcher\RequestMatcherInterface;

/**
 * Adds the MatchResult and the route params as request attributes
 */
final class RoutingMiddleware implements MiddlewareInterface
{
    public const ATTRIBUTE = '_route_match_result';

    public function __construct(
        private readonly RequestMatcherInterface $requestMatcher,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $matchResult = $this->requestMatcher->matchRequest($request);
        $request = $request->withAttribute(self::ATTRIBUTE, $matchResult);

        foreach ($matchResult->getParams() as $name => $value) {
            $request = $request->withAttribute($name, $value);
        }

        return $handler->handle($request);
    }
}
