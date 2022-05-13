<?php

declare(strict_types=1);

namespace Semperton\Framework\Interfaces;

interface PayloadInterface
{
	public function withStatus(int $status): PayloadInterface;
	public function getStatus(): int;
	/**
	 * @param mixed $input
	 */
	public function withInput($input): PayloadInterface;
	/**
	 * @return mixed
	 */
	public function getInput();
	/**
	 * @param mixed $output
	 */
	public function withOutput($output): PayloadInterface;
	/**
	 * @return mixed
	 */
	public function getOutput();
	/**
	 * @param array<int, string> $messages
	 */
	public function withMessages(array $messages): PayloadInterface;
	/**
	 * @return array<int, string>
	 */
	public function getMessages(): array;
}
