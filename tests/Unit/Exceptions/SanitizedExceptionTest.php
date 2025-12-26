<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\Exceptions;

use Cline\Cloak\Exceptions\SanitizedException;
use RuntimeException;

use function describe;
use function expect;
use function test;

describe('SanitizedException', function (): void {
    test('creates exception with all parameters', function (): void {
        $originalException = new RuntimeException('Original error');
        $sanitizedTrace = [
            ['file' => '/app/file.php', 'line' => 10, 'class' => 'App\\Service', 'function' => 'method'],
        ];

        $exception = new SanitizedException(
            message: 'Sanitized error',
            code: 500,
            previous: $originalException,
            errorId: 'error-123',
            sanitizedTrace: $sanitizedTrace,
        );

        expect($exception->getMessage())->toBe('Sanitized error');
        expect($exception->getCode())->toBe(500);
        expect($exception->getOriginalException())->toBe($originalException);
        expect($exception->getErrorId())->toBe('error-123');
        expect($exception->getSanitizedTrace())->toBe($sanitizedTrace);
    });

    test('creates exception with default parameters', function (): void {
        $exception = new SanitizedException();

        expect($exception->getMessage())->toBe('');
        expect($exception->getCode())->toBe(0);
        expect($exception->getOriginalException())->toBeNull();
        expect($exception->getErrorId())->toBeNull();
        expect($exception->getSanitizedTrace())->toBe([]);
    });

    test('gets original exception', function (): void {
        $originalException = new RuntimeException('Original error');
        $exception = new SanitizedException(previous: $originalException);

        expect($exception->getOriginalException())->toBe($originalException);
    });

    test('gets original exception returns null when not set', function (): void {
        $exception = new SanitizedException();

        expect($exception->getOriginalException())->toBeNull();
    });

    test('gets error id', function (): void {
        $exception = new SanitizedException(errorId: 'error-456');

        expect($exception->getErrorId())->toBe('error-456');
    });

    test('gets error id returns null when not set', function (): void {
        $exception = new SanitizedException();

        expect($exception->getErrorId())->toBeNull();
    });

    test('gets sanitized trace', function (): void {
        $trace = [
            ['file' => '/app/file1.php', 'line' => 10],
            ['file' => '/app/file2.php', 'line' => 20],
        ];

        $exception = new SanitizedException(sanitizedTrace: $trace);

        expect($exception->getSanitizedTrace())->toBe($trace);
    });

    test('gets sanitized trace returns empty array when not set', function (): void {
        $exception = new SanitizedException();

        expect($exception->getSanitizedTrace())->toBe([]);
    });

    test('gets sanitized trace as string with empty trace', function (): void {
        $exception = new SanitizedException(sanitizedTrace: []);

        expect($exception->getSanitizedTraceAsString())->toBe('');
    });

    test('gets sanitized trace as string with trace containing class and function', function (): void {
        $exception = new SanitizedException(sanitizedTrace: [
            ['file' => '/app/Service.php', 'line' => 42, 'class' => 'App\\Service', 'function' => 'process'],
        ]);

        $expected = "#0 /app/Service.php(42): App\\Service->process()\n";

        expect($exception->getSanitizedTraceAsString())->toBe($expected);
    });

    test('gets sanitized trace as string with trace containing class only', function (): void {
        $exception = new SanitizedException(sanitizedTrace: [
            ['file' => '/app/Service.php', 'line' => 42, 'class' => 'App\\Service'],
        ]);

        $expected = "#0 /app/Service.php(42): App\\Service->\n";

        expect($exception->getSanitizedTraceAsString())->toBe($expected);
    });

    test('gets sanitized trace as string with trace containing function only', function (): void {
        $exception = new SanitizedException(sanitizedTrace: [
            ['file' => '/app/helpers.php', 'line' => 15, 'function' => 'sanitize'],
        ]);

        $expected = "#0 /app/helpers.php(15): sanitize()\n";

        expect($exception->getSanitizedTraceAsString())->toBe($expected);
    });

    test('gets sanitized trace as string with trace containing neither class nor function', function (): void {
        $exception = new SanitizedException(sanitizedTrace: [
            ['file' => '/app/script.php', 'line' => 5],
        ]);

        $expected = "#0 /app/script.php(5): \n";

        expect($exception->getSanitizedTraceAsString())->toBe($expected);
    });

    test('gets sanitized trace as string with multiple frames', function (): void {
        $exception = new SanitizedException(sanitizedTrace: [
            ['file' => '/app/Service.php', 'line' => 42, 'class' => 'App\\Service', 'function' => 'process'],
            ['file' => '/app/Controller.php', 'line' => 18, 'class' => 'App\\Controller', 'function' => 'handle'],
            ['file' => '/app/helpers.php', 'line' => 7, 'function' => 'bootstrap'],
            ['file' => '/app/index.php', 'line' => 3],
        ]);

        $expected = "#0 /app/Service.php(42): App\\Service->process()\n"
            ."#1 /app/Controller.php(18): App\\Controller->handle()\n"
            ."#2 /app/helpers.php(7): bootstrap()\n"
            ."#3 /app/index.php(3): \n";

        expect($exception->getSanitizedTraceAsString())->toBe($expected);
    });

    test('gets sanitized trace as string with mixed frames', function (): void {
        $exception = new SanitizedException(sanitizedTrace: [
            ['file' => '/app/Service.php', 'line' => 42, 'class' => 'App\\Service', 'function' => 'process'],
            ['file' => '/app/helpers.php', 'line' => 15, 'function' => 'sanitize'],
            ['file' => '/app/Model.php', 'line' => 99, 'class' => 'App\\Model'],
            ['file' => '/app/bootstrap.php', 'line' => 1],
        ]);

        $expected = "#0 /app/Service.php(42): App\\Service->process()\n"
            ."#1 /app/helpers.php(15): sanitize()\n"
            ."#2 /app/Model.php(99): App\\Model->\n"
            ."#3 /app/bootstrap.php(1): \n";

        expect($exception->getSanitizedTraceAsString())->toBe($expected);
    });

    test('preserves exception inheritance chain', function (): void {
        $root = new RuntimeException('Root error');
        $middle = new RuntimeException('Middle error', 0, $root);
        $exception = new SanitizedException(
            message: 'Sanitized error',
            previous: $middle,
        );

        expect($exception->getOriginalException())->toBe($middle);
        expect($exception->getOriginalException()->getPrevious())->toBe($root);
    });

    test('formats trace with special characters in file paths', function (): void {
        $exception = new SanitizedException(sanitizedTrace: [
            ['file' => '/app/path with spaces/Service.php', 'line' => 42, 'class' => 'App\\Service', 'function' => 'process'],
        ]);

        $expected = "#0 /app/path with spaces/Service.php(42): App\\Service->process()\n";

        expect($exception->getSanitizedTraceAsString())->toBe($expected);
    });

    test('formats trace with long class names', function (): void {
        $exception = new SanitizedException(sanitizedTrace: [
            [
                'file' => '/app/Service.php',
                'line' => 42,
                'class' => 'App\\Very\\Long\\Nested\\Namespace\\Service',
                'function' => 'processWithVeryLongMethodName',
            ],
        ]);

        $expected = "#0 /app/Service.php(42): App\\Very\\Long\\Nested\\Namespace\\Service->processWithVeryLongMethodName()\n";

        expect($exception->getSanitizedTraceAsString())->toBe($expected);
    });
});
