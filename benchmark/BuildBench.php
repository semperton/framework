<?php

declare(strict_types=1);

namespace Semperton\Framework\Benchmark;

use PhpBench\Attributes as Bench;

/**
 * Paid on every request with PHP-FPM
 */
#[Bench\Warmup(2)]
#[Bench\Revs(50)]
#[Bench\Iterations(10)]
#[Bench\OutputTimeUnit('microseconds', precision: 1)]
final class BuildBench
{
    /**
     * @param array{resources: positive-int} $params
     */
    #[Bench\ParamProviders('provideResources')]
    public function benchBuild(array $params): void
    {
        BenchApp::builder($params['resources'])->build();
    }

    /**
     * @return iterable<string, array{resources: positive-int}>
     */
    public function provideResources(): iterable
    {
        yield '60 routes' => ['resources' => 10];
        yield '300 routes' => ['resources' => 50];
        yield '1200 routes' => ['resources' => 200];
    }
}
