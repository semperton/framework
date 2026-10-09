<?php

declare(strict_types=1);

namespace Semperton\Framework\Interfaces;

use Psr\Http\Server\RequestHandlerInterface;

interface ApplicationBuilderInterface
{
    public function build(): RequestHandlerInterface;
}
