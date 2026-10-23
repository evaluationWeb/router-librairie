<?php

declare(strict_types=1);

namespace Mithridatem\Routing\Http;

interface FileRequestInterface extends RequestInterface
{
    /**
     * Return a single top-level file, or null when the field is absent.
     * @throws \InvalidArgumentException If the field contains multiple or nested files.
     */
    public function file(string $name): ?UploadedFile;

    /**
     * Return the complete file tree or the files of one top-level field.
     * Nested arrays retain their original keys. A single file becomes [$file].
     *
     * @return array<string|int, UploadedFile|array>
     */
    public function files(?string $name = null): array;
}
