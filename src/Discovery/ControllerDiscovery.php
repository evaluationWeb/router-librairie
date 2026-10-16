<?php

declare(strict_types=1);

namespace Mithridatem\Routing\Discovery;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class ControllerDiscovery
{
    /**
     * Discover PSR-4 controller classes under a directory.
     * Composer's autoloader must be registered before calling this method.
     *
     * @return list<class-string>
     */
    public function discover(string $directory, string $namespace): array
    {
        $root = realpath($directory);

        if ($root === false || !is_dir($root) || !is_readable($root)) {
            throw new \InvalidArgumentException(sprintf('Controller directory "%s" is not readable.', $directory));
        }

        $prefix = trim($namespace, '\\');
        if ($prefix === '') {
            throw new \InvalidArgumentException('Controller namespace must not be empty.');
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            /** @var SplFileInfo $file */
            if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
                continue;
            }

            $realFile = $file->getRealPath();
            if ($realFile === false || !str_starts_with($realFile, $root . DIRECTORY_SEPARATOR)) {
                continue;
            }

            $files[] = $realFile;
        }

        sort($files, SORT_STRING);

        $classes = [];
        foreach ($files as $file) {
            $relative = substr($file, strlen($root) + 1);
            $withoutExtension = substr($relative, 0, -4);
            $suffix = str_replace(DIRECTORY_SEPARATOR, '\\', $withoutExtension);
            $class = $prefix . '\\' . $suffix;

            // class_exists triggers Composer's PSR-4 autoloader; never require arbitrary files.
            if (class_exists($class)) {
                $classes[] = $class;
            }
        }

        return $classes;
    }
}
