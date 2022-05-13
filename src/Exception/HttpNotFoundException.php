<?php

declare(strict_types=1);

namespace Semperton\Framework\Exception;

final class HttpNotFoundException extends HttpException
{
	protected $code = 404;

	protected $message = 'Not Found';
}
