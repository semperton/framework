<?php

declare(strict_types=1);

namespace Semperton\Framework;

use ArrayIterator;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use OutOfBoundsException;
use Semperton\Framework\Interfaces\CommonResolverInterface;
use Semperton\Framework\Interfaces\MiddlewareDispatcherInterface;

use function array_map;

final class MiddlewareDispatcher implements MiddlewareDispatcherInterface
{
	protected ArrayIterator $middleware;

	protected CommonResolverInterface $commonResolver;

	protected ?RequestHandlerInterface $delegateHandler;

	public function __construct(
		CommonResolverInterface $commonResolver,
		?RequestHandlerInterface $delegateHandler = null
	) {
		$this->middleware = new ArrayIterator();
		$this->commonResolver = $commonResolver;
		$this->delegateHandler = $delegateHandler;
	}

	public function addMiddleware(...$middleware): MiddlewareDispatcherInterface
	{
		array_map([$this->middleware, 'append'], $middleware);
		return $this;
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
