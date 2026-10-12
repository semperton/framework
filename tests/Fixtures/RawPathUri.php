<?php

declare(strict_types=1);

namespace Semperton\Framework\Tests\Fixtures;

use Nyholm\Psr7\Uri;
use Psr\Http\Message\UriInterface;

/**
 * Returns the path as given, nyholm/psr7 reduces leading slashes, other implementations (e.g. guzzle) don't
 */
final class RawPathUri implements UriInterface
{
    private readonly Uri $uri;

    public function __construct(
        string $uri,
        private readonly string $path,
    ) {
        $this->uri = (new Uri($uri))->withPath($path);
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function withPath(string $path): UriInterface
    {
        return new self((string) $this->uri->withPath('')->withQuery('')->withFragment(''), $path);
    }

    public function getScheme(): string
    {
        return $this->uri->getScheme();
    }

    public function getAuthority(): string
    {
        return $this->uri->getAuthority();
    }

    public function getUserInfo(): string
    {
        return $this->uri->getUserInfo();
    }

    public function getHost(): string
    {
        return $this->uri->getHost();
    }

    public function getPort(): ?int
    {
        return $this->uri->getPort();
    }

    public function getQuery(): string
    {
        return $this->uri->getQuery();
    }

    public function getFragment(): string
    {
        return $this->uri->getFragment();
    }

    public function withScheme(string $scheme): UriInterface
    {
        return $this->uri->withScheme($scheme);
    }

    public function withUserInfo(string $user, ?string $password = null): UriInterface
    {
        return $this->uri->withUserInfo($user, $password);
    }

    public function withHost(string $host): UriInterface
    {
        return $this->uri->withHost($host);
    }

    public function withPort(?int $port): UriInterface
    {
        return $this->uri->withPort($port);
    }

    public function withQuery(string $query): UriInterface
    {
        return $this->uri->withQuery($query);
    }

    public function withFragment(string $fragment): UriInterface
    {
        return $this->uri->withFragment($fragment);
    }

    public function __toString(): string
    {
        return (string) $this->uri;
    }
}
