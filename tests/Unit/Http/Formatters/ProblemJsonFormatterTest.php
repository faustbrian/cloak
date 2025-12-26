<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\Http\Formatters;

use Cline\Cloak\Exceptions\SanitizedException;
use Cline\Cloak\Http\Formatters\ProblemJsonFormatter;
use RuntimeException;

use function describe;
use function expect;
use function json_decode;
use function test;

describe('ProblemJsonFormatter', function (): void {
    describe('format method', function (): void {
        test('formats basic exception according to RFC 7807', function (): void {
            // Arrange
            $formatter = new ProblemJsonFormatter();
            $exception = new RuntimeException('Something went wrong');

            // Act
            $response = $formatter->format($exception, status: 500);

            // Assert
            expect($response->getStatusCode())->toBe(500);
            expect($response->headers->get('Content-Type'))->toBe('application/problem+json');

            $data = json_decode($response->getContent(), true);
            expect($data)->toHaveKeys(['type', 'title', 'status', 'detail']);
            expect($data['type'])->toBe('about:blank');
            expect($data['title'])->toBe('Internal Server Error');
            expect($data['status'])->toBe(500);
            expect($data['detail'])->toBe('Something went wrong');
        });

        test('formats exception with custom status code', function (): void {
            // Arrange
            $formatter = new ProblemJsonFormatter();
            $exception = new RuntimeException('Not found');

            // Act
            $response = $formatter->format($exception, status: 404);

            // Assert
            $data = json_decode($response->getContent(), true);
            expect($data['title'])->toBe('Not Found');
            expect($data['status'])->toBe(404);
        });

        test('includes custom headers', function (): void {
            // Arrange
            $formatter = new ProblemJsonFormatter();
            $exception = new RuntimeException('Error');
            $headers = ['X-Custom-Header' => 'CustomValue'];

            // Act
            $response = $formatter->format($exception, headers: $headers);

            // Assert
            expect($response->headers->get('X-Custom-Header'))->toBe('CustomValue');
            expect($response->headers->get('Content-Type'))->toBe('application/problem+json');
        });
    });

    describe('getTitle method - all status codes', function (): void {
        test('returns correct title for 400 Bad Request', function (): void {
            // Arrange
            $formatter = new ProblemJsonFormatter();
            $exception = new RuntimeException('Bad request');

            // Act
            $response = $formatter->format($exception, status: 400);

            // Assert
            $data = json_decode($response->getContent(), true);
            expect($data['title'])->toBe('Bad Request');
        });

        test('returns correct title for 401 Unauthorized', function (): void {
            // Arrange
            $formatter = new ProblemJsonFormatter();
            $exception = new RuntimeException('Unauthorized');

            // Act
            $response = $formatter->format($exception, status: 401);

            // Assert
            $data = json_decode($response->getContent(), true);
            expect($data['title'])->toBe('Unauthorized');
        });

        test('returns correct title for 403 Forbidden', function (): void {
            // Arrange
            $formatter = new ProblemJsonFormatter();
            $exception = new RuntimeException('Forbidden');

            // Act
            $response = $formatter->format($exception, status: 403);

            // Assert
            $data = json_decode($response->getContent(), true);
            expect($data['title'])->toBe('Forbidden');
        });

        test('returns correct title for 404 Not Found', function (): void {
            // Arrange
            $formatter = new ProblemJsonFormatter();
            $exception = new RuntimeException('Not found');

            // Act
            $response = $formatter->format($exception, status: 404);

            // Assert
            $data = json_decode($response->getContent(), true);
            expect($data['title'])->toBe('Not Found');
        });

        test('returns correct title for 405 Method Not Allowed', function (): void {
            // Arrange
            $formatter = new ProblemJsonFormatter();
            $exception = new RuntimeException('Method not allowed');

            // Act
            $response = $formatter->format($exception, status: 405);

            // Assert
            $data = json_decode($response->getContent(), true);
            expect($data['title'])->toBe('Method Not Allowed');
        });

        test('returns correct title for 409 Conflict', function (): void {
            // Arrange
            $formatter = new ProblemJsonFormatter();
            $exception = new RuntimeException('Conflict');

            // Act
            $response = $formatter->format($exception, status: 409);

            // Assert
            $data = json_decode($response->getContent(), true);
            expect($data['title'])->toBe('Conflict');
        });

        test('returns correct title for 422 Unprocessable Entity', function (): void {
            // Arrange
            $formatter = new ProblemJsonFormatter();
            $exception = new RuntimeException('Validation failed');

            // Act
            $response = $formatter->format($exception, status: 422);

            // Assert
            $data = json_decode($response->getContent(), true);
            expect($data['title'])->toBe('Unprocessable Entity');
        });

        test('returns correct title for 429 Too Many Requests', function (): void {
            // Arrange
            $formatter = new ProblemJsonFormatter();
            $exception = new RuntimeException('Rate limited');

            // Act
            $response = $formatter->format($exception, status: 429);

            // Assert
            $data = json_decode($response->getContent(), true);
            expect($data['title'])->toBe('Too Many Requests');
        });

        test('returns correct title for 500 Internal Server Error', function (): void {
            // Arrange
            $formatter = new ProblemJsonFormatter();
            $exception = new RuntimeException('Server error');

            // Act
            $response = $formatter->format($exception, status: 500);

            // Assert
            $data = json_decode($response->getContent(), true);
            expect($data['title'])->toBe('Internal Server Error');
        });

        test('returns correct title for 502 Bad Gateway', function (): void {
            // Arrange
            $formatter = new ProblemJsonFormatter();
            $exception = new RuntimeException('Bad gateway');

            // Act
            $response = $formatter->format($exception, status: 502);

            // Assert
            $data = json_decode($response->getContent(), true);
            expect($data['title'])->toBe('Bad Gateway');
        });

        test('returns correct title for 503 Service Unavailable', function (): void {
            // Arrange
            $formatter = new ProblemJsonFormatter();
            $exception = new RuntimeException('Service unavailable');

            // Act
            $response = $formatter->format($exception, status: 503);

            // Assert
            $data = json_decode($response->getContent(), true);
            expect($data['title'])->toBe('Service Unavailable');
        });

        test('returns default title for unknown status code', function (): void {
            // Arrange
            $formatter = new ProblemJsonFormatter();
            $exception = new RuntimeException('Unknown error');

            // Act
            $response = $formatter->format($exception, status: 418);

            // Assert
            $data = json_decode($response->getContent(), true);
            expect($data['title'])->toBe('Error');
        });
    });

    describe('error ID handling', function (): void {
        test('includes error ID as instance URN when SanitizedException has error ID', function (): void {
            // Arrange
            $formatter = new ProblemJsonFormatter();
            $exception = new SanitizedException(
                message: 'Something failed',
                errorId: 'f47ac10b-58cc-4372-a567-0e02b2c3d479',
            );

            // Act
            $response = $formatter->format($exception);

            // Assert
            $data = json_decode($response->getContent(), true);
            expect($data)->toHaveKey('instance');
            expect($data['instance'])->toBe('urn:uuid:f47ac10b-58cc-4372-a567-0e02b2c3d479');
        });

        test('does not include instance when SanitizedException has null error ID', function (): void {
            // Arrange
            $formatter = new ProblemJsonFormatter();
            $exception = new SanitizedException(
                message: 'Something failed',
                errorId: null,
            );

            // Act
            $response = $formatter->format($exception);

            // Assert
            $data = json_decode($response->getContent(), true);
            expect($data)->not->toHaveKey('instance');
        });

        test('does not include instance when exception is not SanitizedException', function (): void {
            // Arrange
            $formatter = new ProblemJsonFormatter();
            $exception = new RuntimeException('Regular exception');

            // Act
            $response = $formatter->format($exception);

            // Assert
            $data = json_decode($response->getContent(), true);
            expect($data)->not->toHaveKey('instance');
        });

        test('includes error ID with empty string', function (): void {
            // Arrange
            $formatter = new ProblemJsonFormatter();
            $exception = new SanitizedException(
                message: 'Error',
                errorId: '',
            );

            // Act
            $response = $formatter->format($exception);

            // Assert
            $data = json_decode($response->getContent(), true);
            // Empty string is falsy, so instance should not be included
            expect($data)->not->toHaveKey('instance');
        });
    });

    describe('trace handling', function (): void {
        test('includes sanitized trace when includeTrace is true and exception is SanitizedException', function (): void {
            // Arrange
            $formatter = new ProblemJsonFormatter();
            $trace = [
                ['file' => '/app/Service.php', 'line' => 42],
                ['file' => '/app/Controller.php', 'line' => 100, 'function' => 'handleRequest'],
            ];
            $exception = new SanitizedException(
                message: 'Error occurred',
                sanitizedTrace: $trace,
            );

            // Act
            $response = $formatter->format($exception, includeTrace: true);

            // Assert
            $data = json_decode($response->getContent(), true);
            expect($data)->toHaveKey('trace');
            expect($data['trace'])->toBe($trace);
        });

        test('does not include trace when includeTrace is false', function (): void {
            // Arrange
            $formatter = new ProblemJsonFormatter();
            $trace = [['file' => '/app/Service.php', 'line' => 42]];
            $exception = new SanitizedException(
                message: 'Error',
                sanitizedTrace: $trace,
            );

            // Act
            $response = $formatter->format($exception, includeTrace: false);

            // Assert
            $data = json_decode($response->getContent(), true);
            expect($data)->not->toHaveKey('trace');
        });

        test('does not include trace when exception is not SanitizedException', function (): void {
            // Arrange
            $formatter = new ProblemJsonFormatter();
            $exception = new RuntimeException('Regular exception');

            // Act
            $response = $formatter->format($exception, includeTrace: true);

            // Assert
            $data = json_decode($response->getContent(), true);
            expect($data)->not->toHaveKey('trace');
        });

        test('includes trace with empty array when SanitizedException has no trace', function (): void {
            // Arrange
            $formatter = new ProblemJsonFormatter();
            $exception = new SanitizedException(
                message: 'Error',
                sanitizedTrace: [],
            );

            // Act
            $response = $formatter->format($exception, includeTrace: true);

            // Assert
            $data = json_decode($response->getContent(), true);
            expect($data)->toHaveKey('trace');
            expect($data['trace'])->toBe([]);
        });
    });

    describe('combined scenarios', function (): void {
        test('includes both error ID and trace when available', function (): void {
            // Arrange
            $formatter = new ProblemJsonFormatter();
            $trace = [['file' => '/app/Service.php', 'line' => 42]];
            $exception = new SanitizedException(
                message: 'Complex error',
                errorId: 'abc-123-def',
                sanitizedTrace: $trace,
            );

            // Act
            $response = $formatter->format($exception, status: 422, includeTrace: true);

            // Assert
            $data = json_decode($response->getContent(), true);
            expect($data)->toHaveKeys(['type', 'title', 'status', 'detail', 'instance', 'trace']);
            expect($data['instance'])->toBe('urn:uuid:abc-123-def');
            expect($data['trace'])->toBe($trace);
            expect($data['title'])->toBe('Unprocessable Entity');
        });

        test('formats complete response with all optional fields', function (): void {
            // Arrange
            $formatter = new ProblemJsonFormatter();
            $trace = [
                ['file' => '/app/Service.php', 'line' => 42, 'function' => 'process'],
                ['file' => '/app/Controller.php', 'line' => 100, 'class' => 'ApiController'],
            ];
            $exception = new SanitizedException(
                message: 'Validation failed for user input',
                errorId: 'validation-error-12345',
                sanitizedTrace: $trace,
            );
            $headers = ['X-Request-Id' => 'req-123'];

            // Act
            $response = $formatter->format(
                $exception,
                status: 422,
                includeTrace: true,
                headers: $headers,
            );

            // Assert
            expect($response->getStatusCode())->toBe(422);
            expect($response->headers->get('Content-Type'))->toBe('application/problem+json');
            expect($response->headers->get('X-Request-Id'))->toBe('req-123');

            $data = json_decode($response->getContent(), true);
            expect($data['type'])->toBe('about:blank');
            expect($data['title'])->toBe('Unprocessable Entity');
            expect($data['status'])->toBe(422);
            expect($data['detail'])->toBe('Validation failed for user input');
            expect($data['instance'])->toBe('urn:uuid:validation-error-12345');
            expect($data['trace'])->toBe($trace);
        });
    });

    describe('getContentType method', function (): void {
        test('returns correct content type', function (): void {
            // Arrange
            $formatter = new ProblemJsonFormatter();

            // Act
            $contentType = $formatter->getContentType();

            // Assert
            expect($contentType)->toBe('application/problem+json');
        });
    });
});
