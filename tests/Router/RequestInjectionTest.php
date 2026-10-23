<?php

declare(strict_types=1);

namespace Mithridatem\Routing\Tests\Router;

use Mithridatem\Routing\Argument\ArgumentResolverInterface;
use Mithridatem\Routing\Context\NativeRequestContext;
use Mithridatem\Routing\Context\RequestContextInterface;
use Mithridatem\Routing\Exception\RouterException;
use Mithridatem\Routing\Exception\UnauthorizedException;
use Mithridatem\Routing\Http\Request;
use Mithridatem\Routing\Http\RequestInterface;
use Mithridatem\Routing\Loader\AttributeRouteLoader;
use Mithridatem\Routing\Route;
use Mithridatem\Routing\Router;
use Mithridatem\Routing\Tests\Support\FakeControllerResolver;
use Mithridatem\Routing\Tests\Support\FakeRequestContext;
use Mithridatem\Routing\Tests\Support\RequestDemoController;
use PHPUnit\Framework\TestCase;

final class RequestInjectionTest extends TestCase
{
    public function testInjectionWorksInEitherPositionAndPreservesNamedRouteBinding(): void
    {
        $router = new Router();
        $router->map(Route::post('/first/{id}/{slug}', fn (RequestInterface $request, string $slug, string $id)
            => [$id, $slug, $request]));
        $router->map(Route::post('/last/{id}/{slug}', fn (string $slug, string $id, RequestInterface $request)
            => [$id, $slug, $request]));

        foreach (['first', 'last'] as $prefix) {
            $request = new Request("/$prefix/42/php", 'POST', post: ['title' => 'PHP']);
            self::assertSame(['42', 'php', $request], $router->dispatch($request));
        }
    }

    public function testConcreteRequestAndMultipleTypedParametersReceiveSameInstance(): void
    {
        $router = new Router();
        $router->map(Route::get('/', fn (Request $first, RequestInterface $second) => [$first, $second]));
        $request = new Request('/');
        self::assertSame([$request, $request], $router->dispatch($request));
    }

    public function testCustomRequestImplementationIsAccepted(): void
    {
        $request = new class implements RequestInterface {
            public function getPath(): string
            {
                return '/';
            }

            public function getMethod(): string
            {
                return 'GET';
            }

            public function getBearerToken(): ?string
            {
                return null;
            }

            public function query(string $name, mixed $default = null): mixed
            {
                return $default;
            }

            public function post(string $name, mixed $default = null): mixed
            {
                return $default;
            }

            public function header(string $name, ?string $default = null): ?string
            {
                return $default;
            }

            public function getContent(): string
            {
                return '';
            }

        };
        $router = new Router();
        $router->map(Route::get('/', fn (?RequestInterface $http = null) => $http));
        self::assertSame($request, $router->dispatch($request));

        $router = new Router();
        $router->map(Route::get('/', fn (Request $http) => $http));
        $this->expectException(RouterException::class);
        $this->expectExceptionMessage('expected ' . Request::class);
        $router->dispatch($request);
    }

    public function testLegacyHandlersKeepDefaultsVariadicsAndScalarCoercion(): void
    {
        $router = new Router();
        $router->map(Route::get('/books/{id}', fn (int $id, string $format = 'html') => [$id, $format]));
        $router->map(Route::get('/tags/{first}/{second}', fn (...$tags) => $tags));

        self::assertSame([42, 'html'], $router->dispatch(new FakeRequestContext('/books/42')));
        self::assertSame(['first' => 'php', 'second' => 'sql'], $router->dispatch(new FakeRequestContext('/tags/php/sql')));
        self::assertSame([42, 'html'], $router->dispatch(new NativeRequestContext('/books/42', 'GET')));
    }

    public function testInjectionPreservesDefaultsAndVariadics(): void
    {
        $router = new Router();
        $router->map(Route::get('/books/{id}', fn (RequestInterface $request, int $id, string $format = 'html')
            => [$request, $id, $format]));
        $router->map(Route::get('/tags/{tag}', fn (RequestInterface $request, ...$tags) => [$request, $tags]));
        $request = new Request('/books/42');
        self::assertSame([$request, 42, 'html'], $router->dispatch($request));
        $request = new Request('/tags/php');
        self::assertSame([$request, ['tag' => 'php']], $router->dispatch($request));
    }

