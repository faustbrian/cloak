<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit;

use Cline\Cloak\Exceptions\SanitizedException;
use Cline\Cloak\Http\Formatters\HalFormatter;
use Cline\Cloak\Http\Formatters\HydraFormatter;
use Cline\Cloak\Http\Formatters\JsonApiFormatter;
use Cline\Cloak\Http\Formatters\ProblemJsonFormatter;
use Cline\Cloak\Http\Formatters\SimpleFormatter;
use RuntimeException;

use function describe;
use function expect;
use function json_decode;
use function test;

describe('Response Formatters', function (): void {
    describe('SimpleFormatter', function (): void {
        test('formats exception as simple JSON', function (): void {
            $formatter = new SimpleFormatter();
            $exception = new RuntimeException('Something went wrong');

            $response = $formatter->format($exception);

            expect($response->getStatusCode())->toBe(500);
            expect($formatter->getContentType())->toBe('application/json');

            $data = json_decode($response->getContent(), true);
            expect($data)->toHaveKey('error');
            expect($data['error'])->toBe('Something went wrong');
        });

        test('includes error ID when available', function (): void {
            $formatter = new SimpleFormatter();
            $exception = new SanitizedException(
                message: 'Error occurred',
                errorId: 'test-id-123',
            );

            $response = $formatter->format($exception);

            $data = json_decode($response->getContent(), true);
            expect($data)->toHaveKey('error_id');
            expect($data['error_id'])->toBe('test-id-123');
        });

        test('includes trace when requested', function (): void {
            $formatter = new SimpleFormatter();
            $trace = [['file' => '/app/Service.php', 'line' => 42]];
            $exception = new SanitizedException(
                message: 'Error',
                sanitizedTrace: $trace,
            );

            $response = $formatter->format($exception, includeTrace: true);

            $data = json_decode($response->getContent(), true);
            expect($data)->toHaveKey('trace');
            expect($data['trace'])->toBe($trace);
        });
    });

    describe('JsonApiFormatter', function (): void {
        test('formats exception according to JSON:API spec', function (): void {
            $formatter = new JsonApiFormatter();
            $exception = new RuntimeException('Database error');

            $response = $formatter->format($exception, status: 422);

            expect($formatter->getContentType())->toBe('application/vnd.api+json');
            expect($response->headers->get('Content-Type'))->toContain('application/vnd.api+json');

            $data = json_decode($response->getContent(), true);
            expect($data)->toHaveKey('errors');
            expect($data['errors'])->toBeArray();
            expect($data['errors'][0])->toHaveKeys(['status', 'title', 'detail']);
            expect($data['errors'][0]['status'])->toBe('422');
            expect($data['errors'][0]['title'])->toBe('Unprocessable Entity');
            expect($data['errors'][0]['detail'])->toBe('Database error');
        });

        test('includes error ID', function (): void {
            $formatter = new JsonApiFormatter();
            $exception = new SanitizedException(
                message: 'Error',
                errorId: 'abc-123',
            );

            $response = $formatter->format($exception);

            $data = json_decode($response->getContent(), true);
            expect($data['errors'][0])->toHaveKey('id');
            expect($data['errors'][0]['id'])->toBe('abc-123');
        });

        test('includes trace in meta when requested', function (): void {
            $formatter = new JsonApiFormatter();
            $trace = [['file' => '/app/Service.php', 'line' => 42]];
            $exception = new SanitizedException(
                message: 'Error',
                sanitizedTrace: $trace,
            );

            $response = $formatter->format($exception, includeTrace: true);

            $data = json_decode($response->getContent(), true);
            expect($data['errors'][0])->toHaveKey('meta');
            expect($data['errors'][0]['meta']['trace'])->toBe($trace);
        });
    });

    describe('ProblemJsonFormatter', function (): void {
        test('formats exception according to RFC 7807', function (): void {
            $formatter = new ProblemJsonFormatter();
            $exception = new RuntimeException('Not found');

            $response = $formatter->format($exception, status: 404);

            expect($formatter->getContentType())->toBe('application/problem+json');
            expect($response->headers->get('Content-Type'))->toContain('application/problem+json');

            $data = json_decode($response->getContent(), true);
            expect($data)->toHaveKeys(['type', 'title', 'status', 'detail']);
            expect($data['type'])->toBe('about:blank');
            expect($data['title'])->toBe('Not Found');
            expect($data['status'])->toBe(404);
            expect($data['detail'])->toBe('Not found');
        });

        test('includes error ID as instance URN', function (): void {
            $formatter = new ProblemJsonFormatter();
            $exception = new SanitizedException(
                message: 'Error',
                errorId: 'f47ac10b-58cc-4372-a567-0e02b2c3d479',
            );

            $response = $formatter->format($exception);

            $data = json_decode($response->getContent(), true);
            expect($data)->toHaveKey('instance');
            expect($data['instance'])->toBe('urn:uuid:f47ac10b-58cc-4372-a567-0e02b2c3d479');
        });
    });

    describe('HalFormatter', function (): void {
        test('formats exception with HAL structure', function (): void {
            $formatter = new HalFormatter();
            $exception = new RuntimeException('Service unavailable');

            $response = $formatter->format($exception, status: 503);

            expect($formatter->getContentType())->toBe('application/hal+json');
            expect($response->headers->get('Content-Type'))->toContain('application/hal+json');

            $data = json_decode($response->getContent(), true);
            expect($data)->toHaveKeys(['message', 'status', '_links']);
            expect($data['message'])->toBe('Service unavailable');
            expect($data['status'])->toBe(503);
            expect($data['_links'])->toHaveKey('self');
        });

        test('includes error ID when available', function (): void {
            $formatter = new HalFormatter();
            $exception = new SanitizedException(
                message: 'Error occurred',
                errorId: 'hal-error-123',
            );

            $response = $formatter->format($exception);

            $data = json_decode($response->getContent(), true);
            expect($data)->toHaveKey('error_id');
            expect($data['error_id'])->toBe('hal-error-123');
        });

        test('includes trace in _embedded when requested', function (): void {
            $formatter = new HalFormatter();
            $trace = [['file' => '/app/Service.php', 'line' => 42]];
            $exception = new SanitizedException(
                message: 'Error',
                sanitizedTrace: $trace,
            );

            $response = $formatter->format($exception, includeTrace: true);

            $data = json_decode($response->getContent(), true);
            expect($data)->toHaveKey('_embedded');
            expect($data['_embedded']['trace'])->toBe($trace);
        });
    });

    describe('HydraFormatter', function (): void {
        test('formats exception with JSON-LD and Hydra vocabulary', function (): void {
            $formatter = new HydraFormatter();
            $exception = new RuntimeException('Bad request');

            $response = $formatter->format($exception, status: 400);

            expect($formatter->getContentType())->toBe('application/ld+json');
            expect($response->headers->get('Content-Type'))->toContain('application/ld+json');

            $data = json_decode($response->getContent(), true);
            expect($data)->toHaveKeys(['@context', '@type', 'hydra:title', 'hydra:description']);
            expect($data['@context'])->toBe('/contexts/Error');
            expect($data['@type'])->toBe('hydra:Error');
            expect($data['hydra:title'])->toBe('Bad Request');
            expect($data['hydra:description'])->toBe('Bad request');
        });

        test('includes error ID as @id URN', function (): void {
            $formatter = new HydraFormatter();
            $exception = new SanitizedException(
                message: 'Error',
                errorId: 'abc-123',
            );

            $response = $formatter->format($exception);

            $data = json_decode($response->getContent(), true);
            expect($data)->toHaveKey('@id');
            expect($data['@id'])->toBe('urn:uuid:abc-123');
        });
    });
});
