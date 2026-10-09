<?php

declare(strict_types=1);

namespace Semperton\Framework\Tests\Misc;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function is_string;

/**
 * Writes the given text, or the 'name' route param if set
 */
final class TestHandler implements RequestHandlerInterface
{
    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly string $text = 'handler',
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $name = $request->getAttribute('name');
        $response = $this->responseFactory->createResponse();
        $response->getBody()->write(is_string($name) ? $name : $this->text);

        return $response;
    }
}
