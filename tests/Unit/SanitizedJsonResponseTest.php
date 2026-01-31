<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit;

use Cline\Cloak\Exceptions\SanitizedException;
use Cline\Cloak\Http\SanitizedJsonResponse;
use RuntimeException;

use function describe;
use function expect;
use function json_decode;
use function test;

describe('SanitizedJsonResponse', function (): void {
    test('creates JSON response from exception', function (): void {
        $exception = new RuntimeException('Something went wrong');
        $response = SanitizedJsonResponse::fromException($exception);

        expect($response->getStatusCode())->toBe(500);

        $data = json_decode($response->getContent(), true);
        expect($data)->toHaveKey('error');
        expect($data['error'])->toBe('Something went wrong');
    });

    test('includes error ID when exception is SanitizedException', function (): void {
        $exception = new SanitizedException(
            message: 'Database error occurred',
            errorId: 'test-error-id-123',
        );

        $response = SanitizedJsonResponse::fromException($exception);

        $data = json_decode($response->getContent(), true);
        expect($data)->toHaveKey('error_id');
        expect($data['error_id'])->toBe('test-error-id-123');
    });

    test('excludes error ID when not available', function (): void {
        $exception = new RuntimeException('Generic error');
        $response = SanitizedJsonResponse::fromException($exception);

        $data = json_decode($response->getContent(), true);
        expect($data)->not->toHaveKey('error_id');
    });

    test('includes sanitized trace when requested', function (): void {
        $sanitizedTrace = [
            ['file' => '/app/[REDACTED]/Service.php', 'line' => 42, 'function' => 'process'],
            ['file' => '/app/[REDACTED]/Controller.php', 'line' => 100, 'function' => 'handle'],
        ];

        $exception = new SanitizedException(
            message: 'Error occurred',
            sanitizedTrace: $sanitizedTrace,
        );

        $response = SanitizedJsonResponse::fromException($exception, includeTrace: true);

        $data = json_decode($response->getContent(), true);
        expect($data)->toHaveKey('trace');
        expect($data['trace'])->toBe($sanitizedTrace);
    });

    test('excludes trace when not requested', function (): void {
        $sanitizedTrace = [
            ['file' => '/app/Service.php', 'line' => 42],
        ];

        $exception = new SanitizedException(
            message: 'Error occurred',
            sanitizedTrace: $sanitizedTrace,
        );

        $response = SanitizedJsonResponse::fromException($exception, includeTrace: false);

        $data = json_decode($response->getContent(), true);
        expect($data)->not->toHaveKey('trace');
    });

    test('respects custom status code', function (): void {
        $exception = new RuntimeException('Bad request');
        $response = SanitizedJsonResponse::fromException($exception, status: 400);

        expect($response->getStatusCode())->toBe(400);
    });

    test('includes custom headers', function (): void {
        $exception = new RuntimeException('Error');
        $response = SanitizedJsonResponse::fromException(
            exception: $exception,
            headers: ['X-Custom-Header' => 'value'],
        );

        expect($response->headers->get('X-Custom-Header'))->toBe('value');
    });
});
