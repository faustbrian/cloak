<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\Http\Formatters;

use Cline\Cloak\Exceptions\SanitizedException;
use Cline\Cloak\Http\Formatters\JsonApiFormatter;
use RuntimeException;

use function describe;
use function expect;
use function json_decode;
use function test;

describe('JsonApiFormatter', function (): void {
    describe('format()', function (): void {
        test('formats exception according to JSON:API spec with default status', function (): void {
            $formatter = new JsonApiFormatter();
            $exception = new RuntimeException('Internal server error');

            $response = $formatter->format($exception);

            expect($response->getStatusCode())->toBe(500);
            expect($response->headers->get('Content-Type'))->toContain('application/vnd.api+json');

            $data = json_decode($response->getContent(), true);
            expect($data)->toHaveKey('errors');
            expect($data['errors'])->toBeArray();
            expect($data['errors'][0])->toHaveKeys(['status', 'title', 'detail']);
            expect($data['errors'][0]['status'])->toBe('500');
            expect($data['errors'][0]['title'])->toBe('Internal Server Error');
            expect($data['errors'][0]['detail'])->toBe('Internal server error');
        });

        test('formats exception with custom status code', function (): void {
            $formatter = new JsonApiFormatter();
            $exception = new RuntimeException('Validation failed');

            $response = $formatter->format($exception, status: 422);

            expect($response->getStatusCode())->toBe(422);

            $data = json_decode($response->getContent(), true);
            expect($data['errors'][0]['status'])->toBe('422');
            expect($data['errors'][0]['title'])->toBe('Unprocessable Entity');
            expect($data['errors'][0]['detail'])->toBe('Validation failed');
        });

        test('includes custom headers', function (): void {
            $formatter = new JsonApiFormatter();
            $exception = new RuntimeException('Error');

            $response = $formatter->format(
                $exception,
                headers: ['X-Custom-Header' => 'test-value'],
            );

            expect($response->headers->get('X-Custom-Header'))->toBe('test-value');
            expect($response->headers->get('Content-Type'))->toContain('application/vnd.api+json');
        });

        test('includes error ID when exception provides one', function (): void {
            $formatter = new JsonApiFormatter();
            $exception = new SanitizedException(
                message: 'Something went wrong',
                errorId: 'error-abc-123',
            );

            $response = $formatter->format($exception);

            $data = json_decode($response->getContent(), true);
            expect($data['errors'][0])->toHaveKey('id');
            expect($data['errors'][0]['id'])->toBe('error-abc-123');
        });

        test('does not include error ID when exception has none', function (): void {
            $formatter = new JsonApiFormatter();
            $exception = new RuntimeException('Error');

            $response = $formatter->format($exception);

            $data = json_decode($response->getContent(), true);
            expect($data['errors'][0])->not->toHaveKey('id');
        });

        test('does not include error ID when SanitizedException has null errorId', function (): void {
            $formatter = new JsonApiFormatter();
            $exception = new SanitizedException(
                message: 'Error',
                errorId: null,
            );

            $response = $formatter->format($exception);

            $data = json_decode($response->getContent(), true);
            expect($data['errors'][0])->not->toHaveKey('id');
        });

        test('includes error code when exception code is non-zero', function (): void {
            $formatter = new JsonApiFormatter();
            $exception = new RuntimeException('Database connection failed', 1_234);

            $response = $formatter->format($exception);

            $data = json_decode($response->getContent(), true);
            expect($data['errors'][0])->toHaveKey('code');
            expect($data['errors'][0]['code'])->toBe('1234');
        });

        test('does not include error code when exception code is zero', function (): void {
            $formatter = new JsonApiFormatter();
            $exception = new RuntimeException('Error', 0);

            $response = $formatter->format($exception);

            $data = json_decode($response->getContent(), true);
            expect($data['errors'][0])->not->toHaveKey('code');
        });

        test('includes trace in meta when requested with SanitizedException', function (): void {
            $formatter = new JsonApiFormatter();
            $trace = [
                ['file' => '/app/Service.php', 'line' => 42, 'function' => 'process'],
                ['file' => '/app/Controller.php', 'line' => 18, 'function' => 'handle'],
            ];
            $exception = new SanitizedException(
                message: 'Processing failed',
                sanitizedTrace: $trace,
            );

            $response = $formatter->format($exception, includeTrace: true);

            $data = json_decode($response->getContent(), true);
            expect($data['errors'][0])->toHaveKey('meta');
            expect($data['errors'][0]['meta'])->toHaveKey('trace');
            expect($data['errors'][0]['meta']['trace'])->toBe($trace);
        });

        test('does not include trace when includeTrace is false', function (): void {
            $formatter = new JsonApiFormatter();
            $trace = [['file' => '/app/Service.php', 'line' => 42]];
            $exception = new SanitizedException(
                message: 'Error',
                sanitizedTrace: $trace,
            );

            $response = $formatter->format($exception, includeTrace: false);

            $data = json_decode($response->getContent(), true);
            expect($data['errors'][0])->not->toHaveKey('meta');
        });

        test('does not include trace when exception is not SanitizedException', function (): void {
            $formatter = new JsonApiFormatter();
            $exception = new RuntimeException('Error');

            $response = $formatter->format($exception, includeTrace: true);

            $data = json_decode($response->getContent(), true);
            expect($data['errors'][0])->not->toHaveKey('meta');
        });

        test('includes all fields when all conditions are met', function (): void {
            $formatter = new JsonApiFormatter();
            $trace = [['file' => '/app/Service.php', 'line' => 42]];
            $exception = new SanitizedException(
                message: 'Complex error',
                code: 9_999,
                errorId: 'complex-error-id',
                sanitizedTrace: $trace,
            );

            $response = $formatter->format($exception, status: 422, includeTrace: true);

            $data = json_decode($response->getContent(), true);
            expect($data['errors'][0])->toHaveKeys(['status', 'title', 'detail', 'id', 'code', 'meta']);
            expect($data['errors'][0]['status'])->toBe('422');
            expect($data['errors'][0]['title'])->toBe('Unprocessable Entity');
            expect($data['errors'][0]['detail'])->toBe('Complex error');
            expect($data['errors'][0]['id'])->toBe('complex-error-id');
            expect($data['errors'][0]['code'])->toBe('9999');
            expect($data['errors'][0]['meta']['trace'])->toBe($trace);
        });
    });

    describe('getContentType()', function (): void {
        test('returns correct JSON:API content type', function (): void {
            $formatter = new JsonApiFormatter();

            expect($formatter->getContentType())->toBe('application/vnd.api+json');
        });
    });

    describe('getTitle() - HTTP Status Codes', function (): void {
        test('returns "Bad Request" for 400', function (): void {
            $formatter = new JsonApiFormatter();
            $exception = new RuntimeException('Bad request');

            $response = $formatter->format($exception, status: 400);

            $data = json_decode($response->getContent(), true);
            expect($data['errors'][0]['title'])->toBe('Bad Request');
        });

        test('returns "Unauthorized" for 401', function (): void {
            $formatter = new JsonApiFormatter();
            $exception = new RuntimeException('Unauthorized');

            $response = $formatter->format($exception, status: 401);

            $data = json_decode($response->getContent(), true);
            expect($data['errors'][0]['title'])->toBe('Unauthorized');
        });

        test('returns "Forbidden" for 403', function (): void {
            $formatter = new JsonApiFormatter();
            $exception = new RuntimeException('Forbidden');

            $response = $formatter->format($exception, status: 403);

            $data = json_decode($response->getContent(), true);
            expect($data['errors'][0]['title'])->toBe('Forbidden');
        });

        test('returns "Not Found" for 404', function (): void {
            $formatter = new JsonApiFormatter();
            $exception = new RuntimeException('Not found');

            $response = $formatter->format($exception, status: 404);

            $data = json_decode($response->getContent(), true);
            expect($data['errors'][0]['title'])->toBe('Not Found');
        });

        test('returns "Method Not Allowed" for 405', function (): void {
            $formatter = new JsonApiFormatter();
            $exception = new RuntimeException('Method not allowed');

            $response = $formatter->format($exception, status: 405);

            $data = json_decode($response->getContent(), true);
            expect($data['errors'][0]['title'])->toBe('Method Not Allowed');
        });

        test('returns "Conflict" for 409', function (): void {
            $formatter = new JsonApiFormatter();
            $exception = new RuntimeException('Conflict');

            $response = $formatter->format($exception, status: 409);

            $data = json_decode($response->getContent(), true);
            expect($data['errors'][0]['title'])->toBe('Conflict');
        });

        test('returns "Unprocessable Entity" for 422', function (): void {
            $formatter = new JsonApiFormatter();
            $exception = new RuntimeException('Unprocessable entity');

            $response = $formatter->format($exception, status: 422);

            $data = json_decode($response->getContent(), true);
            expect($data['errors'][0]['title'])->toBe('Unprocessable Entity');
        });

        test('returns "Too Many Requests" for 429', function (): void {
            $formatter = new JsonApiFormatter();
            $exception = new RuntimeException('Too many requests');

            $response = $formatter->format($exception, status: 429);

            $data = json_decode($response->getContent(), true);
            expect($data['errors'][0]['title'])->toBe('Too Many Requests');
        });

        test('returns "Internal Server Error" for 500', function (): void {
            $formatter = new JsonApiFormatter();
            $exception = new RuntimeException('Internal server error');

            $response = $formatter->format($exception, status: 500);

            $data = json_decode($response->getContent(), true);
            expect($data['errors'][0]['title'])->toBe('Internal Server Error');
        });

        test('returns "Bad Gateway" for 502', function (): void {
            $formatter = new JsonApiFormatter();
            $exception = new RuntimeException('Bad gateway');

            $response = $formatter->format($exception, status: 502);

            $data = json_decode($response->getContent(), true);
            expect($data['errors'][0]['title'])->toBe('Bad Gateway');
        });

        test('returns "Service Unavailable" for 503', function (): void {
            $formatter = new JsonApiFormatter();
            $exception = new RuntimeException('Service unavailable');

            $response = $formatter->format($exception, status: 503);

            $data = json_decode($response->getContent(), true);
            expect($data['errors'][0]['title'])->toBe('Service Unavailable');
        });

        test('returns "Error" for unknown status code', function (): void {
            $formatter = new JsonApiFormatter();
            $exception = new RuntimeException('Unknown error');

            $response = $formatter->format($exception, status: 418); // I'm a teapot

            $data = json_decode($response->getContent(), true);
            expect($data['errors'][0]['title'])->toBe('Error');
        });

        test('returns "Error" for custom 5xx status code', function (): void {
            $formatter = new JsonApiFormatter();
            $exception = new RuntimeException('Custom server error');

            $response = $formatter->format($exception, status: 599);

            $data = json_decode($response->getContent(), true);
            expect($data['errors'][0]['title'])->toBe('Error');
        });

        test('returns "Error" for custom 4xx status code', function (): void {
            $formatter = new JsonApiFormatter();
            $exception = new RuntimeException('Custom client error');

            $response = $formatter->format($exception, status: 499);

            $data = json_decode($response->getContent(), true);
            expect($data['errors'][0]['title'])->toBe('Error');
        });
    });
});
