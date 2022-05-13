<?php

declare(strict_types=1);

namespace Semperton\Framework\Exception;

use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Throwable;

abstract class HttpException extends RuntimeException
{
	protected ServerRequestInterface $request;

	public function __construct(
		ServerRequestInterface $request,
		?Throwable $previous = null
	) {
		parent::__construct('', 0, $previous);

		$this->request = $request;
	}

	public function getRequest(): ServerRequestInterface
	{
		return $this->request;
	}
}
