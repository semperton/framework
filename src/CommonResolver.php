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

use function is_callable;
use function gettype;

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
			return $this->buildAction($action);
		}

		/** @var mixed */
		$object = $this->containerGet($action);

		if ($object instanceof ActionInterface) {
			return $object;
		}

		$type = gettype($action);
		throw new RuntimeException("Unable to resolve Action of type < $type >");
	}

	public function resolveMiddleware($middleware): MiddlewareInterface
	{
		if (is_callable($middleware)) {
			return $this->buildMiddleware($middleware);
		}

		/** @var mixed */
		$object = $this->containerGet($middleware);

		if ($object instanceof MiddlewareInterface) {
			return $object;
		}

		$type = gettype($middleware);
		throw new RuntimeException("Unable to resolve Middleware of type < $type >");
	}

	/**
	 * @return mixed
	 */
	protected function containerGet(string $id)
	{
		if ($this->container && $this->container->has($id)) {

			return $this->container->get($id);
		}

		return null;
	}

	protected function buildMiddleware(callable $middleware): MiddlewareInterface
	{
		return new class($middleware) implements MiddlewareInterface
		{
			protected $middleware;

			public function __construct(callable $middleware)
			{
				$this->middleware = $middleware;
			}
			public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
			{
				$response = ($this->middleware)($request, $handler);

				if (!($response instanceof ResponseInterface)) {
					throw new RuntimeException('Middleware callable did not return a valid response');
				}

				return $response;
			}
		};
	}

	protected function buildAction(callable $action): ActionInterface
	{
		return new class($action) implements ActionInterface
		{
			protected $action;

			public function __construct(callable $action)
			{
				$this->action = $action;
			}
			public function process(ServerRequestInterface $request, array $args): ResponseInterface
			{
				$response = ($this->action)($request, $args);

				if (!($response instanceof ResponseInterface)) {
					throw new RuntimeException('Action callable did not return a valid response');
				}

				return $response;
			}
		};
	}
}
