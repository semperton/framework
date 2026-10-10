<?php

declare(strict_types=1);

namespace Semperton\Framework\Benchmark;

use Nyholm\Psr7\ServerRequest;
use PhpBench\Attributes as Bench;
use Semperton\Framework\Application;

/**
 * Paid on every request, 300 routes
 */
#[Bench\Warmup(2)]
#[Bench\Revs(5000)]
#[Bench\Iterations(10)]
#[Bench\OutputTimeUnit('microseconds', precision: 2)]
#[Bench\BeforeMethods('setUp')]
final class DispatchBench
{
    private Application $app;

    public function setUp(): void
    {
        $this->app = BenchApp::builder(50)->build();
    }

    public function benchStatic(): void
    {
        $this->app->handle(new ServerRequest('GET', '/api/resource25'));
    }

    public function benchDynamic(): void
    {
        $this->app->handle(new ServerRequest('GET', '/api/resource25/42/items/abc'));
    }

    public function benchRouteMiddleware(): void
    {
        $this->app->handle(new ServerRequest('PUT', '/api/resource25/42'));
    }

    public function benchNotFound(): void
    {
        $this->app->handle(new ServerRequest('GET', '/api/missing/42'));
    }

    public function benchMethodNotAllowed(): void
    {
        $this->app->handle(new ServerRequest('PATCH', '/api/resource25/42'));
    }
}
