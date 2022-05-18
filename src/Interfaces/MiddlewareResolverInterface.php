<?php

declare(strict_types=1);

namespace Semperton\Framework\Interfaces;

use Psr\Http\Server\MiddlewareInterface;

interface MiddlewareResolverInterface
{
	/**
	 * @param string|callable $middleware
	 */
	public function resolveMiddleware($middleware): MiddlewareInterface;
}
