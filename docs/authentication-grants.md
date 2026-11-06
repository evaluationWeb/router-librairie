# Authentication-aware grants (opt-in)

Use `AuthenticationAwareGrantChecker` to declare routes for guests or authenticated users without coupling the router to PHP sessions, JWT, cookies, or redirects.

```php
use Mithridatem\Routing\Auth\ArrayGrantChecker;
use Mithridatem\Routing\Auth\AuthenticationAwareGrantChecker;
use Mithridatem\Routing\Router;

$router = new Router();
$router->setGrantChecker(new AuthenticationAwareGrantChecker(
    isAuthenticated: static fn (): bool => isset($_SESSION['user_id']),
    roleChecker: new ArrayGrantChecker($_SESSION['roles'] ?? [])
));
```

The session is **owned and started by the application**; the callback shown is an illustrative Web adapter. For JWT APIs, supply a callback based on a previously verified authentication principal (not merely the existence of an Authorization header). The router does not verify tokens.

## Route declarations

```php
use Mithridatem\Routing\Attribute\Route;

#[Route(path: '/login', method: 'GET', grants: ['IS_GUEST'])]
public function login(): string { /* ... */ }

#[Route(path: '/register', method: 'POST', grants: ['IS_GUEST'])]
public function register(): string { /* ... */ }

#[Route(path: '/profile', method: 'GET', grants: ['IS_AUTHENTICATED'])]
public function profile(): string { /* ... */ }

#[Route(path: '/admin', method: 'GET', grants: ['IS_AUTHENTICATED', 'ROLE_ADMIN'])]
public function admin(): string { /* ... */ }
```

The same grant arrays work with `Route::get()`, `Route::post()`, controller routes, attributes, and route groups.

| Grants | Guest | Authenticated user |
| --- | --- | --- |
| `[]` | Allow | Allow |
| `['IS_GUEST']` | Allow | Deny |
| `['IS_AUTHENTICATED']` | Deny | Allow |
| `['ROLE_USER']` | Existing role checker decides | Existing role checker decides |
| `['IS_AUTHENTICATED', 'ROLE_USER']` | Deny | Require ROLE_USER |

Authentication requirements and ordinary role permissions are combined with **AND**. Ordinary role grants retain their existing **OR** semantics. Combining `IS_GUEST` and `IS_AUTHENTICATED` is always denied. Note that `IS_GUEST` means *not authenticated*, not *missing a role*. With the default `ArrayGrantChecker([])`, the new tokens are **not automatically activated**: configure this checker explicitly.

## Denials, Web navigation, API responses

As before, `ControllerInvoker` throws `UnauthorizedException` (code 403) when `isGranted()` returns false. No exception class, router signature, or redirect behavior is changed. For a Web application, the application-level exception handler may choose to redirect authenticated users denied on guest-only routes to a previously visited, authorized local path stored in its session, with a configurable safe fallback. It must avoid open redirects and loops. It must distinguish guest-only rejections from other denials using its own routing/security context; **not all 403s should redirect**. This library does not store navigation history or enforce a fallback path.

An API should return a JSON error response rather than a session redirect. Decide the HTTP status in the application authentication/error layer, particularly when no valid JWT is available.

## Compatibility

`GrantCheckerInterface`, `ArrayGrantChecker`, `AllowAllGrantChecker`, `Router`, and `ControllerInvoker` remain unchanged. Existing projects keep their current behavior unless they opt in using `setGrantChecker()`. The new checker delegates existing role evaluation to any `GrantCheckerInterface` implementation.
