<?php

declare(strict_types=1);

namespace Mithridatem\Routing\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Route
{
    /** @param string|string[] $method
     *  @param string[] $grants
     */
    public function __construct(
        public readonly string $path,
        public readonly string|array $method = 'GET',
        public readonly array $grants = []
    ) {
        if ($path === '') {
            throw new \InvalidArgumentException('Route path must not be empty.');
        }

        $methods = (array) $method;
        if ($methods === []) {
            throw new \InvalidArgumentException('At least one HTTP method is required.');
        }

        foreach ($methods as $httpMethod) {
            if (!is_string($httpMethod) || !preg_match('/^[A-Za-z]+$/D', $httpMethod)) {
                throw new \InvalidArgumentException('Invalid HTTP method.');
            }
        }
    }
}
