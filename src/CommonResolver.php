<?php

declare(strict_types=1);

namespace Semperton\Framework;

use Psr\Http\Server\MiddlewareInterface;
use Semperton\Framework\Interfaces\ActionInterface;
use Semperton\Framework\Interfaces\ActionResolverInterface;
use Semperton\Framework\Interfaces\MiddlewareResolverInterface;

final class CommonResolver implements ActionResolverInterface, MiddlewareResolverInterface
{
	public function resolveAction($action): ActionInterface
	{
		if ($action instanceof ActionInterface) {
			return $action;
		}
	}

	public function resolveMiddleware($middleware): MiddlewareInterface
	{
		if ($middleware instanceof MiddlewareInterface) {
			return $middleware;
		}
	}
}
