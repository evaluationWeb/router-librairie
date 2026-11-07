<?php

declare(strict_types=1);

namespace Mithridatem\Routing\Tests\Security;

use Mithridatem\Routing\Auth\ArrayGrantChecker;
use Mithridatem\Routing\Exception\RouteNotFoundException;
use Mithridatem\Routing\Exception\UnauthorizedException;
use Mithridatem\Routing\Http\Request;
use Mithridatem\Routing\Http\UploadedFileNormalizer;
use Mithridatem\Routing\Route;
use Mithridatem\Routing\Router;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Synthetic request tests complement tests/security-http.sh (real HTTP).
 * They do not assume that a reverse proxy decodes or canonicalizes paths.
 */
final class AdversarialInputTest extends TestCase
{
    #[DataProvider('encodedDynamicSegments')]
    public function testUntrustedEncodedDynamicSegmentNeverSkipsRouteGrants(string $path): void
    {
        $invoked = false;
        $router = new Router();
        $router->setGrantChecker(new ArrayGrantChecker(['ROLE_USER']));
        $router->map(Route::get('/admin/{id}', static function () use (&$invoked): string {
            $invoked = true;
            return 'secret';
        }, ['ROLE_ADMIN']));

        try {
            $router->dispatch(new Request($path));
            self::fail('Expected the grant checker to reject this request.');
        } catch (UnauthorizedException) {
            self::assertFalse($invoked);
        }
    }

    public static function encodedDynamicSegments(): array
    {
        return [
            'encoded slash' => ['/admin/%2F'],
            'double encoded slash' => ['/admin/%252F'],
            'encoded dot segment' => ['/admin/%2E%2E'],
            'encoded null-byte string' => ['/admin/%00'],
        ];
    }

    #[DataProvider('nonMatchingUntrustedPaths')]
    public function testUnexpectedEncodedPrefixesDoNotMatchProtectedRoute(string $path): void
    {
        $router = new Router();
        $router->setGrantChecker(new ArrayGrantChecker(['ROLE_ADMIN']));
        $router->map(Route::get('/admin/{id}', static fn (): string => 'secret', ['ROLE_ADMIN']));

        $this->expectException(RouteNotFoundException::class);
        $router->dispatch(new Request($path));
    }

    public static function nonMatchingUntrustedPaths(): array
    {
        return [
            'encoded prefix' => ['/%61dmin/42'],
            'double encoded prefix' => ['/%2561dmin/42'],
            'additional slash' => ['/admin//42'],
            'trailing segment' => ['/admin/42/extra'],
            'dot relative segment' => ['/admin/../42'],
            'path prefix confusion' => ['/administrator/42'],
        ];
    }

    public function testArrayShapedQueryAndFormInputDoNotOverrideRouteAuthorization(): void
    {
        $invoked = false;
        $router = new Router();
        $router->map(Route::post('/update/{id}', static function () use (&$invoked): string {
            $invoked = true;
            return 'modified';
        }, ['ROLE_ADMIN']));

        $request = new Request(
            '/update/42',
            'POST',
            ['grant' => ['ROLE_ADMIN']],
            ['id' => ['99'], 'role' => ['ROLE_ADMIN'], 'csrf_token' => ['unexpected']]
        );

        try {
            $router->dispatch($request);
            self::fail('User-controlled form values must not authorize a route.');
        } catch (UnauthorizedException) {
            self::assertFalse($invoked);
        }
    }

    #[DataProvider('badUploadShapes')]
    public function testMalformedParallelUploadMetadataIsRejected(array $files): void
    {
        $this->expectException(\InvalidArgumentException::class);
        UploadedFileNormalizer::fromPhpFiles($files);
    }

    public static function badUploadShapes(): array
    {
        $valid = [
            'name' => 'example.txt',
            'tmp_name' => '/tmp/php-test',
            'size' => 3,
            'error' => UPLOAD_ERR_OK,
        ];

        return [
            'wrong error type' => [['cover' => array_replace($valid, ['error' => '0'])]],
            'wrong tmp path type' => [['cover' => array_replace($valid, ['tmp_name' => ['nested']])]],
            'negative file size' => [['cover' => array_replace($valid, ['size' => -1])]],
            'non-array file entry' => [['cover' => 'unexpected']],
            'mismatched nested keys' => [['cover' => [
                'name' => [0 => 'one.txt'],
                'tmp_name' => [1 => '/tmp/a'],
                'size' => [0 => 3],
                'error' => [0 => UPLOAD_ERR_OK],
            ]]],
        ];
    }
}
