<?php

declare(strict_types=1);

namespace Semperton\Framework\Interfaces;

interface ConfigInterface
{
	public function getString(string $key): string;
	public function getBool(string $key): bool;
	public function getInt(string $key): int;
	public function getFloat(string $key): float;
	/**
	 * @return array<array-key, mixed>
	 */
	public function getArray(string $key): array;
	public function getObject(string $key): object;
	public function getConfig(string $key): ConfigInterface;
}
