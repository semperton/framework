<?php

declare(strict_types=1);

namespace Semperton\Framework;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Semperton\Framework\Interfaces\ActionInterface;
use Semperton\Framework\Interfaces\CommonResolverInterface;

use function gettype;
use function is_callable;

final class CommonResolver implements CommonResolverInterface
{
    protected ResponseFactoryInterface $responseFactory;

    protected ?ContainerInterface $container;

    public function __construct(
        ResponseFactoryInterface $responseFactory,
        ?ContainerInterface $container = null
    ) {
        $this->responseFactory = $responseFactory;
        $this->container = $container;
    }

    public function resolveAction($action): ActionInterface
    {
        if (is_callable($action)) {
            return $this->buildAction($action);
        }

        /** @var mixed */
        $object = $this->containerGet($action);

        if ($object instanceof ActionInterface) {
            return $object;
        }

        $type = gettype($action);
        throw new RuntimeException("Type < $type > is not a valid Action");
    }

    public function resolveMiddleware($middleware): MiddlewareInterface
    {
        if (is_callable($middleware)) {
            return $this->buildMiddleware($middleware);
        }

        /** @var mixed */
        $object = $this->containerGet($middleware);

        if ($object instanceof MiddlewareInterface) {
            return $object;
        }

        $type = gettype($middleware);
        throw new RuntimeException("Type < $type > is not a valid Middleware");
    }

    /**
     * @return null|mixed
     */
    protected function containerGet(string $id)
    {
        if ($this->container && $this->container->has($id)) {

            return $this->container->get($id);
        }

        return null;
    }

    protected function buildMiddleware(callable $middleware): MiddlewareInterface
    {
        $responseFactory = $this->responseFactory;

        return new class($responseFactory, $middleware) implements MiddlewareInterface
        {
            protected ResponseFactoryInterface $responseFactory;

            /** @var callable */
            protected $middleware;

            public function __construct(ResponseFactoryInterface $responseFactory, callable $middleware)
            {
                $this->responseFactory = $responseFactory;
                $this->middleware = $middleware;
            }

            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                $response = $this->responseFactory->createResponse();

                $middlewareResponse = ($this->middleware)($request, $response, $handler);

                if (!($middlewareResponse instanceof ResponseInterface)) {
                    throw new RuntimeException('Middleware callable did not return a valid response');
                }

                return $middlewareResponse;
            }
        };
    }

    protected function buildAction(callable $action): ActionInterface
    {
        $responseFactory = $this->responseFactory;

        return new class($responseFactory, $action) implements ActionInterface
        {
            protected ResponseFactoryInterface $responseFactory;

            /** @var callable */
            protected $action;

            public function __construct(ResponseFactoryInterface $responseFactory, callable $action)
            {
                $this->responseFactory = $responseFactory;
                $this->action = $action;
            }

            public function process(ServerRequestInterface $request, array $args): ResponseInterface
            {
                $response = $this->responseFactory->createResponse();

                $actionResponse = ($this->action)($request, $response, $args);

                if (!($actionResponse instanceof ResponseInterface)) {
                    throw new RuntimeException('Action callable did not return a valid response');
                }

                return $actionResponse;
            }
        };
    }
}
