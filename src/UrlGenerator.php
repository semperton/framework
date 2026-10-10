<?php

declare(strict_types=1);

namespace Semperton\Framework;

use Semperton\Routing\Collection\RouteCollection;

/**
 * Builds the paths of named routes, get it from ApplicationBuilder::getUrlGenerator()
 */
final class UrlGenerator
{
    /**
     * @internal
     */
    public function __construct(
        private readonly RouteCollection $routeCollection,
        private readonly string $basePath = '',
    ) {}

    /**
     * The path including the base path
     *
     * @param array<string, scalar> $params decoded values, they get percent-encoded
     */
    public function path(string $name, array $params = []): string
    {
        return $this->basePath . $this->routeCollection->reverse($name, $params);
    }
}
