<?php

declare(strict_types=1);

namespace Semperton\Framework\Interfaces;

use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

interface MiddlewareDispatcherInterface extends RequestHandlerInterface
{
	/**
	 * @param string|callable|MiddlewareInterface $middleware
	 */
	public function addMiddleware(...$middleware): MiddlewareDispatcherInterface;
}
