<?php

declare(strict_types=1);

namespace Semperton\Framework\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Semperton\Framework\Interfaces\ErrorHandlerInterface;
use Throwable;

final class ErrorMiddleware implements MiddlewareInterface
{
    protected ErrorHandlerInterface $errorHandler;

    public function __construct(ErrorHandlerInterface $errorHandler)
    {
        $this->errorHandler = $errorHandler;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (Throwable $exception) {
            return $this->errorHandler->handle($request, $exception);
        }
    }
}
