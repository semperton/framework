<?php

declare(strict_types=1);

namespace Semperton\Framework\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Semperton\Framework\Interfaces\ActionInterface;
use Semperton\Framework\Interfaces\CommonResolverInterface;
use Semperton\Framework\Routing\RouteObject;

final class ActionMiddleware implements MiddlewareInterface
{
	protected CommonResolverInterface $commonResolver;

	public function __construct(CommonResolverInterface $commonResolver)
	{
		$this->commonResolver = $commonResolver;
	}

	public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
	{
		/** @var RouteObject */
		$routeObject = $request->getAttribute('_route_object');

		/** @var array<string, string> */
		$args = $request->getAttribute('_route_params');

		$action = $routeObject->getAction();

		if (!($action instanceof ActionInterface)) {

			$action = $this->commonResolver->resolveAction($action);
		}

		return $action->process($request, $args);
	}
}
