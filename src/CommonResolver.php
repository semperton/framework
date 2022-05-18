<?php

declare(strict_types=1);

namespace Semperton\Framework;

use Psr\Container\ContainerInterface;
use Psr\Http\Server\MiddlewareInterface;
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
		if ($action instanceof ActionInterface) {
			return $action;
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
		if ($middleware instanceof MiddlewareInterface) {
			return $middleware;
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
