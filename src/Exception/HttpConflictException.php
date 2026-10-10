<?php

declare(strict_types=1);

namespace Semperton\Framework\Exception;

final class HttpConflictException extends HttpException
{
    protected const STATUS_CODE = 409;

    protected const REASON_PHRASE = 'Conflict';
}
