<?php

declare(strict_types=1);

namespace Semperton\Framework\Interfaces;

interface ActionResolverInterface
{
	/**
	 * @param mixed $action
	 */
	public function resolveAction($action): ActionInterface;
}
