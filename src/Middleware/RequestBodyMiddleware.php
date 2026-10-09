<?php

declare(strict_types=1);

namespace Semperton\Framework\Middleware;

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
use function strtolower;
use function trim;

use const JSON_THROW_ON_ERROR;

/**
 * Parses json and urlencoded bodies, unless the request already has a parsed body
 */
final class RequestBodyMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->getParsedBody() !== null) {
            return $handler->handle($request);
        }

        $type = strtolower(trim(explode(';', $request->getHeaderLine('Content-Type'), 2)[0]));

        if ($type === 'application/json') {
            try {
                /** @var array|scalar|null */
                $data = json_decode((string)$request->getBody(), true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new HttpBadRequestException($request, $exception);
            }
            // scalar json values are no valid parsed body
            $request = $request->withParsedBody(is_array($data) ? $data : null);
        } elseif ($type === 'application/x-www-form-urlencoded') {
            parse_str((string)$request->getBody(), $data);
            $request = $request->withParsedBody($data);
        }

        return $handler->handle($request);
    }
}
