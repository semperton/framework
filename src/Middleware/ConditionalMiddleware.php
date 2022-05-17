<?php

declare(strict_types=1);

namespace Semperton\Framework\Middleware;

use ArrayIterator;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Semperton\Framework\Handler\RequestHandler;
use Semperton\Framework\Interfaces\MiddlewareResolverInterface;
use Semperton\Framework\RouteObject;

final class ConditionalMiddleware implements MiddlewareInterface
{
	protected MiddlewareResolverInterface $resolver;

	public function __construct(MiddlewareResolverInterface $resolver)
	{
		$this->resolver = $resolver;
	}

	public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
	{
		/** @var RouteObject */
		$routeObject = $request->getAttribute('_route_object');

		if ($routeObject && !!$middleware = $routeObject->getMiddleware()) {

			$middleware = new ArrayIterator($middleware);
			$delegate = new RequestHandler($middleware, [$this->resolver, 'resolveMiddleware'], $handler);

			return $delegate->handle($request);
		}

		return $handler->handle($request);
	}
}
