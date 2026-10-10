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
Routes take PSR-15 request handlers:
```php
use Nyholm\Psr7\Factory\Psr17Factory;
use Semperton\Framework\ApplicationBuilder;
use Semperton\Framework\Interfaces\RouteCollectorInterface;

$builder = new ApplicationBuilder(new Psr17Factory());

$builder->addErrorMiddleware();

$builder->get('/users/:id:d', $userHandler);
$builder->post('/users', $createHandler, [$authMiddleware]);

$builder->group('/admin', function (RouteCollectorInterface $admin) use ($dashboardHandler) {
    $admin->get('/dashboard', $dashboardHandler, name: 'dashboard');
}, [$authMiddleware], name: 'admin.');

$app = $builder->build();
$response = $app->handle($request);
```

Middleware runs in the order it was added, the first one is the outermost.
Group and route middleware only runs for matched routes, after the global middleware.
Unmatched requests result in a `404` / `405` exception (with an `Allow` header, `GET` routes include `HEAD`).
`OPTIONS` requests are answered with a `204` and the `Allow` header, unless an `OPTIONS` route exists. These responses only pass the global middleware.

### Route params

The routing adds a `RouteResult` to the request, with the matched route and its decoded params:
```php
use Semperton\Framework\RouteResult;

$result = RouteResult::fromRequest($request); // null before the routing

$result->param('id');            // '42', null if missing
$result->param('page', '1');     // with a default
$result->params;                 // ['id' => '42']
$result->route?->name;           // 'admin.dashboard', empty if unnamed, null without a match
$result->route?->pattern;        // '/admin/dashboard'
$result->allowedMethods;         // methods of the path if the method did not match, e.g. ['GET', 'HEAD', 'OPTIONS']
```
Params are kept in this single attribute, so they never collide with attributes of other middleware.

Routing runs as the innermost global middleware by default. Call `addRoutingMiddleware()` to place it earlier, e.g. when a middleware needs the matched route for logging, metrics or access control.

### Routes

Paths, placeholders, validators and wildcards are described in the [routing docs](https://github.com/semperton/routing).
Add custom validators before the routes using them:
```php
$builder->addRouteValidator('slug', fn (string $value): bool => preg_match('/^[a-z0-9-]+$/', $value) === 1);
$builder->get('/blog/:slug:slug', $articleHandler, name: 'article');
```
`any()` maps `GET`, `POST`, `PUT`, `PATCH`, `DELETE` and `OPTIONS`, `map()` takes a custom list:
```php
$builder->any('/rpc', $rpcHandler);
$builder->map(['GET', 'POST'], '/search', $searchHandler);
```
Build the paths of named routes with the `UrlGenerator`. It is available before `build()`, e.g. to register it in the container:
```php
$urls = $builder->getUrlGenerator();
$urls->path('article', ['slug' => 'hello-world']); // '/blog/hello-world'
```

### Base path

Serving the application from a sub-directory, set the base path as it appears in the url.
Routes are defined without it, requests outside of it result in a `404` and the `UrlGenerator` includes it:
```php
$builder = new ApplicationBuilder($responseFactory, basePath: '/app'); // https://example.com/app

$builder->get('/blog/:slug', $articleHandler, name: 'article');    // matches '/app/blog/hello-world'
$builder->getUrlGenerator()->path('article', ['slug' => 'hello']); // '/app/blog/hello'
```

## Container

Handlers and middleware can be given as container ids. They are resolved from a PSR-11 container on first use, so unused routes are never instantiated:
```php
$builder = new ApplicationBuilder($responseFactory, $container);

$builder->add(SessionMiddleware::class);
$builder->get('/', IndexHandler::class, [CacheMiddleware::class]);
```

## Error handling

`addErrorMiddleware()` renders all exceptions as [problem details](https://www.rfc-editor.org/rfc/rfc9457) (`application/problem+json`). Add it first, so it catches the errors of all other middleware.
Details and traces are only exposed in debug mode, server errors (5xx) are passed to an optional PSR-3 logger:
```php
use Semperton\Framework\Handler\ErrorHandler;

$builder->addErrorMiddleware(new ErrorHandler($responseFactory, debug: true, logger: $logger));
```
Throw an `HttpException` to respond with a specific status code. Its message is sent to the client as `detail`, so never put internal details in it:
```php
throw new HttpNotFoundException('User not found');
// {"type":"about:blank","title":"Not Found","status":404,"detail":"User not found"}

throw new HttpNotFoundException();
// {"type":"about:blank","title":"Not Found","status":404}
```
The messages of all other exceptions are never exposed, they result in a `500`. Extend `HttpException` for other status codes:
```php
final class HttpForbiddenException extends HttpException
{
    protected const STATUS_CODE = 403;
    protected const REASON_PHRASE = 'Forbidden';
}
```
Implement the `ErrorHandlerInterface` for custom error responses.

## Request body

The `RequestBodyMiddleware` parses non-empty `application/json` (and `+json` types like `application/merge-patch+json`) and `application/x-www-form-urlencoded` bodies, unless the request already has a non-empty parsed body.
Invalid JSON results in a `400` response, other content types (e.g. uploads) are left unread.
The raw body stays readable, e.g. to verify webhook signatures.
```php
$builder->add(new RequestBodyMiddleware());
```

## CORS

CORS is a security policy of your application, so there is no built in middleware.
Since `OPTIONS` requests are answered automatically, a global middleware only needs to add the headers.
Add it first, so error responses get the headers too:
```php
final class CorsMiddleware implements MiddlewareInterface
{
    /** @param list<string> $origins */
    public function __construct(private readonly array $origins) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request)->withAddedHeader('Vary', 'Origin');
        $origin = $request->getHeaderLine('Origin');

        if (!in_array($origin, $this->origins, true)) {
            return $response;
        }

        $response = $response->withHeader('Access-Control-Allow-Origin', $origin);

        // preflight, answered with the allowed methods of the path
        if ($request->getMethod() === 'OPTIONS' && $request->hasHeader('Access-Control-Request-Method')) {
            $response = $response
                ->withHeader('Access-Control-Allow-Methods', $response->getHeaderLine('Allow'))
                ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
                ->withHeader('Access-Control-Max-Age', '600');
        }

        return $response;
    }
}

$builder->add(new CorsMiddleware(['https://example.com']));
$builder->addErrorMiddleware();
```
Packages like [middlewares/cors](https://github.com/middlewares/cors) work as well.

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
The builder is locked after `build()`.
`HEAD` requests are routed to `GET` handlers and return their body. PHP-FPM drops it automatically, in workers the server adapter must not send it.
