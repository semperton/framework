<?php

declare(strict_types=1);

namespace Semperton\Framework;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use UnexpectedValueException;

/**
 * Resolves a handler or middleware from the container on use
 */
final class LazyHandler implements RequestHandlerInterface, MiddlewareInterface
{
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly string $id,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $handler = $this->container->get($this->id);

        if (!($handler instanceof RequestHandlerInterface)) {
            throw new UnexpectedValueException("Entry < {$this->id} > is not a RequestHandlerInterface");
        }

        return $handler->handle($request);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $middleware = $this->container->get($this->id);

        if (!($middleware instanceof MiddlewareInterface)) {
            throw new UnexpectedValueException("Entry < {$this->id} > is not a MiddlewareInterface");
        }

        return $middleware->process($request, $handler);
    }
}
