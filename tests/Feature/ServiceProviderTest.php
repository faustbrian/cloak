<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Feature;

use Cline\Cloak\CloakManager;
use Cline\Cloak\Contracts\ExceptionSanitizer;
use Cline\Cloak\Facades\Cloak;
use Cline\Cloak\Sanitizers\PatternBasedSanitizer;
use RuntimeException;

use function config;
use function describe;
use function expect;
use function test;

describe('CloakServiceProvider', function (): void {
    test('registers exception sanitizer in container', function (): void {
        $sanitizer = $this->app->make(ExceptionSanitizer::class);

        expect($sanitizer)->toBeInstanceOf(PatternBasedSanitizer::class);
    });

    test('registers cloak manager in container', function (): void {
        $manager = $this->app->make(CloakManager::class);

        expect($manager)->toBeInstanceOf(CloakManager::class);
    });

    test('facade resolves cloak manager', function (): void {
        expect(Cloak::getFacadeRoot())->toBeInstanceOf(CloakManager::class);
    });

    test('facade can sanitize exceptions', function (): void {
        config(['cloak.patterns' => ['/secret/i']]);
        config(['cloak.enabled' => true]);

        $exception = new RuntimeException('This is a secret message');
        $sanitized = Cloak::sanitizeForRendering($exception);

        expect($sanitized->getMessage())->toContain('[REDACTED]');
    });

    test('config is published', function (): void {
        expect(config('cloak.enabled'))->toBeTrue();
        expect(config('cloak.patterns'))->toBeArray();
        expect(config('cloak.replacement'))->toBe('[REDACTED]');
    });
});
