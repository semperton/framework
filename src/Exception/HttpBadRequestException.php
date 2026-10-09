<?php

declare(strict_types=1);

namespace Semperton\Framework\Exception;

final class HttpBadRequestException extends HttpException
{
    protected const STATUS_CODE = 400;

    protected const REASON_PHRASE = 'Bad Request';
}
