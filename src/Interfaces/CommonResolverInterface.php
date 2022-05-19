<?php

declare(strict_types=1);

namespace Semperton\Framework\Interfaces;

use Psr\Http\Server\MiddlewareInterface;

interface CommonResolverInterface
{
	/**
	 * @param string|callable $action
	 */
	public function resolveAction($action): ActionInterface;

	/**
	 * @param string|callable $middleware
	 */
	public function resolveMiddleware($middleware): MiddlewareInterface;
}
