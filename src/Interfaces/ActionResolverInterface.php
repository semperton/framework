<?php

declare(strict_types=1);

namespace Semperton\Framework\Interfaces;

interface ActionResolverInterface
{
	/**
	 * @param string|callable $action
	 */
	public function resolveAction($action): ActionInterface;
}
