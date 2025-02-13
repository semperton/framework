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

use function get_class;
use function implode;
use function json_encode;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

final class ErrorHandler implements ErrorHandlerInterface
{
    protected ResponseFactoryInterface $responseFactory;

    public function __construct(ResponseFactoryInterface $responseFactory)
    {
        $this->responseFactory = $responseFactory;
    }

    public function handle(ServerRequestInterface $request, Throwable $exception): ResponseInterface
    {
        $statusCode = $this->getStatusCode($exception);
        $response = $this->responseFactory->createResponse($statusCode);

        if ($exception instanceof HttpMethodNotAllowedException) {
            $allowedMethods = $exception->getAllowedMethods();
            $response = $response->withHeader('Allow', implode(',', $allowedMethods));
        }

        $data = [
            'status' => $statusCode
        ];
        $data['errors'][] = $this->getExceptionData($exception);

        while ($prevEx = $exception->getPrevious()) {
            $data['errors'][] = $this->getExceptionData($prevEx);
        }

        $response = $response->withHeader('Content-Type', 'application/json');
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $response;
    }

    protected function getStatusCode(Throwable $exception): int
    {
        if ($exception instanceof HttpException) {
            return $exception->getCode();
        }

        return 500;
    }

    protected function getExceptionData(Throwable $exception): array
    {
        return [
            'type' => get_class($exception),
            'code' => $exception->getCode(),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTrace()
            // 'trace' => explode("\n", $exception->getTraceAsString())
        ];
    }
}
