# Security test baseline (2026-11-07)

This is a **non-invasive characterization pass** for `mithridatem/router`.
It does **not** certify the library, and it does not change production code.

## Covered by `tests/Security/SecurityBaselineTest.php`

- Default denial for protected controllers and role separation.
- Inheritance of group grants when a route has no explicit grants.
- **Current behavior**: route-level grants replace group-level grants.
- Controller denial when a middleware delegates with `next()`.
- **Current behavior**: middleware runs before the final grant check; side effects in pre-authorization middleware are therefore possible.
- Prefix confusion, literal encoded separators, nested routes and suffixes.
- Rejection of malformed normalized file input and refusal to move a synthetic, non-HTTP upload.

## Security decisions (not fixed in this PR)

1. **Group grants**: route-level grants replace group grants. If callers assume cumulative checks, they can inadvertently broaden access. Document the override prominently or consider an explicit AND policy with a compatibility strategy.
2. **Middleware order**: middleware executes before controller grants. This is needed for authentication middleware, but pre-authorization middleware should avoid sensitive side effects. A future design could distinguish authentication from authorization and post-authorization work.
3. **URL parsing/normalization**: these tests use `Request` instances. Reverse proxies, web servers and URL decoding behavior are outside the scope of this baseline and need HTTP-level integration tests.
4. **Uploads**: `UploadedFile::moveTo()` checks that an upload is a genuine HTTP upload. Destination allowlisting, extension/MIME verification, random filenames and isolation outside the executable web root remain application responsibilities.

## Run

```bash
composer install
composer test
# Or:
vendor/bin/phpunit tests/Security/SecurityBaselineTest.php
```

The suite should stay green on the current baseline. A future policy-changing PR must intentionally revise the corresponding characterization assertions and document the compatibility impact.

## Follow-up tests

- Real HTTP requests through Apache/nginx and `php -S`, including decoded and double-encoded paths.
- Multipart uploads against a real HTTP server and destination handling in the integrating application.
- Access checks with real session/JWT authentication providers.
- Dependency review with `composer audit` in each consuming application.
