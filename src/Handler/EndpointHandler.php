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

use function in_array;

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

        if ($methods !== []) {
            // GET routes also serve HEAD requests
            if (in_array('GET', $methods, true) && !in_array('HEAD', $methods, true)) {
                $methods[] = 'HEAD';
            }
            throw new HttpMethodNotAllowedException($request, $methods);
        }

        throw new HttpNotFoundException($request);
    }
}
