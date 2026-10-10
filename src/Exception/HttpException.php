<?php

declare(strict_types=1);

namespace Semperton\Framework\Exception;

use RuntimeException;
use Throwable;

/**
 * The message is sent to the client, never put internal details in it
 */
abstract class HttpException extends RuntimeException
{
    /** @var int */
    protected const STATUS_CODE = 500;

    /** @var string */
    protected const REASON_PHRASE = 'Internal Server Error';

    public function __construct(string $message = '', ?Throwable $previous = null)
    {
        parent::__construct($message === '' ? static::REASON_PHRASE : $message, static::STATUS_CODE, $previous);
    }

    public function getStatusCode(): int
    {
        return static::STATUS_CODE;
    }

    /**
     * The reason phrase of the status code
     */
    public function getTitle(): string
    {
        return static::REASON_PHRASE;
    }

    /**
     * Headers required by the status code, e.g. 'Allow' for 405
     *
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return [];
    }
}
