<?php

declare(strict_types=1);

namespace Mithridatem\Routing\Tests\Router;

use Mithridatem\Routing\Auth\ArrayGrantChecker;
use Mithridatem\Routing\Auth\AuthenticationAwareGrantChecker;
use Mithridatem\Routing\Auth\GrantCheckerInterface;
use Mithridatem\Routing\Context\RequestContextInterface;
use Mithridatem\Routing\Exception\UnauthorizedException;
use Mithridatem\Routing\Route;
use Mithridatem\Routing\Router;
use PHPUnit\Framework\TestCase;

final class AuthenticationAwareGrantCheckerTest extends TestCase
{
    public function testGuestOnlyRoute(): void
    {
        self::assertTrue($this->checker(false)->isGranted(['IS_GUEST']));
        self::assertFalse($this->checker(true)->isGranted(['IS_GUEST']));
    }

    public function testAuthenticatedOnlyRoute(): void
    {
        self::assertFalse($this->checker(false)->isGranted(['IS_AUTHENTICATED']));
        self::assertTrue($this->checker(true)->isGranted(['IS_AUTHENTICATED']));
    }

    public function testEmptyAndRoleOnlyGrantsPreserveExistingBehavior(): void
    {
        $checker = $this->checker(false, ['ROLE_USER']);
        self::assertTrue($checker->isGranted([]));
        self::assertTrue($checker->isGranted(['ROLE_ADMIN', 'ROLE_USER']));
        self::assertFalse($checker->isGranted(['ROLE_ADMIN']));
    }

    public function testAuthenticationAndRolesAreBothRequired(): void
    {
        self::assertFalse($this->checker(false, ['ROLE_USER'])->isGranted(['IS_AUTHENTICATED', 'ROLE_USER']));
        self::assertTrue($this->checker(true, ['ROLE_USER'])->isGranted(['IS_AUTHENTICATED', 'ROLE_USER']));
        self::assertFalse($this->checker(true)->isGranted(['IS_AUTHENTICATED', 'ROLE_USER']));
        self::assertTrue($this->checker(false, ['ROLE_USER'])->isGranted(['IS_GUEST', 'ROLE_USER']));
        self::assertFalse($this->checker(true, ['ROLE_USER'])->isGranted(['IS_GUEST', 'ROLE_USER']));
    }

    public function testContradictoryGrantsAreDenied(): void
    {
        self::assertFalse($this->checker(false)->isGranted(['IS_GUEST', 'IS_AUTHENTICATED']));
        self::assertFalse($this->checker(true)->isGranted(['IS_GUEST', 'IS_AUTHENTICATED']));
    }

    public function testAuthenticationIsEvaluatedForEachCheck(): void
    {
        $authenticated = false;
        $checker = new AuthenticationAwareGrantChecker(static function () use (&$authenticated): bool {
            return $authenticated;
        });

        self::assertTrue($checker->isGranted(['IS_GUEST']));
        $authenticated = true;
        self::assertFalse($checker->isGranted(['IS_GUEST']));
    }

    public function testDispatchRejectsLoggedInGuestAndDoesNotInvokeHandler(): void
    {
        $invoked = false;
        $router = new Router();
        $router->setGrantChecker($this->checker(true));
        $router->map(Route::get('/login', static function () use (&$invoked): string {
            $invoked = true;
            return 'login';
        }, ['IS_GUEST']));

        $context = $this->createMock(RequestContextInterface::class);
        $context->method('getPath')->willReturn('/login');
        $context->method('getMethod')->willReturn('GET');

        try {
            $router->dispatch($context);
            self::fail('An authenticated user should be denied access to /login.');
        } catch (UnauthorizedException $exception) {
            self::assertSame(403, $exception->getCode());
            self::assertFalse($invoked);
        }
    }

    public function testDefaultRouterAndLegacyCheckerAreUnchanged(): void
    {
        self::assertTrue((new ArrayGrantChecker([]))->isGranted([]));
        self::assertFalse((new ArrayGrantChecker([]))->isGranted(['IS_GUEST']));
        self::assertFalse((new ArrayGrantChecker([]))->isGranted(['IS_AUTHENTICATED']));
    }

    /** @param list<string> $roles */
    private function checker(bool $authenticated, array $roles = []): GrantCheckerInterface
    {
        return new AuthenticationAwareGrantChecker(
            static fn (): bool => $authenticated,
            new ArrayGrantChecker($roles)
        );
    }
}
