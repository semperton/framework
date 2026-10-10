<?php

declare(strict_types=1);

namespace Semperton\Framework;

use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Semperton\Framework\Interfaces\ResponseEmitterInterface;

use function connection_status;
use function header;
use function headers_sent;
use function in_array;
use function ob_get_length;
use function ob_get_level;
use function ob_implicit_flush;
use function strtolower;

use const CONNECTION_NORMAL;

final class ResponseEmitter implements ResponseEmitterInterface
{
    /** bodies of a known size up to this limit are sent at once */
    private const MAX_SINGLE_WRITE = 65536;

    public function __construct(
        private readonly int $chunkSize = 4096,
    ) {}

    public function emit(ResponseInterface $response): void
    {
        $filename = $line = null;

        if (headers_sent($filename, $line)) {
            throw new RuntimeException("Headers already sent in < $filename > on line < $line >, unable to emit response");
        }

        if (ob_get_level() > 0 && ob_get_length() > 0) {
            throw new RuntimeException('Output already started, unable to emit response');
        }

        $responseEmpty = $this->isResponseEmpty($response);

        $this->sendStatusLine($response);
        $this->sendHeaders($response);

        if (!$responseEmpty) {
            $this->sendBody($response);
        }
    }

    private function sendStatusLine(ResponseInterface $response): void
    {
        $protocol = $response->getProtocolVersion();
        $status = $response->getStatusCode();
        $reason = $response->getReasonPhrase();

        header("HTTP/$protocol $status $reason", true, $status);
    }

    private function sendHeaders(ResponseInterface $response): void
    {
        $headers = $response->getHeaders();

        foreach ($headers as $name => $values) {

            $first = strtolower((string) $name) !== 'set-cookie';

            foreach ($values as $value) {
                header("$name: $value", $first);
                $first = false;
            }
        }
    }

    private function sendBody(ResponseInterface $response): void
    {
        ob_implicit_flush();

        $stream = $response->getBody();
        $size = $stream->getSize();

        if ($this->chunkSize < 1 || ($size !== null && $size <= self::MAX_SINGLE_WRITE)) {
            echo (string) $stream;
            return;
        }

        if ($stream->isSeekable()) {
            $stream->rewind();
        }

        while (!$stream->eof()) {

            echo $stream->read($this->chunkSize);

            if (connection_status() !== CONNECTION_NORMAL) {
                break;
            }
        }
    }

    private function isResponseEmpty(ResponseInterface $response): bool
    {
        $statusCode = $response->getStatusCode();
        if (in_array($statusCode, [204, 205, 304], true)) {
            return true;
        }

        return !$response->getBody()->isReadable();
    }
}
