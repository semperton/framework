<?php

declare(strict_types=1);

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Semperton\Framework\Application;
use Semperton\Framework\Interfaces\RouteCollectorInterface;
use Semperton\Framework\Tests\Misc\TestAction;
use Semperton\Framework\Tests\Misc\TestMiddleware;

final class ApplicationTest extends TestCase
{
	protected function createRequest(string $method, string $uri): ServerRequestInterface
	{
		return new ServerRequest($method, $uri);
	}
	public function testApplicationCreate(): void
	{
		$factory = new Psr17Factory();
		$app = new Application(
			$factory
		);

		$app->addRoutingMiddleware();
		// $app->addConditionalMiddleware();
		$app->addActionMiddleware();

		$action = new TestAction($factory);

		$app->group('/home', function (RouteCollectorInterface $index) use ($action) {
			$index->get('/', $action, [
				new TestMiddleware()
			]);
		});

		// $app->get('/home', $action);

		$request = $this->createRequest('GET', '/home');
		$response = $app->handle($request);

		$this->assertEquals('Hello World', (string)$response->getBody());
	}
}
