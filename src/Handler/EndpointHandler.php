<?php

declare(strict_types=1);

namespace Semperton\Framework\Handler;

use LogicException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Semperton\Framework\Exception\HttpMethodNotAllowedException;
use Semperton\Framework\Exception\HttpNotFoundException;
use Semperton\Framework\RouteResult;

/**
 * Last handler of the application, dispatches the matched route
 *
 * @internal used by the ApplicationBuilder
 */
final class EndpointHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $result = RouteResult::fromRequest($request)
            ?? throw new LogicException('No route result found, add the RoutingMiddleware');

        if ($result->route !== null) {
            return $result->route->handler->handle($request);
        }

        if ($result->allowedMethods !== []) {
            throw new HttpMethodNotAllowedException($request, $result->allowedMethods);
        }

        throw new HttpNotFoundException($request);
    }
}
