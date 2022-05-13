<?php

declare(strict_types=1);

namespace Semperton\Framework\Interfaces;

use Psr\Http\Message\ServerRequestInterface;

interface ServerRequestCreatorInterface
{
	public function createServerRequest(): ServerRequestInterface;
}
