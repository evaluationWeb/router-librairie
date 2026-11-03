<?php

declare(strict_types=1);

namespace Mithridatem\Routing\Http;

/**
 * Optional access to complete query-string and form POST parameter collections.
 *
 * Kept separate from RequestInterface to preserve compatibility with existing
 * implementations of the original request contract.
 */
interface ParameterCollectionRequestInterface extends RequestInterface
{
    /** @return array<string, mixed> */
    public function allQuery(): array;

    /** @return array<string, mixed> */
    public function allPost(): array;
}
