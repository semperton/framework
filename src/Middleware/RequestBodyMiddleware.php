<?php

declare(strict_types=1);

namespace Semperton\Framework\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function explode;
use function strtolower;
use function trim;
use function json_decode;
use function mb_parse_str;

final class RequestBodyMiddleware implements MiddlewareInterface
{
	public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
	{
		$header = $request->getHeader('Content-Type');

		if (isset($header[0])) {

			$header = explode(';', $header[0], 2);
			$header = strtolower(trim($header[0]));

			$method = $request->getMethod();
			$body = (string)$request->getBody();

			$data = null;

			switch ($header) {
				case 'application/json':
					$data = json_decode($body);
					break;
				case 'application/x-www-form-urlencoded':
					if ($method === 'POST') {
						$data = $_POST;
					} else {
						mb_parse_str($body, $data);
					}
					break;
				case 'multipart/form-data':
					if ($method === 'POST') {
						$data = $_POST;
					}
					break;
			}

			$request = $request->withParsedBody($data);
		}

		return $handler->handle($request);
	}
}
