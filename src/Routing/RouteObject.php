<?php

declare(strict_types=1);

namespace Semperton\Framework\Routing;

final class RouteObject
{
	/** @var mixed */
	protected $handler;

	/** @var array<int, string|callable|MiddlewareInterface> */
	protected array $middleware;

	public function __construct($handler, array $middleware)
	{
		$this->handler = $handler;
		$this->middleware = $middleware;
	}

	/**
	 * @return mixed
	 */
	public function getHandler()
	{
		return $this->handler;
	}

	/**
	 * @return array<int, string|callable|MiddlewareInterface>
	 */
	public function getMiddleware(): array
	{
		return $this->middleware;
	}
}
