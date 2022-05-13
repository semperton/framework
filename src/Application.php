<?php

declare(strict_types=1);

namespace Semperton\Framework;

use ArrayIterator;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Semperton\Framework\Interfaces\ActionResolverInterface;
use Semperton\Framework\Interfaces\MiddlewareResolverInterface;
use Semperton\Framework\Interfaces\ServerRequestCreatorInterface;
use Semperton\Porter\Emitter\EmitterInterface;
use Semperton\Porter\RequestHandler;
use Semperton\Porter\RequestRunner;

final class Application implements RequestHandlerInterface
{
	/** @var array<int, string|callable|MiddlewareInterface> */
	protected array $middleware = [];

	protected ServerRequestCreatorInterface $serverRequestCreator;

	protected ResponseFactoryInterface $responseFactory;

	protected MiddlewareResolverInterface $middlewareResolver;

	protected ActionResolverInterface $actionResolver;

	protected EmitterInterface $responseEmitter;

	public function __construct(
		ServerRequestCreatorInterface $serverRequestCreator,
		ResponseFactoryInterface $responseFactory,
		MiddlewareResolverInterface $middlewareResolver,
		ActionResolverInterface $actionResolver,
		EmitterInterface $responseEmitter
	) {
		$this->serverRequestCreator = $serverRequestCreator;
		$this->responseFactory = $responseFactory;
		$this->middlewareResolver = $middlewareResolver;
		$this->actionResolver = $actionResolver;
		$this->responseEmitter = $responseEmitter;
	}

	public function run(): void
	{
		$requestRunner = new RequestRunner(
			[$this->serverRequestCreator, 'createServerRequest'],
			$this,
			$this->responseEmitter
		);

		$requestRunner->run();
	}

	public function handle(ServerRequestInterface $request): ResponseInterface
	{
		$middleware = new ArrayIterator($this->middleware);

		$requestHandler = new RequestHandler($middleware, [$this->middlewareResolver, 'resolve']);

		return $requestHandler->handle($request);
	}
}
