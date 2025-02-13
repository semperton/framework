<?php

declare(strict_types=1);

namespace Semperton\Framework;

use ArrayIterator;
use OutOfBoundsException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Semperton\Framework\Interfaces\CommonResolverInterface;

final class MiddlewareDispatcher implements RequestHandlerInterface
{
    protected ArrayIterator $middleware;

    protected CommonResolverInterface $commonResolver;

    protected ?RequestHandlerInterface $delegateHandler;

    /**
     * @param array<string|callable|MiddlewareInterface> $middleware
     */
    public function __construct(
        array $middleware,
        CommonResolverInterface $commonResolver,
        ?RequestHandlerInterface $delegateHandler = null
    ) {
        $this->middleware = new ArrayIterator($middleware);
        $this->commonResolver = $commonResolver;
        $this->delegateHandler = $delegateHandler;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->middleware->valid()) {

            if ($this->delegateHandler) {
                return $this->delegateHandler->handle($request);
            }

            throw new OutOfBoundsException('End of middleware stack, no response was returned');
        }

        /** @var string|callable|MiddlewareInterface */
        $middleware = $this->middleware->current();

        $this->middleware->next();

        if (!($middleware instanceof MiddlewareInterface)) {

            $middleware = $this->commonResolver->resolveMiddleware($middleware);
        }

        return $middleware->process($request, $this);
    }
}
