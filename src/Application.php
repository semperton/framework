<?php

declare(strict_types=1);

namespace Semperton\Framework;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Semperton\Framework\Internal\MiddlewareHandler;

/**
 * Immutable application, build it once and reuse it for every request
 */
final class Application implements RequestHandlerInterface
{
    private readonly RequestHandlerInterface $handler;

    /**
     * @internal use the ApplicationBuilder
     * @param list<MiddlewareInterface> $middleware the first entry is the outermost
     */
    public function __construct(array $middleware, RequestHandlerInterface $endpoint)
    {
        $this->handler = MiddlewareHandler::pipe($middleware, $endpoint);
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->handler->handle($request);
    }
}
