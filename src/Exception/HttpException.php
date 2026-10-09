<?php

declare(strict_types=1);

namespace Semperton\Framework\Exception;

use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Throwable;

abstract class HttpException extends RuntimeException
{
    /** @var int */
    protected const STATUS_CODE = 500;

    /** @var string */
    protected const REASON_PHRASE = 'Internal Server Error';

    public function __construct(
        protected readonly ServerRequestInterface $request,
        ?Throwable $previous = null
    ) {
        parent::__construct(static::REASON_PHRASE, static::STATUS_CODE, $previous);
    }

    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }
}
