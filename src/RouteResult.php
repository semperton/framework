<?php

declare(strict_types=1);

namespace Semperton\Framework;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Result of the routing, added to the request by the routing middleware
 */
final class RouteResult
{
    public const ATTRIBUTE = self::class;

    /**
     * @internal
     * @param array<string, string> $params decoded route params
     * @param list<string> $allowedMethods methods of the path, if the route did not match
     */
    public function __construct(
        public readonly ?Route $route,
        public readonly array $params = [],
        public readonly array $allowedMethods = [],
    ) {}

    /**
     * The routing result, null if the routing did not run yet
     */
    public static function fromRequest(ServerRequestInterface $request): ?self
    {
        /** @var mixed */
        $result = $request->getAttribute(self::ATTRIBUTE);

        return $result instanceof self ? $result : null;
    }

    /**
     * The decoded route param
     */
    public function param(string $name, ?string $default = null): ?string
    {
        return $this->params[$name] ?? $default;
    }
}
