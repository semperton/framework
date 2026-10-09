<?php

declare(strict_types=1);

namespace Semperton\Framework\Handler;

use LogicException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Semperton\Framework\Exception\HttpMethodNotAllowedException;
use Semperton\Framework\Exception\HttpNotFoundException;
use Semperton\Framework\Middleware\RoutingMiddleware;
use Semperton\Routing\MatchResult;

/**
 * Last handler of the application, dispatches the matched route
 */
final class EndpointHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $matchResult = $request->getAttribute(RoutingMiddleware::ATTRIBUTE);

        if (!($matchResult instanceof MatchResult)) {
            throw new LogicException('No route match result found, add the RoutingMiddleware');
        }

        if ($matchResult->isMatch()) {
            /** @var RequestHandlerInterface */
            $handler = $matchResult->getHandler();
            return $handler->handle($request);
        }

        $methods = $matchResult->getMethods();
        $method = $request->getMethod();

        // GET, HEAD requests must not respond with a 405 status
        if ($methods !== [] && $method !== 'GET' && $method !== 'HEAD') {
            throw new HttpMethodNotAllowedException($request, $methods);
        }

        throw new HttpNotFoundException($request);
    }
}
