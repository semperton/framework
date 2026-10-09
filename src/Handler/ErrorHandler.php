<?php

declare(strict_types=1);

namespace Semperton\Framework\Handler;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Semperton\Framework\Exception\HttpException;
use Semperton\Framework\Exception\HttpMethodNotAllowedException;
use Semperton\Framework\Interfaces\ErrorHandlerInterface;
use Throwable;

use function explode;
use function implode;
use function json_encode;

use const JSON_INVALID_UTF8_SUBSTITUTE;
use const JSON_PARTIAL_OUTPUT_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Renders exceptions as json, details are only exposed in debug mode
 */
final class ErrorHandler implements ErrorHandlerInterface
{
    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly bool $debug = false
    ) {}

    public function handle(ServerRequestInterface $request, Throwable $exception): ResponseInterface
    {
        $statusCode = $exception instanceof HttpException ? $exception->getCode() : 500;
        $response = $this->responseFactory->createResponse($statusCode)
            ->withHeader('Content-Type', 'application/json');

        if ($exception instanceof HttpMethodNotAllowedException) {
            $response = $response->withHeader('Allow', implode(', ', $exception->getAllowedMethods()));
        }

        $data = [
            'status' => $statusCode,
            'message' => $response->getReasonPhrase()
        ];

        if ($this->debug) {
            do {
                $data['errors'][] = [
                    'type' => $exception::class,
                    'code' => $exception->getCode(),
                    'message' => $exception->getMessage(),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                    'trace' => explode("\n", $exception->getTraceAsString())
                ];
            } while ($exception = $exception->getPrevious());
        }

        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR;
        $response->getBody()->write((string)json_encode($data, $flags));

        return $response;
    }
}
