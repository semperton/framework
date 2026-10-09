<?php

declare(strict_types=1);

namespace Semperton\Framework\Exception;

use Psr\Http\Message\ServerRequestInterface;
use Throwable;

final class HttpMethodNotAllowedException extends HttpException
{
    protected const STATUS_CODE = 405;

    protected const REASON_PHRASE = 'Method Not Allowed';

    /**
     * @param list<string> $allowedMethods
     */
    public function __construct(
        ServerRequestInterface $request,
        private readonly array $allowedMethods,
        string $message = '',
        ?Throwable $previous = null,
    ) {
        parent::__construct($request, $message, $previous);
    }

    /**
     * @return list<string>
     */
    public function getAllowedMethods(): array
    {
        return $this->allowedMethods;
    }
}
