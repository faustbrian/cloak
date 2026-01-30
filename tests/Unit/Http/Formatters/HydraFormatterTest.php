<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\Http\Formatters;

use Cline\Cloak\Exceptions\SanitizedException;
use Cline\Cloak\Http\Formatters\HydraFormatter;
use RuntimeException;

use function describe;
use function expect;
use function json_decode;
use function test;

describe('HydraFormatter', function (): void {
    describe('format()', function (): void {
        test('formats basic exception with JSON-LD and Hydra vocabulary', function (): void {
            // Arrange
            $formatter = new HydraFormatter();
            $exception = new RuntimeException('Bad request');

            // Act
            $response = $formatter->format($exception, status: 400);

            // Assert
            expect($response->getStatusCode())->toBe(400);
            expect($response->headers->get('Content-Type'))->toContain('application/ld+json');

            $data = json_decode($response->getContent(), true);
            expect($data)->toHaveKeys(['@context', '@type', 'hydra:title', 'hydra:description']);
            expect($data['@context'])->toBe('/contexts/Error');
            expect($data['@type'])->toBe('hydra:Error');
            expect($data['hydra:title'])->toBe('Bad Request');
            expect($data['hydra:description'])->toBe('Bad request');
        });

        test('includes error ID as @id URN when SanitizedException has errorId', function (): void {
            // Arrange
            $formatter = new HydraFormatter();
            $exception = new SanitizedException(
                message: 'Error occurred',
                errorId: 'f47ac10b-58cc-4372-a567-0e02b2c3d479',
            );

            // Act
            $response = $formatter->format($exception);

            // Assert
            $data = json_decode($response->getContent(), true);
            expect($data)->toHaveKey('@id');
            expect($data['@id'])->toBe('urn:uuid:f47ac10b-58cc-4372-a567-0e02b2c3d479');
        });

        test('does not include @id when SanitizedException has no errorId', function (): void {
            // Arrange
            $formatter = new HydraFormatter();
            $exception = new SanitizedException(
                message: 'Error occurred',
                errorId: null,
            );

            // Act
            $response = $formatter->format($exception);

            // Assert
            $data = json_decode($response->getContent(), true);
            expect($data)->not->toHaveKey('@id');
        });

        test('does not include @id when exception is not SanitizedException', function (): void {
            // Arrange
            $formatter = new HydraFormatter();
            $exception = new RuntimeException('Regular error');

            // Act
            $response = $formatter->format($exception);

            // Assert
            $data = json_decode($response->getContent(), true);
            expect($data)->not->toHaveKey('@id');
        });

        test('includes sanitized trace when includeTrace is true and exception is SanitizedException', function (): void {
            // Arrange
            $formatter = new HydraFormatter();
            $trace = [
                ['file' => '/app/Service.php', 'line' => 42, 'function' => 'process'],
                ['file' => '/app/Controller.php', 'line' => 15, 'class' => 'ApiController', 'function' => 'handle'],
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
            $formatter = new HydraFormatter();
            $trace = [['file' => '/app/Service.php', 'line' => 42]];
            $exception = new SanitizedException(
                message: 'Error occurred',
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
            $formatter = new HydraFormatter();
            $exception = new RuntimeException('Regular error');

            // Act
            $response = $formatter->format($exception, includeTrace: true);

            // Assert
            $data = json_decode($response->getContent(), true);
            expect($data)->not->toHaveKey('trace');
        });

        test('respects custom headers', function (): void {
            // Arrange
            $formatter = new HydraFormatter();
            $exception = new RuntimeException('Error');
            $customHeaders = ['X-Custom-Header' => 'test-value'];

            // Act
            $response = $formatter->format($exception, headers: $customHeaders);

            // Assert
            expect($response->headers->get('X-Custom-Header'))->toBe('test-value');
            expect($response->headers->get('Content-Type'))->toContain('application/ld+json');
        });
    });

    describe('getTitle()', function (): void {
        test('returns "Bad Request" for 400 status', function (): void {
            // Arrange
            $formatter = new HydraFormatter();
            $exception = new RuntimeException('Error');

            // Act
            $response = $formatter->format($exception, status: 400);

            // Assert
            $data = json_decode($response->getContent(), true);
            expect($data['hydra:title'])->toBe('Bad Request');
        });

        test('returns "Unauthorized" for 401 status', function (): void {
            // Arrange
            $formatter = new HydraFormatter();
            $exception = new RuntimeException('Error');

            // Act
            $response = $formatter->format($exception, status: 401);

            // Assert
            $data = json_decode($response->getContent(), true);
            expect($data['hydra:title'])->toBe('Unauthorized');
        });

        test('returns "Forbidden" for 403 status', function (): void {
            // Arrange
            $formatter = new HydraFormatter();
            $exception = new RuntimeException('Error');

            // Act
            $response = $formatter->format($exception, status: 403);

            // Assert
            $data = json_decode($response->getContent(), true);
            expect($data['hydra:title'])->toBe('Forbidden');
        });

        test('returns "Not Found" for 404 status', function (): void {
            // Arrange
            $formatter = new HydraFormatter();
            $exception = new RuntimeException('Error');

            // Act
            $response = $formatter->format($exception, status: 404);

            // Assert
            $data = json_decode($response->getContent(), true);
            expect($data['hydra:title'])->toBe('Not Found');
        });

        test('returns "Method Not Allowed" for 405 status', function (): void {
            // Arrange
            $formatter = new HydraFormatter();
            $exception = new RuntimeException('Error');

            // Act
            $response = $formatter->format($exception, status: 405);

            // Assert
            $data = json_decode($response->getContent(), true);
            expect($data['hydra:title'])->toBe('Method Not Allowed');
        });

        test('returns "Conflict" for 409 status', function (): void {
            // Arrange
            $formatter = new HydraFormatter();
            $exception = new RuntimeException('Error');

            // Act
            $response = $formatter->format($exception, status: 409);

            // Assert
            $data = json_decode($response->getContent(), true);
            expect($data['hydra:title'])->toBe('Conflict');
        });

        test('returns "Unprocessable Entity" for 422 status', function (): void {
            // Arrange
            $formatter = new HydraFormatter();
            $exception = new RuntimeException('Error');

            // Act
            $response = $formatter->format($exception, status: 422);

            // Assert
            $data = json_decode($response->getContent(), true);
            expect($data['hydra:title'])->toBe('Unprocessable Entity');
        });

        test('returns "Too Many Requests" for 429 status', function (): void {
            // Arrange
            $formatter = new HydraFormatter();
            $exception = new RuntimeException('Error');

            // Act
            $response = $formatter->format($exception, status: 429);

            // Assert
            $data = json_decode($response->getContent(), true);
            expect($data['hydra:title'])->toBe('Too Many Requests');
        });

        test('returns "Internal Server Error" for 500 status', function (): void {
            // Arrange
            $formatter = new HydraFormatter();
            $exception = new RuntimeException('Error');

            // Act
            $response = $formatter->format($exception, status: 500);

            // Assert
            $data = json_decode($response->getContent(), true);
            expect($data['hydra:title'])->toBe('Internal Server Error');
        });

        test('returns "Bad Gateway" for 502 status', function (): void {
            // Arrange
            $formatter = new HydraFormatter();
            $exception = new RuntimeException('Error');

            // Act
            $response = $formatter->format($exception, status: 502);

            // Assert
            $data = json_decode($response->getContent(), true);
            expect($data['hydra:title'])->toBe('Bad Gateway');
        });

        test('returns "Service Unavailable" for 503 status', function (): void {
            // Arrange
            $formatter = new HydraFormatter();
            $exception = new RuntimeException('Error');

            // Act
            $response = $formatter->format($exception, status: 503);

            // Assert
            $data = json_decode($response->getContent(), true);
            expect($data['hydra:title'])->toBe('Service Unavailable');
        });

        test('returns "Error" for unknown status code', function (): void {
            // Arrange
            $formatter = new HydraFormatter();
            $exception = new RuntimeException('Error');

            // Act
            $response = $formatter->format($exception, status: 418);

            // Assert
            $data = json_decode($response->getContent(), true);
            expect($data['hydra:title'])->toBe('Error');
        });
    });

    describe('getContentType()', function (): void {
        test('returns application/ld+json content type', function (): void {
            // Arrange
            $formatter = new HydraFormatter();

            // Act
            $contentType = $formatter->getContentType();

            // Assert
            expect($contentType)->toBe('application/ld+json');
        });
    });
});
