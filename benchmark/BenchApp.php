<?php

declare(strict_types=1);

namespace Semperton\Framework\Benchmark;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Semperton\Framework\ApplicationBuilder;
use Semperton\Framework\RouteCollectorInterface;

/**
 * Builds a typical application with resources, groups and middleware
 */
final class BenchApp
{
    /**
     * @param positive-int $resources each resource maps 6 routes
     */
    public static function builder(int $resources): ApplicationBuilder
    {
        $factory = new Psr17Factory();
        $handler = new class ($factory) implements RequestHandlerInterface {
            public function __construct(private readonly Psr17Factory $factory) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->factory->createResponse();
            }
        };
        $middleware = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return $handler->handle($request);
            }
        };

        $builder = new ApplicationBuilder($factory);
        $builder->addErrorMiddleware();
        $builder->add($middleware);

        $builder->group('/api', function (RouteCollectorInterface $api) use ($resources, $handler, $middleware) {
            for ($i = 0; $i < $resources; $i++) {
                $api->group("/resource$i", function (RouteCollectorInterface $r) use ($i, $handler, $middleware) {
                    $r->get('', $handler, name: 'list');
                    $r->post('', $handler, [$middleware], name: 'create');
                    $r->get('/:id:d', $handler, name: 'show');
                    $r->put('/:id:d', $handler, [$middleware], name: 'update');
                    $r->delete('/:id:d', $handler, [$middleware], name: 'delete');
                    $r->get('/:id:d/items/:item', $handler, name: 'items');
                }, name: "resource$i.");
            }
        }, [$middleware], name: 'api.');

        return $builder;
    }
}
