<?php

declare(strict_types=1);

namespace Semperton\Framework;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Semperton\Framework\Interfaces\ActionInterface;
use Semperton\Framework\Interfaces\ActionResolverInterface;
use Semperton\Framework\Interfaces\MiddlewareResolverInterface;

final class CommonResolver implements ActionResolverInterface, MiddlewareResolverInterface
{
	protected ?ContainerInterface $container;

	public function __construct(?ContainerInterface $container = null)
	{
		$this->container = $container;
	}

	public function resolveAction($action): ActionInterface
	{
		if (is_callable($action)) {

			return new class($action) implements ActionInterface
			{
				protected $action;

				public function __construct(callable $action)
				{
					$this->action = $action;
				}
				public function process(ServerRequestInterface $request, array $args): ResponseInterface
				{
					return ($this->action)($request, $args);
				}
			};
		}

		if (is_string($action)) {

			if ($this->container && $this->container->has($action)) {
				$entry = $this->container->get($action);

				if ($entry instanceof ActionInterface) {
					return $entry;
				}
			}
		}

		$type = gettype($action);
		throw new RuntimeException("Unable to resolve Action of type < $type >");
	}

	public function resolveMiddleware($middleware): MiddlewareInterface
	{
		if (is_callable($middleware)) {

			return new class($middleware) implements MiddlewareInterface
			{
				protected $middleware;

				public function __construct(callable $middleware)
				{
					$this->middleware = $middleware;
				}
				public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
				{
					return ($this->middleware)($request, $handler);
				}
			};
		}

		if (is_string($middleware)) {

			if ($this->container && $this->container->has($middleware)) {
				$entry = $this->container->get($middleware);

				if ($entry instanceof MiddlewareInterface) {
					return $entry;
				}
			}
		}

		$type = gettype($middleware);
		throw new RuntimeException("Unable to resolve Middleware of type < $type >");
	}
}
