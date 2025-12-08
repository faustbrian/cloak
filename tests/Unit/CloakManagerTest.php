<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit;

use Cline\Cloak\CloakManager;
use Cline\Cloak\Exceptions\SanitizedException;
use Cline\Cloak\Sanitizers\PatternBasedSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Mockery;
use RuntimeException;

use function config;
use function describe;
use function expect;
use function json_decode;
use function test;

describe('CloakManager', function (): void {
    test('sanitizes exceptions for rendering', function (): void {
        $sanitizer = new PatternBasedSanitizer(
            patterns: ['/secret/i'],
            replacement: '[REDACTED]',
        );

        $manager = new CloakManager($sanitizer, logOriginal: false);
        $exception = new RuntimeException('This is a secret message');

        $sanitized = $manager->sanitizeForRendering($exception);

        expect($sanitized)->toBeInstanceOf(SanitizedException::class);
        expect($sanitized->getMessage())->toBe('This is a [REDACTED] message');
    });

    test('returns original exception when disabled', function (): void {
        config(['cloak.enabled' => false]);

        $sanitizer = new PatternBasedSanitizer(
            patterns: ['/secret/i'],
            replacement: '[REDACTED]',
        );

        $manager = new CloakManager($sanitizer);
        $exception = new RuntimeException('This is a secret message');

        $sanitized = $manager->sanitizeForRendering($exception);

        expect($sanitized)->toBe($exception);
        expect($sanitized->getMessage())->toBe('This is a secret message');
    });

    test('logs original exception when configured', function (): void {
        Log::spy();

        $sanitizer = new PatternBasedSanitizer(
            patterns: ['/secret/i'],
            replacement: '[REDACTED]',
        );

        $manager = new CloakManager($sanitizer, logOriginal: true);
        $exception = new RuntimeException('This is a secret message');

        $manager->sanitizeForRendering($exception);

        Log::shouldHaveReceived('error')
            ->once()
            ->with('Original exception before sanitization', Mockery::type('array'));
    });

    test('does not log when log original is disabled', function (): void {
        Log::spy();

        $sanitizer = new PatternBasedSanitizer(
            patterns: ['/secret/i'],
            replacement: '[REDACTED]',
        );

        $manager = new CloakManager($sanitizer, logOriginal: false);
        $exception = new RuntimeException('This is a secret message');

        $manager->sanitizeForRendering($exception);

        Log::shouldNotHaveReceived('error');
    });

    test('is enabled returns correct value from config', function (): void {
        config(['cloak.enabled' => true]);
        $manager = CloakManager::fromConfig();
        expect($manager->isEnabled())->toBeTrue();

        config(['cloak.enabled' => false]);
        $manager = CloakManager::fromConfig();
        expect($manager->isEnabled())->toBeFalse();
    });

    test('gets sanitizer instance', function (): void {
        $sanitizer = new PatternBasedSanitizer([]);
        $manager = new CloakManager($sanitizer);

        expect($manager->getSanitizer())->toBe($sanitizer);
    });

    test('creates JSON response from exception', function (): void {
        $sanitizer = new PatternBasedSanitizer(
            patterns: ['/secret/i'],
            replacement: '[REDACTED]',
        );

        $manager = new CloakManager($sanitizer, logOriginal: false);
        $exception = new RuntimeException('This is a secret message');

        $response = $manager->toJsonResponse($exception);

        expect($response)->toBeInstanceOf(JsonResponse::class);
        expect($response->getStatusCode())->toBe(500);

        $data = json_decode($response->getContent(), true);
        expect($data['error'])->toBe('This is a [REDACTED] message');
    });

    test('creates JSON response with custom status code', function (): void {
        $sanitizer = new PatternBasedSanitizer([]);
        $manager = new CloakManager($sanitizer, logOriginal: false);
        $exception = new RuntimeException('Bad request');

        $response = $manager->toJsonResponse($exception, status: 400);

        expect($response->getStatusCode())->toBe(400);
    });

    test('includes trace in JSON response when requested', function (): void {
        $sanitizer = new PatternBasedSanitizer(
            patterns: [],
            sanitizeTypes: [RuntimeException::class],
            sanitizeStackTraces: true, // Force sanitization
        );

        $manager = new CloakManager($sanitizer, logOriginal: false);
        $exception = new RuntimeException('Error occurred');

        $response = $manager->toJsonResponse($exception, includeTrace: true);

        $data = json_decode($response->getContent(), true);
        expect($data)->toHaveKey('trace');
    });

    test('uses specified format for JSON response', function (): void {
        $sanitizer = new PatternBasedSanitizer([]);
        $manager = new CloakManager($sanitizer, logOriginal: false);
        $exception = new RuntimeException('Error occurred');

        $response = $manager->toJsonResponse($exception, format: 'json-api');

        expect($response->headers->get('Content-Type'))->toContain('application/vnd.api+json');

        $data = json_decode($response->getContent(), true);
        expect($data)->toHaveKey('errors');
    });

    test('uses default format from config when not specified', function (): void {
        config(['cloak.error_response_format' => 'problem-json']);

        $sanitizer = new PatternBasedSanitizer([]);
        $manager = new CloakManager($sanitizer, logOriginal: false);
        $exception = new RuntimeException('Error occurred');

        $response = $manager->toJsonResponse($exception);

        expect($response->headers->get('Content-Type'))->toContain('application/problem+json');

        $data = json_decode($response->getContent(), true);
        expect($data)->toHaveKeys(['type', 'title', 'status', 'detail']);
    });

    test('gets formatter registry', function (): void {
        $sanitizer = new PatternBasedSanitizer([]);
        $manager = new CloakManager($sanitizer);

        $registry = $manager->getFormatterRegistry();

        expect($registry->has('simple'))->toBeTrue();
        expect($registry->has('json-api'))->toBeTrue();
    });

    test('logs original exception with request context', function (): void {
        Log::spy();

        $sanitizer = new PatternBasedSanitizer(
            patterns: ['/secret/i'],
            replacement: '[REDACTED]',
        );

        $manager = new CloakManager($sanitizer, logOriginal: true);
        $exception = new RuntimeException('This is a secret message');

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('fullUrl')->andReturn('https://example.com/api/test');
        $request->shouldReceive('method')->andReturn('POST');

        $manager->sanitizeForRendering($exception, $request);

        Log::shouldHaveReceived('error')
            ->once()
            ->with(
                'Original exception before sanitization',
                Mockery::on(fn ($context): bool => isset($context['exception'])
                    && isset($context['message'], $context['file'], $context['line'], $context['url'], $context['method'])

                    && $context['url'] === 'https://example.com/api/test'
                    && $context['method'] === 'POST'),
            );
    });

    test('logs original exception without request context', function (): void {
        Log::spy();

        $sanitizer = new PatternBasedSanitizer(
            patterns: ['/secret/i'],
            replacement: '[REDACTED]',
        );

        $manager = new CloakManager($sanitizer, logOriginal: true);
        $exception = new RuntimeException('This is a secret message');

        $manager->sanitizeForRendering($exception);

        Log::shouldHaveReceived('error')
            ->once()
            ->with(
                'Original exception before sanitization',
                Mockery::on(fn ($context): bool => isset($context['exception'])
                    && isset($context['message'], $context['file'], $context['line'])

                    && !isset($context['url'])
                    && !isset($context['method'])),
            );
    });
});
