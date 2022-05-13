<?php

declare(strict_types=1);

namespace Semperton\Framework\Interfaces;

use Semperton\Framework\Application;

interface ApplicationFactoryInterface
{
	public function createApplication(): Application;
}
