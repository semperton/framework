<?php

declare(strict_types=1);

namespace Semperton\Framework\Middleware;

use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Semperton\Framework\Interfaces\CommonResolverInterface;
use Semperton\Framework\MiddlewareDispatcher;
use Semperton\Framework\Routing\RouteObject;

final class ConditionalMiddleware implements MiddlewareInterface
{
	protected CommonResolverInterface $commonResolver;

	public function __construct(CommonResolverInterface $commonResolver)
	{
		$this->commonResolver = $commonResolver;
	}

	public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
	{
		/** @var mixed */
		$routeObject = $request->getAttribute('_route_object');

		if ($routeObject instanceof RouteObject && !!$middleware = $routeObject->getMiddleware()) {

			$dispatcher = new MiddlewareDispatcher($this->commonResolver, $handler);
			$dispatcher->addMiddleware(...$middleware);

			return $dispatcher->handle($request);
		}

		return $handler->handle($request);
	}
}
