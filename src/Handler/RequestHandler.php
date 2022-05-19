<?php

declare(strict_types=1);

namespace Semperton\Framework\Handler;

use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Semperton\Framework\Interfaces\MiddlewareResolverInterface;
use OutOfBoundsException;
use Iterator;

final class RequestHandler implements RequestHandlerInterface
{
	protected Iterator $middleware;

	protected MiddlewareResolverInterface $resolver;

	protected ?RequestHandlerInterface $delegate;

	public function __construct(
		Iterator $middleware,
		MiddlewareResolverInterface $resolver,
		?RequestHandlerInterface $delegate = null
	) {
		$this->middleware = $middleware;
		$this->resolver = $resolver;
		$this->delegate = $delegate;
	}

	public function handle(ServerRequestInterface $request): ResponseInterface
	{
		if (!$this->middleware->valid()) {

			if ($this->delegate) {
				return $this->delegate->handle($request);
			}

			throw new OutOfBoundsException('End of middleware stack, no response was returned');
		}

		/** @var string|callable|MiddlewareInterface */
		$middleware = $this->middleware->current();

		$this->middleware->next();

		if (!($middleware instanceof MiddlewareInterface)) {

			$middleware = $this->resolver->resolveMiddleware($middleware);
		}

		return $middleware->process($request, $this);
	}
}
