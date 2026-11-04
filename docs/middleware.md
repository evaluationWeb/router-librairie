# HTTP Middleware pipeline

Middleware support is opt-in. Existing routes and `GrantCheckerInterface` implementations remain unchanged.

## Contract

Implement `Mithridatem\Routing\Middleware\MiddlewareInterface`:

```php
use Mithridatem\Routing\Context\RequestContextInterface;
use Mithridatem\Routing\Middleware\MiddlewareInterface;

final class TraceMiddleware implements MiddlewareInterface
{
    public function process(RequestContextInterface $context, callable $next): mixed
    {
        // Work before controller / authorization
        $response = $next($context);
        // Work after controller
        return $response;
    }
}
```

The middleware may return without invoking `$next` to reject a request. The chain executes in order: global middlewares, route middlewares, existing grant check, controller. The grant check remains the last gate before controller invocation.

## Registration

```php
$router->addMiddleware(new TraceMiddleware());

#[Route(path: '/items', method: 'POST', middleware: [CsrfMiddleware::class])]
public function create(): mixed { /* ... */ }
```

Routes registered manually can use `Route::get('/items', $handler)->withMiddleware(MyMiddleware::class)` or `Route::controller(...)->withMiddleware(...)`. The existing signatures are retained.

Class-string middlewares are constructed by `DefaultMiddlewareResolver` with no constructor arguments. For dependency injection, implement `MiddlewareResolverInterface` and call `$router->setMiddlewareResolver($resolver)`. Do not put CSRF/JWT implementations into this routing package.

The pipeline's input is `RequestContextInterface` for compatibility with the existing `dispatch()` signature. Middlewares that need POST data or headers should check that the context also implements `RequestInterface` or `ParameterCollectionRequestInterface` and fail explicitly otherwise. A middleware may pass a different compatible context to `$next`, which will then be used for controller argument injection.

### Security notes

The pipeline runs after route matching. The existing `GrantCheckerInterface` remains responsible for grants; middleware authentication must establish verified identity before invoking `$next`. Route matching failures do not traverse this pipeline. Missing/invalid credentials should result in 401 while authenticated users with insufficient grants should receive 403 at the HTTP boundary. JWT signature/claims validation and CSRF session-token storage belong to the consuming application's security layer.

## Compatibility

No middleware is installed by default. The original `Route` constructor and factory positional parameters remain valid; `middleware` is an optional final parameter. `#[Route]` likewise gains an optional final `middleware` property. `Router::dispatch()` continues to accept `RequestContextInterface` and return `mixed`.
