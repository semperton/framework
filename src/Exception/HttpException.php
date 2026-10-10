<?php

declare(strict_types=1);

namespace Semperton\Framework\Exception;

use Psr\Http\Message\ServerRequestInterface;
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

    public function __construct(
        protected readonly ServerRequestInterface $request,
        string $message = '',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message === '' ? static::REASON_PHRASE : $message, static::STATUS_CODE, $previous);
    }

    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }

    /**
     * The reason phrase of the status code
     */
    public function getTitle(): string
    {
        return static::REASON_PHRASE;
    }
}
