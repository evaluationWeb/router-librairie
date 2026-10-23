<?php

declare(strict_types=1);

namespace Mithridatem\Routing\Tests\Http;

use Mithridatem\Routing\Http\Request;
use PHPUnit\Framework\TestCase;

final class RequestTest extends TestCase
{
    public function testSourcesRemainSeparateAndMissingValuesUseDefaults(): void
    {
        $request = new Request('/books', 'post', ['title' => 'URL'], ['title' => 'Form']);

        self::assertSame('/books', $request->getPath());
        self::assertSame('POST', $request->getMethod());
        self::assertSame('URL', $request->query('title'));
        self::assertSame('Form', $request->post('title'));
        self::assertSame('1', $request->query('page', '1'));
        self::assertNull($request->post('missing'));
        self::assertSame([], $request->post('missing', []));
    }

    public function testValuesAreNotConvertedFilteredOrEscaped(): void
    {
        $values = ['null' => null, 'zero' => '0', 'empty' => '', 'tags' => ['php'], 'html' => '<b>PHP</b>'];
        $request = new Request('/', query: $values, post: $values);

        foreach ($values as $key => $value) {
            self::assertSame($value, $request->query($key, 'fallback'));
            self::assertSame($value, $request->post($key, 'fallback'));
        }
    }

    public function testHeadersAreCaseInsensitiveAndBodyIsRepeatable(): void
    {
        $request = new Request('/', headers: ['Content-Type' => 'application/json'], content: '{"title":"PHP"}');

        self::assertSame('application/json', $request->header('content-TYPE'));
        self::assertSame('fallback', $request->header('missing', 'fallback'));
        self::assertNull($request->header('missing'));
        self::assertSame('{"title":"PHP"}', $request->getContent());
        self::assertSame('{"title":"PHP"}', $request->getContent());
        self::assertNull($request->post('title'));
    }

    public function testBearerTokenRetainsNativeContextSemantics(): void
    {
        self::assertSame('token', (new Request('/', headers: ['authorization' => 'bEaReR token ']))->getBearerToken());
        self::assertNull((new Request('/'))->getBearerToken());
        self::assertNull((new Request('/', headers: ['Authorization' => 'Basic abc']))->getBearerToken());
    }

    public function testFromGlobalsTakesASnapshotAndExtractsServerHeaders(): void
    {
        $server = $_SERVER;
        $get = $_GET;
        $post = $_POST;
        try {
            $_SERVER = [
                'REQUEST_URI' => '/books?page=2',
                'REQUEST_METHOD' => 'post',
                'HTTP_AUTHORIZATION' => 'Bearer abc',
                'HTTP_X_TRACE_ID' => 'trace',
                'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
                'CONTENT_LENGTH' => '9',
                'HTTP_INVALID' => ['ignored'],
            ];
            $_GET = ['page' => '2'];
            $_POST = ['title' => 'PHP'];
            $request = Request::fromGlobals();
            $_GET['page'] = '3';
            $_POST['title'] = 'Changed';
            $_SERVER['HTTP_X_TRACE_ID'] = 'changed';

            self::assertSame('/books', $request->getPath());
            self::assertSame('POST', $request->getMethod());
            self::assertSame('abc', $request->getBearerToken());
            self::assertSame('trace', $request->header('x-trace-id'));
            self::assertSame('application/x-www-form-urlencoded', $request->header('content-type'));
            self::assertSame('9', $request->header('content-length'));
            self::assertNull($request->header('invalid'));
            self::assertSame('2', $request->query('page'));
            self::assertSame('PHP', $request->post('title'));
        } finally {
            $_SERVER = $server;
            $_GET = $get;
            $_POST = $post;
        }
    }

    public function testFromGlobalsUsesDefaultPathAndMethod(): void
    {
        $server = $_SERVER;
        try {
            $_SERVER = [];
            $request = Request::fromGlobals();
            self::assertSame('/', $request->getPath());
            self::assertSame('GET', $request->getMethod());
        } finally {
            $_SERVER = $server;
        }
    }
}
