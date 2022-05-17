<?php

declare(strict_types=1);

namespace Semperton\Framework\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Semperton\Framework\Interfaces\ActionResolverInterface;
use Semperton\Framework\Routing\RouteObject;

final class ActionMiddleware implements MiddlewareInterface
{
	protected ActionResolverInterface $resolver;

	public function __construct(ActionResolverInterface $resolver)
	{
		$this->resolver = $resolver;
	}

	public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
	{
		/** @var RouteObject */
		$routeObject = $request->getAttribute('_route_object');

		/** @var array<string, string> */
		$args = $request->getAttribute('_route_params');

		$action = $this->resolver->resolveAction($routeObject->getHandler());

		return $action->process($request, $args);
	}
}
