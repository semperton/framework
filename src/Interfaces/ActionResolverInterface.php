<?php

declare(strict_types=1);

namespace Semperton\Framework\Interfaces;

interface ActionResolverInterface
{
	public function resolve(string $action): ActionInterface;
}
