<?php

declare(strict_types=1);

namespace Semperton\Framework\Exception;

final class HttpForbiddenException extends HttpException
{
    protected const STATUS_CODE = 403;

    protected const REASON_PHRASE = 'Forbidden';
}
