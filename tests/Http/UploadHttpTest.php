<?php

declare(strict_types=1);

namespace Mithridatem\Routing\Tests\Http;

use PHPUnit\Framework\TestCase;

final class UploadHttpTest extends TestCase
{
    public function testRealMultipartUploadsAndLegacyNativeMove(): void
    {
        $directory = sys_get_temp_dir() . '/router-upload-' . bin2hex(random_bytes(8));
        mkdir($directory);
        $destination = $directory . '/stored.txt';
        file_put_contents($destination, 'old contents');
        $log = $directory . '/server.log';
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
        self::assertIsResource($socket, $errorMessage);
        $address = stream_socket_get_name($socket, false);
        fclose($socket);

        $process = proc_open(
            [PHP_BINARY, '-S', $address, __DIR__ . '/../Fixtures/upload-server.php'],
            [0 => ['pipe', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']],
            $pipes,
            null,
            array_merge(getenv(), ['ROUTER_UPLOAD_TEST_DESTINATION' => $destination])
        );
        self::assertIsResource($process);
        fclose($pipes[0]);

        try {
            $ready = false;
            for ($attempt = 0; $attempt < 200; $attempt++) {
                $connection = @stream_socket_client('tcp://' . $address, $errorCode, $errorMessage, 0.1);
                if ($connection !== false) {
                    fclose($connection);
                    $ready = true;
                    break;
                }
                usleep(10000);
            }
            self::assertTrue($ready, 'PHP HTTP server did not start: ' . file_get_contents($log));

            $boundary = 'RouterUploadBoundary';
            $content = "hello upload\n";
            $body = self::field($boundary, 'title', 'Book')
                . self::filePart($boundary, 'cover', 'original.txt', $content)
                . self::filePart($boundary, 'attachments[]', 'one.txt', 'one')
                . self::filePart($boundary, 'attachments[]', 'two.txt', 'two')
                . self::filePart($boundary, 'documents[identity][front]', 'identity.txt', 'id')
                . self::filePart($boundary, 'optional', '', '')
                . '--' . $boundary . "--\r\n";

            $result = self::post($address . '/upload?preview=1', $boundary, $body);
            self::assertTrue($result['globalsUnchanged']);
            self::assertTrue($result['tmpNamePreserved']);
            self::assertSame('original.txt', $result['name']);
            self::assertSame(strlen($content), $result['size']);
            self::assertSame(UPLOAD_ERR_OK, $result['error']);
            self::assertTrue($result['valid']);
            self::assertSame('Book', $result['title']);
            self::assertSame('1', $result['preview']);
            self::assertSame([['one.txt', true], ['two.txt', true]], $result['attachments']);
            self::assertSame('identity.txt', $result['nested']);
            self::assertSame([UPLOAD_ERR_NO_FILE, false], $result['empty']);
            self::assertSame([true, true, true], $result['failedDestinations']);
            self::assertSame($content, $result['content']);
            self::assertSame($content, file_get_contents($destination));
            self::assertFalse($result['validAfterMove']);
            self::assertSame([true, 'original.txt', strlen($content), UPLOAD_ERR_OK], $result['metadataAfterMove']);
            self::assertTrue($result['secondMoveRejected']);

            $result = self::post($address . '/legacy', $boundary, $body);
            self::assertSame([
                'name' => 'original.txt', 'size' => strlen($content), 'error' => UPLOAD_ERR_OK, 'moved' => true,
            ], $result);
            self::assertSame($content, file_get_contents($destination));

            $limitedBody = self::field($boundary, 'MAX_FILE_SIZE', '1')
                . self::filePart($boundary, 'cover', 'original.txt', $content)
                . '--' . $boundary . "--\r\n";
            self::assertSame(
                ['original.txt', '', 0, UPLOAD_ERR_FORM_SIZE, false],
                self::post($address . '/inspect-error', $boundary, $limitedBody)
            );
        } finally {
            proc_terminate($process);
            proc_close($process);
            foreach (glob($directory . '/*') as $path) {
                unlink($path);
            }
            rmdir($directory);
        }
    }

    private static function post(string $address, string $boundary, string $body): array
    {
        $context = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => 'Content-Type: multipart/form-data; boundary=' . $boundary,
            'content' => $body,
            'timeout' => 5,
            'ignore_errors' => true,
        ]]);
        $response = file_get_contents('http://' . $address, false, $context);
        self::assertIsString($response);

        return json_decode($response, true, 512, JSON_THROW_ON_ERROR);
    }

    private static function field(string $boundary, string $name, string $value): string
    {
        return '--' . $boundary . "\r\nContent-Disposition: form-data; name=\"$name\"\r\n\r\n$value\r\n";
    }

    private static function filePart(string $boundary, string $name, string $filename, string $content): string
    {
        return '--' . $boundary . "\r\nContent-Disposition: form-data; name=\"$name\"; filename=\"$filename\"\r\n"
            . "Content-Type: application/octet-stream\r\n\r\n$content\r\n";
    }
}
