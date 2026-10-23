<?php

declare(strict_types=1);

namespace Mithridatem\Routing\Tests\Http;

use Mithridatem\Routing\Http\FileRequestInterface;
use Mithridatem\Routing\Http\Request;
use Mithridatem\Routing\Http\RequestInterface;
use Mithridatem\Routing\Http\UploadedFile;
use Mithridatem\Routing\Http\UploadedFileNormalizer;
use Mithridatem\Routing\Route;
use Mithridatem\Routing\Router;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FileRequestTest extends TestCase
{
    public function testConstructorPreservesExistingPositionalAndNamedArguments(): void
    {
        $request = new Request('/books', 'POST', ['page' => '2'], ['title' => 'PHP'], ['X-Trace' => 'abc'], 'raw');
        self::assertInstanceOf(RequestInterface::class, $request);
        self::assertSame('2', $request->query('page'));
        self::assertSame('PHP', $request->post('title'));
        self::assertSame('abc', $request->header('x-trace'));
        self::assertSame('raw', $request->getContent());
        self::assertSame([], $request->files());
        self::assertNull($request->file('cover'));
        self::assertSame([], $request->files('missing'));
        self::assertSame('json', (new Request('/', content: 'json'))->getContent());
    }

    public function testSingleMultipleAndNestedFilesKeepTheirKeysAndIdentity(): void
    {
        $cover = new UploadedFile('/tmp/one', 'cover.png', 10);
        $empty = new UploadedFile('', '', 0, UPLOAD_ERR_NO_FILE);
        $request = new Request('/', files: [
            'cover' => $cover,
            'attachments' => [2 => $cover, 5 => $empty],
            'documents' => ['identity' => ['front' => $cover]],
        ]);
        self::assertSame($cover, $request->file('cover'));
        self::assertSame([$cover], $request->files('cover'));
        self::assertSame([2 => $cover, 5 => $empty], $request->files('attachments'));
        self::assertSame($cover, $request->files('documents')['identity']['front']);
        self::assertSame($empty, $request->files()['attachments'][5]);
        $this->expectException(\InvalidArgumentException::class);
        $request->file('attachments');
    }

    public function testRawPhpMetadataIsNotAcceptedAsANormalizedConstructorTree(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Request('/', files: ['cover' => self::entry()]);
    }

    public function testGlobalsAreNormalizedWithoutChangingLegacyFilesAccess(): void
    {
        $original = $_FILES;
        try {
            $_FILES = ['cover' => self::entry(), 'optional' => self::entry('', '', 0, UPLOAD_ERR_NO_FILE)];
            $snapshot = $_FILES;
            $request = Request::fromGlobals();
            self::assertSame($snapshot, $_FILES);
            self::assertSame($_FILES['cover']['tmp_name'], $request->file('cover')->getTmpName());
            self::assertSame($_FILES['cover']['name'], $request->file('cover')->getName());
            self::assertSame($_FILES['cover']['size'], $request->file('cover')->getSize());
            self::assertSame($_FILES['cover']['error'], $request->file('cover')->getError());
            self::assertSame(UPLOAD_ERR_NO_FILE, $request->file('optional')->getError());
            self::assertFalse($request->file('optional')->isValid());
            $_FILES['cover']['name'] = 'changed';
            self::assertSame('original.txt', $request->file('cover')->getName());
        } finally {
            $_FILES = $original;
        }
    }

    public function testNormalizerSupportsSparseAndNestedParallelArrays(): void
    {
        $files = UploadedFileNormalizer::fromPhpFiles([
            'attachments' => [
                'tmp_name' => [2 => '/tmp/a', 7 => '/tmp/b'],
                'name' => [7 => 'b.txt', 2 => 'a.txt'],
                'size' => [2 => 1, 7 => 2],
                'error' => [2 => UPLOAD_ERR_OK, 7 => UPLOAD_ERR_PARTIAL],
            ],
            'documents' => [
                'tmp_name' => ['identity' => ['front' => '/tmp/c']],
                'name' => ['identity' => ['front' => 'c.txt']],
                'size' => ['identity' => ['front' => 3]],
                'error' => ['identity' => ['front' => UPLOAD_ERR_OK]],
            ],
        ]);
        self::assertSame([2, 7], array_keys($files['attachments']));
        self::assertSame('a.txt', $files['attachments'][2]->getName());
        self::assertSame(UPLOAD_ERR_PARTIAL, $files['attachments'][7]->getError());
        self::assertSame('/tmp/c', $files['documents']['identity']['front']->getTmpName());
        self::assertSame([], UploadedFileNormalizer::fromPhpFiles([]));
    }

    #[DataProvider('malformedMetadata')]
    public function testMalformedMetadataIsRejectedExplicitly(array $files): void
    {
        $this->expectException(\InvalidArgumentException::class);
        UploadedFileNormalizer::fromPhpFiles($files);
    }

    public static function malformedMetadata(): array
    {
        $entry = self::entry();
        $missing = $entry;
        unset($missing['error']);
        return [
            'not an array' => [['cover' => 'invalid']],
            'missing error' => [['cover' => $missing]],
            'wrong size type' => [['cover' => array_replace($entry, ['size' => '12'])]],
            'negative size' => [['cover' => array_replace($entry, ['size' => -1])]],
            'wrong name type' => [['cover' => array_replace($entry, ['name' => []])]],
            'mixed scalar and array' => [['cover' => array_replace($entry, ['error' => [0]])]],
            'different keys' => [['cover' => [
                'tmp_name' => [0 => '/tmp/a'], 'name' => [1 => 'a'], 'size' => [0 => 1], 'error' => [0 => 0],
            ]]],
            'different lengths' => [['cover' => [
                'tmp_name' => [], 'name' => ['a'], 'size' => [1], 'error' => [0],
            ]]],
        ];
    }

    public function testNewInterfaceIsInjectedAlongsideExistingRequestInterface(): void
    {
        $file = new UploadedFile('/tmp/a', 'a.txt', 1);
        $request = new Request('/books/42', 'POST', files: ['cover' => $file]);
        $router = new Router();
        $router->map(Route::post('/books/{id}', fn (string $id, FileRequestInterface $http, RequestInterface $legacy)
            => [$id, $http->file('cover'), $legacy]));
        self::assertSame(['42', $file, $request], $router->dispatch($request));
    }

    private static function entry(
        string $tmpName = '/tmp/php123',
        string $name = 'original.txt',
        int $size = 12,
        int $error = UPLOAD_ERR_OK
    ): array {
        return ['tmp_name' => $tmpName, 'name' => $name, 'size' => $size, 'error' => $error, 'type' => 'text/plain'];
    }
}
