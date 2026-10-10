<?php

declare(strict_types=1);

namespace Semperton\Framework\Middleware;

use LogicException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Semperton\Framework\Route;
use Semperton\Framework\RouteResult;
use Semperton\Routing\Matcher\PathMatcherInterface;

use function get_debug_type;
use function in_array;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * Adds the RouteResult as request attribute
 *
 * @internal added by the ApplicationBuilder
 */
final class RoutingMiddleware implements MiddlewareInterface
{
    /**
     * @param string $basePath normalized, e.g. '/app' or ''
     */
    public function __construct(
        private readonly PathMatcherInterface $pathMatcher,
        private readonly string $basePath = '',
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $handler->handle($request->withAttribute(RouteResult::ATTRIBUTE, $this->route($request)));
    }

    private function route(ServerRequestInterface $request): RouteResult
    {
        $path = $request->getUri()->getPath();

        if ($this->basePath !== '') {
            if ($path === $this->basePath) {
                $path = '/';
            } elseif (str_starts_with($path, $this->basePath . '/')) {
                $path = substr($path, strlen($this->basePath));
            } else {
                return new RouteResult(null);
            }
        } elseif ($path === '') {
            $path = '/'; // equivalent (RFC 3986)
        }

        $matchResult = $this->pathMatcher->match($request->getMethod(), $path);

        if ($matchResult->isMatch()) {
            /** @var mixed */
            $route = $matchResult->getHandler();

            if (!($route instanceof Route)) {
                throw new LogicException('Matched handler must be a Route, got < ' . get_debug_type($route) . ' >');
            }

            return new RouteResult($route, $matchResult->getParams());
        }

        $methods = $matchResult->getMethods();

        if ($methods !== []) {
            // GET routes also serve HEAD requests, OPTIONS is answered by the EndpointHandler
            if (in_array('GET', $methods, true) && !in_array('HEAD', $methods, true)) {
                $methods[] = 'HEAD';
            }
            if (!in_array('OPTIONS', $methods, true)) {
                $methods[] = 'OPTIONS';
            }
        }

        return new RouteResult(null, [], $methods);
    }
}
