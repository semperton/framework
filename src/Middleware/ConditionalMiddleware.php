<?php

declare(strict_types=1);

namespace Semperton\Framework\Middleware;

use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Semperton\Framework\Interfaces\MiddlewareDispatcherInterface;
use Semperton\Framework\Routing\RouteObject;

final class ConditionalMiddleware implements MiddlewareInterface
{
	protected MiddlewareDispatcherInterface $middlewareDispatcher;

	public function __construct(MiddlewareDispatcherInterface $middlewareDispatcher)
	{
		$this->middlewareDispatcher = $middlewareDispatcher;
	}

	public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
	{
		/** @var mixed */
		$routeObject = $request->getAttribute('_route_object');

		if ($routeObject instanceof RouteObject && !!$middleware = $routeObject->getMiddleware()) {

			$this->middlewareDispatcher->addMiddleware(...$middleware);

			return $this->middlewareDispatcher->handle($request);
		}

		return $handler->handle($request);
	}
}
