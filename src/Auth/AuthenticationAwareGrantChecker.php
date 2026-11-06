<?php

declare(strict_types=1);

namespace Mithridatem\Routing\Auth;

use Closure;

/**
 * Optional authorization checker that understands authentication state.
 *
 * Ordinary role grants are delegated to an existing GrantCheckerInterface.
 * The authentication provider is deliberately independent of sessions/JWT.
 */
final class AuthenticationAwareGrantChecker implements GrantCheckerInterface
{
    public const IS_GUEST = 'IS_GUEST';
    public const IS_AUTHENTICATED = 'IS_AUTHENTICATED';

    /** @var Closure(): bool */
    private Closure $isAuthenticated;

    private GrantCheckerInterface $roleChecker;

    /**
     * @param callable(): bool $isAuthenticated
     */
    public function __construct(callable $isAuthenticated, ?GrantCheckerInterface $roleChecker = null)
    {
        $this->isAuthenticated = Closure::fromCallable($isAuthenticated);
        $this->roleChecker = $roleChecker ?? new ArrayGrantChecker([]);
    }

    public function isGranted(array $requiredGrants): bool
    {
        $guestOnly = in_array(self::IS_GUEST, $requiredGrants, true);
        $authenticatedOnly = in_array(self::IS_AUTHENTICATED, $requiredGrants, true);

        // Contradictory authentication requirements never authorize access.
        if ($guestOnly && $authenticatedOnly) {
            return false;
        }

        if ($guestOnly || $authenticatedOnly) {
            $authenticated = ($this->isAuthenticated)();
            if ($guestOnly && $authenticated) {
                return false;
            }
            if ($authenticatedOnly && !$authenticated) {
                return false;
            }
        }

        $roleGrants = array_values(array_filter(
            $requiredGrants,
            static fn (mixed $grant): bool => $grant !== self::IS_GUEST && $grant !== self::IS_AUTHENTICATED
        ));

        // Authentication requirements are ANDed with the existing OR-of-roles behavior.
        return $this->roleChecker->isGranted($roleGrants);
    }
}
