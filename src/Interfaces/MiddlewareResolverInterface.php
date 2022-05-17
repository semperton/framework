<?php

declare(strict_types=1);

namespace Semperton\Framework\Interfaces;

use Psr\Http\Server\MiddlewareInterface;

interface MiddlewareResolverInterface
{
	/**
	 * @param mixed $middleware
	 */
	public function resolveMiddleware($middleware): MiddlewareInterface;
}
