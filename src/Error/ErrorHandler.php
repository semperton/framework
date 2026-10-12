<?php

declare(strict_types=1);

namespace Semperton\Framework\Error;

use InvalidArgumentException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Semperton\Framework\Exception\HttpException;
use Throwable;

use function explode;
use function json_encode;

use const JSON_INVALID_UTF8_SUBSTITUTE;
use const JSON_PARTIAL_OUTPUT_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Renders exceptions as problem details (RFC 9457), details are only exposed in debug mode
 * Server errors (5xx) are passed to the logger
 */
final class ErrorHandler implements ErrorHandlerInterface
{
    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly bool $debug = false,
        private readonly ?LoggerInterface $logger = null,
    ) {}

    public function handle(ServerRequestInterface $request, Throwable $exception): ResponseInterface
    {
        $httpException = null;
        $statusCode = 500;

        // invalid codes of custom http exceptions must not break the error response
        if ($exception instanceof HttpException) {
            $code = $exception->getStatusCode();
            if ($code >= 400 && $code <= 599) {
                $httpException = $exception;
                $statusCode = $code;
            }
        }

        if ($statusCode >= 500) {
            $this->logger?->error($exception->getMessage(), ['exception' => $exception]);
        }

        $response = $this->responseFactory->createResponse($statusCode);

        // invalid headers of custom http exceptions must not break the error response either
        try {
            $withHeaders = $response;
            foreach ($httpException?->getHeaders() ?? [] as $name => $value) {
                $withHeaders = $withHeaders->withHeader($name, $value);
            }
            $response = $withHeaders;
        } catch (InvalidArgumentException $headerException) {
            $this->logger?->error('Invalid headers of < ' . $exception::class . ' >, the response is sent without them', [
                'exception' => $headerException,
            ]);
        }

        $response = $response->withHeader('Content-Type', 'application/problem+json');

        $title = $httpException?->getTitle() ?? $response->getReasonPhrase();

        $data = [
            'type' => 'about:blank',
            'title' => $title,
            'status' => $statusCode,
        ];

        // only http exception messages are meant for the client
        if ($httpException !== null && $httpException->getMessage() !== $title) {
            $data['detail'] = $httpException->getMessage();
        }

        if ($this->debug) {
            do {
                $data['errors'][] = [
                    'type' => $exception::class,
                    'code' => $exception->getCode(),
                    'message' => $exception->getMessage(),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                    'trace' => explode("\n", $exception->getTraceAsString()),
                ];
            } while ($exception = $exception->getPrevious());
        }

        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR;
        $response->getBody()->write((string) json_encode($data, $flags));

        return $response;
    }
}
