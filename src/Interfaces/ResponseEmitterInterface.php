<?php

declare(strict_types=1);

namespace Semperton\Framework\Interfaces;

use Psr\Http\Message\ResponseInterface;

interface ResponseEmitterInterface
{
    public function emit(ResponseInterface $response): void;
}
