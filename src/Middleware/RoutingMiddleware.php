<?php

declare(strict_types=1);

namespace Semperton\Framework\Middleware;

use Semperton\Framework\Exception\HttpMethodNotAllowedException;
use Semperton\Framework\Exception\HttpNotFoundException;
use Semperton\Framework\Interfaces\RouteMatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class RoutingMiddleware implements MiddlewareInterface
{
	protected RouteMatcherInterface $routeMatcher;

	public function __construct(RouteMatcherInterface $routeMatcher)
	{
		$this->routeMatcher = $routeMatcher;
	}

	public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
	{
		$matchResult = $this->routeMatcher->matchRequest($request);

		if ($matchResult->isMatch()) {

			$request = $request->withAttribute('_route_object', $matchResult->getHandler())
				->withAttribute('_route_params', $matchResult->getParams());

			return $handler->handle($request);
		}

		$methods = $matchResult->getMethods();

		// GET, HEAD requests must not respond with a 405 status
		if (!empty($methods) && !in_array($request->getMethod(), ['GET', 'HEAD'])) {

			$exception = new HttpMethodNotAllowedException($request);
			$exception->setAllowedMethods($methods);
			throw $exception;
		}

		throw new HttpNotFoundException($request);
	}
}
