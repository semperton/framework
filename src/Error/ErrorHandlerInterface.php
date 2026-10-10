<?php

declare(strict_types=1);

namespace Semperton\Framework\Error;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

interface ErrorHandlerInterface
{
    public function handle(ServerRequestInterface $request, Throwable $exception): ResponseInterface;
}
