<div align="center">
<a href="https://github.com/semperton">
<img width="140" src="https://raw.githubusercontent.com/semperton/.github/main/readme-logo.svg" alt="Semperton">
</a>
<h1>Semperton Framework</h1>
<p>A small PSR-15 framework built on <a href="https://github.com/semperton/routing">semperton/routing</a>.<br>Built once, ready for long-running PHP processes.</p>
</div>

---

## Installation

Just use Composer:

```
composer require semperton/framework
```
Framework requires PHP 8.2+ and any PSR-7 / PSR-17 implementation.

## Usage

Configure the application with the `ApplicationBuilder` and `build()` it.
Routes take PSR-15 request handlers, route params are added as request attributes:
```php
use Nyholm\Psr7\Factory\Psr17Factory;
use Semperton\Framework\ApplicationBuilder;
use Semperton\Framework\Interfaces\RouteCollectorInterface;

$builder = new ApplicationBuilder(new Psr17Factory());

$builder->addErrorMiddleware();

$builder->get('/users/:id:d', $userHandler); // $request->getAttribute('id')
$builder->post('/users', $createHandler, [$authMiddleware]);

$builder->group('/admin', function (RouteCollectorInterface $admin) use ($dashboardHandler) {
    $admin->get('/dashboard', $dashboardHandler, name: 'dashboard');
}, [$authMiddleware], name: 'admin.');

$app = $builder->build();
$response = $app->handle($request);
```

Middleware runs in the order it was added, the first one is the outermost.
Group and route middleware only runs for matched routes, after the global middleware.
The built in `EndpointHandler` dispatches the matched route or throws a `404` / `405` exception (with an `Allow` header, `GET` routes include `HEAD`).

Routing runs as the innermost global middleware by default. Call `addRoutingMiddleware()` to place it earlier, e.g. when a middleware needs the matched route (`$request->getAttribute(RoutingMiddleware::ATTRIBUTE)`), or to use a custom matcher.

### Routes

Paths, placeholders, validators and wildcards are described in the [routing docs](https://github.com/semperton/routing).
Pass your own `RouteCollection` to add validators or to build urls:
```php
use Semperton\Routing\Collection\RouteCollection;

$routes = new RouteCollection();
$routes->setValidator('slug', fn (string $value): bool => preg_match('/^[a-z0-9-]+$/', $value) === 1);

$builder = new ApplicationBuilder($responseFactory, routeCollection: $routes);
$builder->get('/blog/:slug:slug', $articleHandler, name: 'article');

$routes->reverse('article', ['slug' => 'hello-world']); // '/blog/hello-world'
```

## Container

Handlers and middleware can be given as container ids. They are resolved from a PSR-11 container on first use, so unused routes are never instantiated:
```php
$builder = new ApplicationBuilder($responseFactory, $container);

$builder->add(SessionMiddleware::class);
$builder->get('/', IndexHandler::class, [CacheMiddleware::class]);
```

## Error handling

`addErrorMiddleware()` renders all exceptions as JSON. Add it first, so it catches the errors of all other middleware.
Details and traces are only exposed in debug mode, server errors (5xx) are passed to an optional PSR-3 logger:
```php
use Semperton\Framework\Handler\ErrorHandler;

$builder->addErrorMiddleware(new ErrorHandler($responseFactory, debug: true, logger: $logger));
```
Throw an `HttpException` to respond with a specific status code. Its message is sent to the client, so never put internal details in it:
```php
throw new HttpNotFoundException($request, 'User not found'); // {"status":404,"message":"User not found"}
```
The messages of all other exceptions are never exposed. Extend `HttpException` for other status codes:
```php
final class HttpForbiddenException extends HttpException
{
    protected const STATUS_CODE = 403;
    protected const REASON_PHRASE = 'Forbidden';
}
```
Implement the `ErrorHandlerInterface` for custom error responses.

## Request body

The `RequestBodyMiddleware` parses non-empty `application/json` (and `+json` types like `application/merge-patch+json`) and `application/x-www-form-urlencoded` bodies, unless the request already has a parsed body.
Invalid JSON results in a `400` response.
```php
$builder->add(new RequestBodyMiddleware());
```

## Development

```
composer test   # phpunit
composer check  # psalm + php-cs-fixer (PER-CS)
composer fix    # apply the code style
```

## Running

The built application is immutable and keeps no state between requests.

With PHP-FPM, emit the response with the `ResponseEmitter`:
```php
use Semperton\Framework\ResponseEmitter;

(new ResponseEmitter())->emit($app->handle($request));
```

In long-running processes (FrankenPHP worker mode, RoadRunner, Workerman, Swoole ...), build the application once at boot and call `handle()` for every request.
Routes added to the builder after `build()` still reach the application, so finish the configuration first.
`HEAD` requests are routed to `GET` handlers and return their body. PHP-FPM drops it automatically, in workers the server adapter must not send it.
