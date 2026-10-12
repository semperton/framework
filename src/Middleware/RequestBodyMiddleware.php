<?php

declare(strict_types=1);

namespace Semperton\Framework\Middleware;

use ErrorException;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Semperton\Framework\Exception\HttpBadRequestException;

use function explode;
use function is_array;
use function json_decode;
use function parse_str;
use function restore_error_handler;
use function set_error_handler;
use function str_ends_with;
use function strtolower;
use function trim;

use const E_WARNING;
use const JSON_THROW_ON_ERROR;

/**
 * Parses non-empty json (including +json types) and urlencoded bodies, unless the request already has a non-empty parsed body
 */
final class RequestBodyMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $parsedBody = $request->getParsedBody();

        // some request factories pass an empty $_POST for non-form requests
        if ($parsedBody !== null && $parsedBody !== []) {
            return $handler->handle($request);
        }

        $type = strtolower(trim(explode(';', $request->getHeaderLine('Content-Type'), 2)[0]));
        $json = $type === 'application/json' || str_ends_with($type, '+json');

        // other bodies (uploads, binary) stay unread for the handler
        if (!$json && $type !== 'application/x-www-form-urlencoded') {
            return $handler->handle($request);
        }

        $stream = $request->getBody();
        $body = (string) $stream;

        // keep the raw body readable for the handler, e.g. for signature checks
        if ($stream->isSeekable()) {
            $stream->rewind();
        }

        if ($body === '') {
            return $handler->handle($request);
        }

        if ($json) {
            try {
                /** @var array|scalar|null */
                $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new HttpBadRequestException(previous: $exception);
            }
            // scalar json values are no valid parsed body
            $request = $request->withParsedBody(is_array($data) ? $data : null);
        } else {
            // parse_str drops input above max_input_vars or max_input_nesting_level with a warning
            set_error_handler(static function (int $level, string $message): never {
                throw new HttpBadRequestException(previous: new ErrorException($message, 0, $level));
            }, E_WARNING);
            try {
                parse_str($body, $data);
            } finally {
                restore_error_handler();
            }
            $request = $request->withParsedBody($data);
        }

        return $handler->handle($request);
    }
}
