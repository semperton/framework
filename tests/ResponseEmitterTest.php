<?php

declare(strict_types=1);

namespace Semperton\Framework\Tests;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Semperton\Framework\ResponseEmitter;

use function ob_get_clean;
use function ob_start;
use function str_repeat;

/**
 * Headers can't be inspected in the cli, these tests cover the body
 */
final class ResponseEmitterTest extends TestCase
{
    private function emit(ResponseInterface $response, ?ResponseEmitter $emitter = null): string
    {
        ob_start();
        ($emitter ?? new ResponseEmitter())->emit($response);
        return (string) ob_get_clean();
    }

    private function response(int $status, string $body): ResponseInterface
    {
        $factory = new Psr17Factory();
        return $factory->createResponse($status)->withBody($factory->createStream($body));
    }

    #[RunInSeparateProcess]
    public function testBody(): void
    {
        $this->assertSame('hello', $this->emit($this->response(200, 'hello')));
    }

    #[RunInSeparateProcess]
    public function testChunkedBody(): void
    {
        $body = str_repeat('0123456789', 10000);
        $response = $this->response(200, $body);
        $response->getBody()->read(10); // starts from the beginning anyway

        $this->assertSame($body, $this->emit($response, new ResponseEmitter(1000)));
    }

    #[RunInSeparateProcess]
    public function testEmptyResponses(): void
    {
        foreach ([101, 204, 205, 304] as $status) {
            $this->assertSame('', $this->emit($this->response($status, 'ignored')));
        }
    }
}
