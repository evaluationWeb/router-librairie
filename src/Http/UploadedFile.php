<?php

declare(strict_types=1);

namespace Mithridatem\Routing\Http;

use Mithridatem\Routing\Exception\FileUploadException;

final class UploadedFile
{
    private bool $moved = false;

    public function __construct(
        private readonly string $tmpName,
        private readonly string $name,
        private readonly int $size,
        private readonly int $error = UPLOAD_ERR_OK
    ) {
        if ($size < 0) {
            throw new \InvalidArgumentException('The uploaded file size cannot be negative.');
        }
    }

    public function getTmpName(): string
    {
        return $this->tmpName;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getError(): int
    {
        return $this->error;
    }

    public function isValid(): bool
    {
        return !$this->moved
            && $this->error === UPLOAD_ERR_OK
            && is_uploaded_file($this->tmpName);
    }

    /** Move a real HTTP upload; metadata getters continue to expose original values. */
    public function moveTo(string $destination): void
    {
        if (!$this->isValid()) {
            throw new FileUploadException(sprintf(
                'Cannot move this file: upload error %d, file already moved or not an HTTP upload.',
                $this->error
            ));
        }

        if ($destination === '' || str_contains($destination, "\0")) {
            throw new FileUploadException('The upload destination must be a non-empty path without null bytes.');
        }

        if (!@move_uploaded_file($this->tmpName, $destination)) {
            throw new FileUploadException('Unable to move the uploaded file to the destination.');
        }

        $this->moved = true;
    }
}
