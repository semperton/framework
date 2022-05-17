<?php

declare(strict_types=1);

namespace Semperton\Framework\Interfaces;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

interface ResponderInterface
{
	public function render(ServerRequestInterface $request, PayloadInterface $payload): ResponseInterface;
}
