<?php

declare(strict_types=1);

namespace Semperton\Framework\Exception;

use Throwable;

final class HttpServiceUnavailableException extends HttpException
{
    protected const STATUS_CODE = 503;

    protected const REASON_PHRASE = 'Service Unavailable';

    /**
     * @param ?non-negative-int $retryAfter seconds, sent as Retry-After header
     */
    public function __construct(
        private readonly ?int $retryAfter = null,
        string $message = '',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $previous);
    }

    public function getHeaders(): array
    {
        return $this->retryAfter === null ? [] : ['Retry-After' => (string) $this->retryAfter];
    }
}
