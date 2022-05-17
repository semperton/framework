<?php

declare(strict_types=1);

namespace Semperton\Framework;

use Semperton\Framework\Interfaces\ConfigInterface;
use OutOfBoundsException;

use function array_key_exists;
use function is_array;

final class ArrayConfig implements ConfigInterface
{
	/** @var array<array-key, mixed> */
	protected array $values;

	/** @param array<array-key, mixed> $values */
	public function __construct(array $values)
	{
		$this->values = $values;
	}

	/** @return mixed */
	protected function get(string $key)
	{
		if (isset($this->values[$key]) || array_key_exists($key, $this->values)) {

			return $this->values[$key];
		}

		throw new OutOfBoundsException("No config entry found for < $key >");
	}

	public function getString(string $key): string
	{
		/** @var string */
		return $this->get($key);
	}

	public function getBool(string $key): bool
	{
		/** @var bool */
		return $this->get($key);
	}

	public function getInt(string $key): int
	{
		/** @var int */
		return $this->get($key);
	}

	public function getFloat(string $key): float
	{
		/** @var float */
		return $this->get($key);
	}

	public function getArray(string $key): array
	{
		/** @var array */
		return $this->get($key);
	}

	public function getObject(string $key): object
	{
		/** @var object */
		return $this->get($key);
	}

	public function getConfig(string $key): ConfigInterface
	{
		/** @var mixed */
		$value = $this->get($key);

		if (is_array($value)) {
			$value = new self($value);
		}

		/** @var ConfigInterface */
		return $value;
	}
}
