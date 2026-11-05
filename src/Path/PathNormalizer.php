<?php

declare(strict_types=1);

namespace Mithridatem\Routing\Path;

/**
 * Normalizes route/request paths and removes an optional application base path.
 */
final class PathNormalizer
{
    public function normalize(string $path): string
    {
        $clean = '/' . ltrim($path, '/');

        return rtrim($clean, '/') ?: '/';
    }

    public function normalizeBasePath(string $basePath): string
    {
        return $basePath === '/' ? '/' : '/' . trim($basePath, '/');
    }

    public function stripBasePath(string $path, string $basePath): string
    {
        $normalized = $this->normalize($path);

        if ($basePath === '/' || $basePath === '') {
            return $normalized;
        }

        if ($normalized === $basePath || str_starts_with($normalized, $basePath . '/')) {
            $stripped = substr($normalized, strlen($basePath)) ?: '/';

            return $this->normalize($stripped);
        }

        return $normalized;
    }
}
