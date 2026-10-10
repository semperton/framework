<?php

declare(strict_types=1);

namespace Semperton\Framework\Middleware;

use LogicException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Semperton\Framework\Route;
use Semperton\Framework\RouteResult;
use Semperton\Routing\Matcher\RequestMatcherInterface;

use function get_debug_type;
use function in_array;

/**
 * Adds the RouteResult as request attribute
 *
 * @internal added by the ApplicationBuilder
 */
final class RoutingMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly RequestMatcherInterface $requestMatcher,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $matchResult = $this->requestMatcher->matchRequest($request);

        if ($matchResult->isMatch()) {
            /** @var mixed */
            $route = $matchResult->getHandler();

            if (!($route instanceof Route)) {
                throw new LogicException('Matched handler must be a Route, got < ' . get_debug_type($route) . ' >');
            }

            $result = new RouteResult($route, $matchResult->getParams());
        } else {
            $methods = $matchResult->getMethods();

            // GET routes also serve HEAD requests
            if (in_array('GET', $methods, true) && !in_array('HEAD', $methods, true)) {
                $methods[] = 'HEAD';
            }

            $result = new RouteResult(null, [], $methods);
        }

        return $handler->handle($request->withAttribute(RouteResult::ATTRIBUTE, $result));
    }
}
