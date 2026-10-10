<?php

declare(strict_types=1);

namespace Semperton\Framework\Exception;

final class HttpUnprocessableContentException extends HttpException
{
    protected const STATUS_CODE = 422;

    protected const REASON_PHRASE = 'Unprocessable Content';
}
