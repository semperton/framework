<?php

declare(strict_types=1);

namespace Semperton\Framework\Exception;

final class HttpNotFoundException extends HttpException
{
    protected const STATUS_CODE = 404;

    protected const REASON_PHRASE = 'Not Found';
}