    public function testRequestParameterIsIdentifiedByTypeRatherThanName(): void
    {
        $router = new Router();
        $router->map(Route::get('/{request}', fn (string $request) => $request));
        self::assertSame('value', $router->dispatch(new FakeRequestContext('/value')));
    }

    public function testLegacyContextCannotBeSilentlyReplacedWithGlobals(): void
    {
        $router = new Router();
        $router->map(Route::get('/', fn (RequestInterface $request) => $request));
        $this->expectException(RouterException::class);
        $this->expectExceptionMessage('Pass a compatible RequestInterface to dispatch()');
        $router->dispatch(new FakeRequestContext('/'));
    }

    public function testNullableRequestStillRequiresCompatibleContext(): void
    {
        $router = new Router();
        $router->map(Route::get('/', fn (?RequestInterface $request = null) => $request));
        $this->expectException(RouterException::class);
        $router->dispatch(new NativeRequestContext('/', 'GET'));
    }

    public function testRouteNameCannotOverwriteInjectedRequest(): void
    {
        $router = new Router();
        $router->map(Route::get('/{request}', fn (RequestInterface $request) => $request));
        $this->expectException(RouterException::class);
        $this->expectExceptionMessage('conflicts');
        $router->dispatch(new Request('/value'));
    }

    public function testByReferenceRequestInjectionIsRejected(): void
    {
        $router = new Router();
        $router->map(Route::get('/', function (RequestInterface &$request) {
            return $request;
        }));
        $this->expectException(RouterException::class);
        $router->dispatch(new Request('/'));
    }

    public function testVariadicRequestInjectionIsRejected(): void
    {
        $router = new Router();
        $router->map(Route::get('/', fn (RequestInterface ...$requests) => $requests));
        $this->expectException(RouterException::class);
        $router->dispatch(new Request('/'));
    }

    public function testAttributesAndExistingControllerResolverWorkTogether(): void
    {
        $controller = new RequestDemoController('Book');
        $router = new Router();
        $router->setControllerResolver(new FakeControllerResolver([RequestDemoController::class => $controller]));
        (new AttributeRouteLoader())->loadController($router, RequestDemoController::class);

        self::assertSame('Book 42: PHP', $router->dispatch(new Request('/books/42', 'POST', post: ['title' => 'PHP'])));
    }

    public function testDefaultContextBasePathAndSuccessiveRequests(): void
    {
        $router = new Router();
        $router->setBasePath('/app');
        $router->map(Route::get('/books', fn (RequestInterface $request) => [$request->getPath(), $request->query('page')]));
        $router->setDefaultContext(new Request('/app/books', query: ['page' => '1']));

        self::assertSame(['/app/books', '1'], $router->dispatch());
        self::assertSame(['/app/books', '2'], $router->dispatch(new Request('/app/books', query: ['page' => '2'])));
        self::assertSame(['/app/books', '1'], $router->dispatch());
    }

    public function testDispatchWithoutContextCreatesAFullRequest(): void
    {
        $server = $_SERVER;
        $get = $_GET;
        try {
            $_SERVER = ['REQUEST_URI' => '/books?page=2', 'REQUEST_METHOD' => 'GET'];
            $_GET = ['page' => '2'];
            $router = new Router();
            $router->map(Route::get('/books', fn (RequestInterface $request) => $request->query('page')));
            self::assertSame('2', $router->dispatch());
        } finally {
            $_SERVER = $server;
            $_GET = $get;
        }
    }

    public function testAuthorizationIsCheckedBeforeArgumentResolution(): void
    {
        $router = new Router();
        $router->map(Route::get('/', fn (RequestInterface $request) => $request, ['ROLE_ADMIN']));
        $this->expectException(UnauthorizedException::class);
        $router->dispatch(new FakeRequestContext('/'));
    }

    public function testArgumentResolverCanBeReplacedWithoutChangingControllerResolver(): void
    {
        $router = new Router();
        $router->setArgumentResolver(new class implements ArgumentResolverInterface {
            public function resolve(callable $handler, array $routeParameters, RequestContextInterface $context): array
            {
                return ['id' => $routeParameters['id'], 'path' => $context->getPath()];
            }
        });
        $router->map(Route::get('/books/{id}', fn (string $path, string $id) => "$id:$path"));
        self::assertSame('42:/books/42', $router->dispatch(new FakeRequestContext('/books/42')));
    }
}
