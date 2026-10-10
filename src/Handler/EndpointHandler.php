<?php

declare(strict_types=1);

namespace Semperton\Framework\Handler;

use LogicException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Semperton\Framework\Exception\HttpMethodNotAllowedException;
use Semperton\Framework\Exception\HttpNotFoundException;
use Semperton\Framework\RouteResult;

use function implode;

/**
 * Last handler of the application, dispatches the matched route
 * Answers OPTIONS requests for paths without an OPTIONS route
 *
 * @internal used by the ApplicationBuilder
 */
final class EndpointHandler implements RequestHandlerInterface
{
    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $result = RouteResult::fromRequest($request)
            ?? throw new LogicException('No route result found, add the RoutingMiddleware');

        if ($result->route !== null) {
            return $result->route->handler->handle($request);
        }

        if ($result->allowedMethods !== []) {
            if ($request->getMethod() === 'OPTIONS') {
                return $this->responseFactory->createResponse(204)
                    ->withHeader('Allow', implode(', ', $result->allowedMethods));
            }

            throw new HttpMethodNotAllowedException($result->allowedMethods);
        }

        throw new HttpNotFoundException();
    }
}
