<?php

declare(strict_types=1);

namespace Semperton\Framework\Routing;

use Psr\Http\Server\MiddlewareInterface;
use Semperton\Framework\Interfaces\ActionInterface;

final class RouteObject
{
	/** @var string|callable|ActionInterface */
	protected $action;

	/** @var array<int, string|callable|MiddlewareInterface> */
	protected array $middleware;

	/**
	 * @param string|callable|ActionInterface $action
	 * @param array<int, string|callable|MiddlewareInterface> $middleware
	 */
	public function __construct($action, array $middleware)
	{
		$this->action = $action;
		$this->middleware = $middleware;
	}

	/**
	 * @return string|callable|ActionInterface
	 */
	public function getAction()
	{
		return $this->action;
	}

	/**
	 * @return array<int, string|callable|MiddlewareInterface>
	 */
	public function getMiddleware(): array
	{
		return $this->middleware;
	}
}
