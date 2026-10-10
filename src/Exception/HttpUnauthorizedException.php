<?php

declare(strict_types=1);

namespace Semperton\Framework\Exception;

use Throwable;

final class HttpUnauthorizedException extends HttpException
{
    protected const STATUS_CODE = 401;

    protected const REASON_PHRASE = 'Unauthorized';

    /**
     * @param string $challenge sent as WWW-Authenticate header (RFC 9110), e.g. 'Bearer realm="api"'
     */
    public function __construct(
        private readonly string $challenge,
        string $message = '',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $previous);
    }

    public function getHeaders(): array
    {
        return ['WWW-Authenticate' => $this->challenge];
    }
}
