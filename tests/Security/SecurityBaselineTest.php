<?php

declare(strict_types=1);

namespace Mithridatem\Routing\Tests\Security;

use Mithridatem\Routing\Auth\ArrayGrantChecker;
use Mithridatem\Routing\Context\RequestContextInterface;
use Mithridatem\Routing\Exception\RouteNotFoundException;
use Mithridatem\Routing\Exception\UnauthorizedException;
use Mithridatem\Routing\Group\RouteGroup;
use Mithridatem\Routing\Http\Request;
use Mithridatem\Routing\Http\UploadedFile;
use Mithridatem\Routing\Middleware\MiddlewareInterface;
use Mithridatem\Routing\Route;
use Mithridatem\Routing\Router;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Security baseline: characterization tests, not a claim that every policy is safe.
 *
 * The tests deliberately preserve current public behavior. In particular:
 * - route grants REPLACE group grants if non-empty;
 * - middleware executes BEFORE authorization of the controller.
 *
 * Both behaviors require an explicit security-policy decision before changing.
 */
final class SecurityBaselineTest extends TestCase
{
    public function testAnonymousRequestCannotInvokeProtectedController(): void
    {
        $executed = false;
        $router = new Router();
        $router->map(Route::get('/admin', static function () use (&$executed): string {
            $executed = true;
            return 'sensitive';
        }, ['ROLE_ADMIN']));

        try {
            $router->dispatch(new Request('/admin'));
            self::fail('A protected controller must not be invoked.');
        } catch (UnauthorizedException) {
            self::assertFalse($executed);
        }
    }

    public function testUserRoleDoesNotSatisfyAdminOnlyRoute(): void
    {
        $router = new Router();
        $router->setGrantChecker(new ArrayGrantChecker(['ROLE_USER']));
        $router->map(Route::get('/admin', static fn (): string => 'sensitive', ['ROLE_ADMIN']));

        $this->expectException(UnauthorizedException::class);
        $router->dispatch(new Request('/admin'));
    }

    public function testGroupGrantProtectsRouteWithoutExplicitGrants(): void
    {
        $router = new Router();
        $router->group('/admin', static function (RouteGroup $group): void {
            $group->grants(['ROLE_ADMIN']);
            $group->map(Route::get('/dashboard', static fn (): string => 'sensitive'));
        });

        $this->expectException(UnauthorizedException::class);
        $router->dispatch(new Request('/admin/dashboard'));
    }

    public function testExplicitRouteGrantsReplaceGroupGrantsCurrentPolicy(): void
    {
        $router = new Router();
        $router->setGrantChecker(new ArrayGrantChecker(['ROLE_USER']));
        $router->group('/admin', static function (RouteGroup $group): void {
            $group->grants(['ROLE_ADMIN']);
            $group->map(Route::get('/report', static fn (): string => 'allowed-by-current-policy', ['ROLE_USER']));
        });

        // WARNING: this is the CURRENT policy, not a recommendation.
        // A caller expecting group grants to be cumulative would be exposed.
        self::assertSame(['ROLE_USER'], $router->getRoutes()->all()[0]->getGrants());
        self::assertSame('allowed-by-current-policy', $router->dispatch(new Request('/admin/report')));
    }

    public function testMiddlewareCannotBypassGrantCheckByCallingNext(): void
    {
        $controllerExecuted = false;
        $router = new Router();
        $router->addMiddleware(new class implements MiddlewareInterface {
            public function process(RequestContextInterface $context, callable $next): mixed
            {
                return $next($context);
            }
        });
        $router->map(Route::get('/secret', static function () use (&$controllerExecuted): string {
            $controllerExecuted = true;
            return 'secret';
        }, ['ROLE_ADMIN']));

        try {
            $router->dispatch(new Request('/secret'));
            self::fail('A middleware calling next() must not bypass authorization.');
        } catch (UnauthorizedException) {
            self::assertFalse($controllerExecuted);
        }
    }

    public function testMiddlewareRunsBeforeAuthorizationCurrentPolicy(): void
    {
        $events = [];
        $router = new Router();
        $router->addMiddleware(new class($events) implements MiddlewareInterface {
            public function __construct(private array &$events) {}
            public function process(RequestContextInterface $context, callable $next): mixed
            {
                $this->events[] = 'middleware-before';
                return $next($context);
            }
        });
        $router->map(Route::get('/secret', static function () use (&$events): string {
            $events[] = 'controller';
            return 'secret';
        }, ['ROLE_ADMIN']));

        try {
            $router->dispatch(new Request('/secret'));
            self::fail('Expected authorization denial.');
        } catch (UnauthorizedException) {
            // WARNING: middleware side effects occur before grants are evaluated.
            self::assertSame(['middleware-before'], $events);
        }
    }

    #[DataProvider('nonMatchingPaths')]
    public function testPrefixAndPathVariationsDoNotMatchProtectedRoute(string $path): void
    {
        $router = new Router();
        $router->setGrantChecker(new ArrayGrantChecker(['ROLE_ADMIN']));
        $router->group('/admin', static function (RouteGroup $group): void {
            $group->grants(['ROLE_ADMIN']);
            $group->map(Route::get('/settings', static fn (): string => 'secret'));
        });

        $this->expectException(RouteNotFoundException::class);
        $router->dispatch(new Request($path));
    }

    public static function nonMatchingPaths(): array
    {
        return [
            'similar prefix' => ['/administrator/settings'],
            'prefix with extra segment' => ['/adminx/settings'],
            'encoded separator as literal' => ['/admin%2Fsettings'],
            'path suffix' => ['/admin/settings-extra'],
            'wrong nested path' => ['/admin/other/settings'],
        ];
    }

    public function testMalformedFileMetadataIsRejectedWithoutMovingFiles(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Request('/upload', 'POST', files: ['payload' => ['tmp_name' => '/tmp/untrusted']]);
    }

    public function testSyntheticUploadCannotBeMovedAsHttpUpload(): void
    {
        $file = new UploadedFile('/tmp/not-an-http-upload-security-baseline', '../shell.php', 1);

        self::assertSame('../shell.php', $file->getName());
        self::assertFalse($file->isValid());
        $this->expectException(\Mithridatem\Routing\Exception\FileUploadException::class);
        $file->moveTo(sys_get_temp_dir() . '/security-baseline-destination');
    }
}
