<?php

declare(strict_types=1);

namespace Semperton\Framework\Middleware;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function preg_replace_callback;
use function rawurlencode;
use function str_ends_with;
use function trim;

/**
 * Removes trailing slashes from request paths, routes are defined without them
 * Redirects to the canonical path by default, or rewrites the request path
 */
final class TrailingSlashMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly bool $redirect = true,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $uri = $request->getUri();
        $path = $uri->getPath();

        if ($path === '/' || !str_ends_with($path, '/')) {
            return $handler->handle($request);
        }

        // leading slashes are collapsed too, '//host/' would redirect to another host otherwise
        $path = '/' . trim($path, '/');

        if (!$this->redirect) {
            return $handler->handle($request->withUri($uri->withPath($path)));
        }

        $location = self::encode($path, '/');
        $query = self::encode($uri->getQuery(), '/?');
        $method = $request->getMethod();

        // 308 keeps the method and body of non-safe requests, not every factory knows its reason phrase
        [$status, $reasonPhrase] = $method === 'GET' || $method === 'HEAD'
            ? [301, 'Moved Permanently']
            : [308, 'Permanent Redirect'];

        return $this->responseFactory->createResponse($status, $reasonPhrase)
            ->withHeader('Location', $query === '' ? $location : "$location?$query");
    }

    /**
     * Percent-encodes what is invalid in a uri (rfc 3986 3.3 and 3.4), some PSR-7 implementations keep it,
     * e.g. browsers read '/\host' as '//host', and control characters would break the header
     */
    private static function encode(string $value, string $allowed): string
    {
        return (string) preg_replace_callback(
            '~[^A-Za-z0-9\-._\~!$&\'()*+,;=:@%' . $allowed . ']~',
            static fn(array $matches): string => rawurlencode($matches[0]),
            $value,
        );
    }
}
