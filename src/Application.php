<?php

declare(strict_types=1);

namespace Semperton\Framework;

use ArrayIterator;
use Closure;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Semperton\Framework\Handler\ErrorHandler;
use Semperton\Framework\Handler\RequestHandler;
use Semperton\Framework\Interfaces\ActionResolverInterface;
use Semperton\Framework\Interfaces\MiddlewareResolverInterface;
use Semperton\Framework\Interfaces\ResponseEmitterInterface;
use Semperton\Framework\Interfaces\RouteCollectorInterface;
use Semperton\Framework\Middleware\ActionMiddleware;
use Semperton\Framework\Middleware\ConditionalMiddleware;
use Semperton\Framework\Middleware\ErrorMiddleware;
use Semperton\Framework\Middleware\RoutingMiddleware;
use Semperton\Framework\Routing\RouteCollector;
use Semperton\Routing\RouteMatcher;

final class Application implements RequestHandlerInterface, RouteCollectorInterface
{
	/** @var array<int, string|callable|MiddlewareInterface> */
	protected array $middleware = [];

	protected ResponseFactoryInterface $responseFactory;

	protected MiddlewareResolverInterface $middlewareResolver;

	protected ActionResolverInterface $actionResolver;

	protected ResponseEmitterInterface $responseEmitter;

	protected RouteCollector $routeCollector;

	public function __construct(
		ResponseFactoryInterface $responseFactory,
		?MiddlewareResolverInterface $middlewareResolver = null,
		?ActionResolverInterface $actionResolver = null,
		?ResponseEmitterInterface $responseEmitter = null
	) {
		$this->responseFactory = $responseFactory;
		$this->responseEmitter = $responseEmitter ?? new ResponseEmitter();

		$this->actionResolver = $actionResolver ?? new CommonResolver();
		$this->middlewareResolver = $middlewareResolver ??
			($this->actionResolver instanceof CommonResolver ? $this->actionResolver : new CommonResolver());


		$this->routeCollector = new RouteCollector();
	}

	/**
	 * @param string|callable|MiddlewareInterface $middleware
	 */
	public function addMiddleware($middleware): self
	{
		$this->middleware[] = $middleware;
		return $this;
	}

	public function addErrorMiddleware(): ErrorMiddleware
	{
		$errorHandler = new ErrorHandler($this->responseFactory);
		$errorMiddleware = new ErrorMiddleware($errorHandler);

		$this->middleware[] = $errorMiddleware;

		return $errorMiddleware;
	}

	public function addRoutingMiddleware(): RoutingMiddleware
	{
		$routeMatcher = new RouteMatcher($this->routeCollector);
		$routingMiddleware = new RoutingMiddleware($routeMatcher);

		$this->middleware[] = $routingMiddleware;

		return $routingMiddleware;
	}

	public function addConditionalMiddleware(): ConditionalMiddleware
	{
		$conditionalMiddleware = new ConditionalMiddleware($this->middlewareResolver);

		$this->middleware[] = $conditionalMiddleware;

		return $conditionalMiddleware;
	}

	public function addActionMiddleware(): ActionMiddleware
	{
		$actionMiddleware = new ActionMiddleware($this->actionResolver);

		$this->middleware[] = $actionMiddleware;

		return $actionMiddleware;
	}

	public function run(ServerRequestInterface $request): void
	{
		$response = $this->handle($request);

		$this->responseEmitter->emit($response);
	}

	public function handle(ServerRequestInterface $request): ResponseInterface
	{
		$middleware = new ArrayIterator($this->middleware);
		$requestHandler = new RequestHandler($middleware, $this->middlewareResolver);

		$response = $requestHandler->handle($request);

		if ('HEAD' === strtoupper($request->getMethod())) {

			$emptyBody = $this->responseFactory->createResponse()->getBody();
			$response = $response->withBody($emptyBody);
		}

		return $response;
	}

	public function group(string $path, Closure $callback, array $middleware = []): RouteCollectorInterface
	{
		return $this->routeCollector->group($path, $callback, $middleware);
	}

	public function map(array $methods, string $path, $action, array $middleware = []): RouteCollectorInterface
	{
		return $this->routeCollector->map($methods, $path, $action, $middleware);
	}

	public function get(string $path, $action, array $middleware = []): RouteCollectorInterface
	{
		return $this->routeCollector->get($path, $action, $middleware);
	}

	public function post(string $path, $action, array $middleware = []): RouteCollectorInterface
	{
		return $this->routeCollector->post($path, $action, $middleware);
	}

	public function put(string $path, $action, array $middleware = []): RouteCollectorInterface
	{
		return $this->routeCollector->put($path, $action, $middleware);
	}

	public function delete(string $path, $action, array $middleware = []): RouteCollectorInterface
	{
		return $this->routeCollector->delete($path, $action, $middleware);
	}

	public function patch(string $path, $action, array $middleware = []): RouteCollectorInterface
	{
		return $this->routeCollector->patch($path, $action, $middleware);
	}

	public function head(string $path, $action, array $middleware = []): RouteCollectorInterface
	{
		return $this->routeCollector->head($path, $action, $middleware);
	}

	public function options(string $path, $action, array $middleware = []): RouteCollectorInterface
	{
		return $this->routeCollector->options($path, $action, $middleware);
	}
}
