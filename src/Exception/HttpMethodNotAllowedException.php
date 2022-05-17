<?php

declare(strict_types=1);

namespace Semperton\Framework\Exception;

final class HttpMethodNotAllowedException extends HttpException
{
	protected $code = 405;

	protected $message = 'Method Not Allowed';

	/** @var array<int, string> */
	protected array $allowedMethods = [];

	/**
	 * @param array<int, string> $methods
	 */
	public function setAllowedMethods(array $methods): self
	{
		$this->allowedMethods = $methods;
		return $this;
	}

	/**
	 * @return array<int, string>
	 */
	public function getAllowedMethods(): array
	{
		return $this->allowedMethods;
	}
}
