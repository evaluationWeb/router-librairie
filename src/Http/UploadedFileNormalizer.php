<?php

declare(strict_types=1);

namespace Mithridatem\Routing\Http;

final class UploadedFileNormalizer
{
    private const FIELDS = ['tmp_name', 'name', 'size', 'error'];

    /**
     * Convert PHP's parallel $_FILES arrays into an UploadedFile tree.
     * Entries with UPLOAD_ERR_NO_FILE are deliberately retained.
     *
     * @return array<string|int, UploadedFile|array>
     */
    public static function fromPhpFiles(array $files): array
    {
        $normalized = [];
        foreach ($files as $name => $entry) {
            if (!is_array($entry)) {
                throw new \InvalidArgumentException('Each PHP file field must be an array.');
            }
            $normalized[$name] = self::normalizeEntry($entry);
        }

        return $normalized;
    }

    private static function normalizeEntry(array $entry): UploadedFile|array
    {
        foreach (self::FIELDS as $field) {
            if (!array_key_exists($field, $entry)) {
                throw new \InvalidArgumentException('Missing PHP file metadata: ' . $field);
            }
        }

        if (is_array($entry['error'])) {
            foreach (self::FIELDS as $field) {
                if (!is_array($entry[$field]) || count($entry[$field]) !== count($entry['error'])) {
                    throw new \InvalidArgumentException('The PHP file metadata arrays must have matching keys.');
                }
            }

            $files = [];
            foreach ($entry['error'] as $key => $error) {
                $child = [];
                foreach (self::FIELDS as $field) {
                    if (!array_key_exists($key, $entry[$field])) {
                        throw new \InvalidArgumentException('The PHP file metadata arrays must have matching keys.');
                    }
                    $child[$field] = $entry[$field][$key];
                }
                $files[$key] = self::normalizeEntry($child);
            }

            return $files;
        }

        if (
            !is_string($entry['tmp_name']) || !is_string($entry['name'])
            || !is_int($entry['size']) || !is_int($entry['error'])
        ) {
            throw new \InvalidArgumentException('Invalid PHP file metadata types.');
        }

        return new UploadedFile($entry['tmp_name'], $entry['name'], $entry['size'], $entry['error']);
    }
}
