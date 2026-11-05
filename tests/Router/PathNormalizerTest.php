<?php

declare(strict_types=1);

namespace Mithridatem\Routing\Tests\Router;

use Mithridatem\Routing\Path\PathNormalizer;
use PHPUnit\Framework\TestCase;

final class PathNormalizerTest extends TestCase
{
    public function testNormalizationPreservesExistingSlashBehavior(): void
    {
        $paths = new PathNormalizer();

        self::assertSame('/', $paths->normalize(''));
        self::assertSame('/', $paths->normalize('///'));
        self::assertSame('/books', $paths->normalize('books/'));
        self::assertSame('/books', $paths->normalize('/books/'));
    }

    public function testBasePathIsStrippedOnlyAtSegmentBoundary(): void
    {
        $paths = new PathNormalizer();

        self::assertSame('/books', $paths->stripBasePath('/app/books/', '/app'));
        self::assertSame('/', $paths->stripBasePath('/app', '/app'));
        self::assertSame('/application/books', $paths->stripBasePath('/application/books', '/app'));
        self::assertSame('/books', $paths->stripBasePath('books/', '/'));
    }

    public function testBasePathNormalizationMaintainsExistingBehavior(): void
    {
        $paths = new PathNormalizer();

        self::assertSame('/', $paths->normalizeBasePath('/'));
        self::assertSame('/app', $paths->normalizeBasePath('app/'));
        self::assertSame('/', $paths->normalizeBasePath(''));
    }
}
