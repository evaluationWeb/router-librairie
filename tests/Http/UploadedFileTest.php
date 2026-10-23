<?php

declare(strict_types=1);

namespace Mithridatem\Routing\Tests\Http;

use Mithridatem\Routing\Exception\FileUploadException;
use Mithridatem\Routing\Http\UploadedFile;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UploadedFileTest extends TestCase
{
    public function testOriginalMetadataIsPreservedWithoutTrustingClientName(): void
    {
        $file = new UploadedFile('/tmp/php123', '../original.txt', 123, UPLOAD_ERR_PARTIAL);
        self::assertSame('/tmp/php123', $file->getTmpName());
        self::assertSame('../original.txt', $file->getName());
        self::assertSame(123, $file->getSize());
        self::assertSame(UPLOAD_ERR_PARTIAL, $file->getError());
        self::assertFalse($file->isValid());
    }

    #[DataProvider('uploadErrors')]
    public function testUploadErrorsPreventMovingAndRemainAvailable(int $error): void
    {
        $file = new UploadedFile('', '', 0, $error);
        self::assertSame($error, $file->getError());
        self::assertFalse($file->isValid());
        $this->expectException(FileUploadException::class);
        $file->moveTo('/unused/destination');
    }

    public static function uploadErrors(): array
    {
        return array_map(static fn (int $error): array => [$error], [
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE, UPLOAD_ERR_PARTIAL,
            UPLOAD_ERR_NO_FILE, UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE,
            UPLOAD_ERR_EXTENSION, 99,
        ]);
    }

    public function testAnOrdinaryLocalFileCannotBeMovedAsAnHttpUpload(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'router-upload-');
        self::assertIsString($source);
        $destination = $source . '-moved';
        file_put_contents($source, 'local');
        try {
            $file = new UploadedFile($source, 'local.txt', 5);
            self::assertFalse($file->isValid());
            try {
                $file->moveTo($destination);
                self::fail('An ordinary local file must not be moved.');
            } catch (FileUploadException $exception) {
                self::assertStringContainsString('not an HTTP upload', $exception->getMessage());
            }
            self::assertSame('local', file_get_contents($source));
            self::assertFileDoesNotExist($destination);
        } finally {
            unlink($source);
        }
    }

    public function testNegativeSizeIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new UploadedFile('', '', -1);
    }
}
