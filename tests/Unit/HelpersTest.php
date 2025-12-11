<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit;

use Cline\Cloak\Sanitizers\PatternBasedSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Mockery;
use RuntimeException;

use function Cline\Cloak\rethrow;
use function config;
use function describe;
use function expect;
use function test;

describe('rethrow helper', function (): void {
    test('recreates original exception class with sanitized message', function (): void {
        config([
            'cloak.enabled' => true,
            'cloak.patterns' => ['/secret/i'],
            'cloak.replacement' => '[REDACTED]',
            'cloak.error_id_type' => null, // Disable error ID for predictable messages
        ]);

        $exception = new RuntimeException('This is a secret message', 123);

        $rethrown = rethrow($exception);

        expect($rethrown)->toBeInstanceOf(RuntimeException::class);
        expect($rethrown->getMessage())->toBe('This is a [REDACTED] message');
        expect($rethrown->getCode())->toBe(123);
    });

    test('preserves previous exception chain', function (): void {
        config([
            'cloak.enabled' => true,
            'cloak.patterns' => ['/secret/i'],
            'cloak.replacement' => '[REDACTED]',
        ]);

        $previous = new RuntimeException('Previous error');
        $exception = new RuntimeException('This is a secret message', 456, $previous);

        $rethrown = rethrow($exception);

        expect($rethrown->getPrevious())->toBe($previous);
        expect($rethrown->getPrevious()->getMessage())->toBe('Previous error');
    });

    test('returns original when disabled', function (): void {
        config(['cloak.enabled' => false]);

        $exception = new RuntimeException('This is a secret message');

        $rethrown = rethrow($exception);

        expect($rethrown)->toBe($exception);
        expect($rethrown->getMessage())->toBe('This is a secret message');
    });

    test('accepts request parameter', function (): void {
        Log::spy();

        config([
            'cloak.enabled' => true,
            'cloak.log_original' => true,
            'cloak.patterns' => ['/secret/i'],
            'cloak.replacement' => '[REDACTED]',
        ]);

        $exception = new RuntimeException('This is a secret message');
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('fullUrl')->andReturn('https://example.com/test');
        $request->shouldReceive('method')->andReturn('GET');

        rethrow($exception, $request);

        Log::shouldHaveReceived('error')
            ->once()
            ->with(
                'Original exception before sanitization',
                Mockery::on(fn ($context): bool => isset($context['url'], $context['method'])
                    && $context['url'] === 'https://example.com/test'
                    && $context['method'] === 'GET'),
            );
    });
});
