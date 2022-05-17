<?php

declare(strict_types=1);

namespace Semperton\Framework\Handler;

use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use OutOfBoundsException;
use RuntimeException;
use Iterator;

use function is_callable;
use function gettype;

final class RequestHandler implements RequestHandlerInterface, MiddlewareInterface
{
	/** @var Iterator */
	protected $middleware;

	/** @var null|callable */
	protected $resolver;

	/** @var null|RequestHandlerInterface */
	protected $delegate;

	public function __construct(
		Iterator $middleware,
		?callable $resolver = null,
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

		/** @var null|mixed */
		$middleware = $this->middleware->current();

		$this->middleware->next();

		if ($this->resolver) {

			/** @var mixed */
			$middleware = ($this->resolver)($middleware);
		}

		if ($middleware instanceof MiddlewareInterface) {

			return $middleware->process($request, $this);
		}

		if ($middleware instanceof RequestHandlerInterface) {

			return $middleware->handle($request);
		}

		if (is_callable($middleware)) {

			/** @var ResponseInterface */
			return $middleware($request, $this);
		}

		$type = gettype($middleware);
		throw new RuntimeException("Unable to resolve middleware, < $type > is not a valid Middleware");
	}

	public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
	{
		return (new self($this->middleware, $this->resolver, $handler))->handle($request);
	}
}
