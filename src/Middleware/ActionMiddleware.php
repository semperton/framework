<?php

declare(strict_types=1);

namespace Semperton\Framework\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Semperton\Framework\Interfaces\ActionResolverInterface;

final class ActionMiddleware implements MiddlewareInterface
{
	protected ActionResolverInterface $resolver;

	public function __construct(ActionResolverInterface $resolver)
	{
		$this->resolver = $resolver;
	}

	public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
	{
		/** @var string */
		$handler = $request->getAttribute('_route_handler');

		/** @var array<string, string> */
		$args = $request->getAttribute('_route_params');

		$action = $this->resolver->resolve($handler);

		return $action->process($request, $args);
	}
}
