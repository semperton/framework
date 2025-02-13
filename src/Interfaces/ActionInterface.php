<?php

declare(strict_types=1);

namespace Semperton\Framework\Interfaces;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

interface ActionInterface
{
    /**
     * @param array<string, string> $args
     */
    public function process(ServerRequestInterface $request, array $args): ResponseInterface;
}
