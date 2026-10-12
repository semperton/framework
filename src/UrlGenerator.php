<?php

declare(strict_types=1);

namespace Semperton\Framework;

use Psr\Http\Message\UriInterface;
use Semperton\Routing\Collection\RouteCollection;

use function http_build_query;

use const PHP_QUERY_RFC3986;

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
     * The path including the base path, e.g. '/blog/42?page=2'
     *
     * @param array<string, scalar> $params decoded values, they get percent-encoded
     * @param array<array-key, mixed> $query decoded values, they get percent-encoded
     */
    public function path(string $name, array $params = [], array $query = []): string
    {
        $path = $this->routePath($name, $params);

        return $query === [] ? $path : $path . '?' . $this->queryString($query);
    }

    /**
     * The absolute url, scheme, host and port are taken from the base uri, e.g. $request->getUri()
     * User info and fragment of the base uri are removed
     *
     * @param array<string, scalar> $params decoded values, they get percent-encoded
     * @param array<array-key, mixed> $query decoded values, they get percent-encoded
     */
    public function url(UriInterface $baseUri, string $name, array $params = [], array $query = []): string
    {
        return (string) $baseUri
            ->withPath($this->routePath($name, $params))
            ->withQuery($query === [] ? '' : $this->queryString($query))
            ->withFragment('')
            ->withUserInfo('');
    }

    /**
     * @param array<string, scalar> $params
     */
    private function routePath(string $name, array $params): string
    {
        return $this->basePath . $this->routeCollection->reverse($name, $params);
    }

    /**
     * @param array<array-key, mixed> $query
     */
    private function queryString(array $query): string
    {
        return http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }
}
