<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit;

use Cline\Cloak\Exceptions\SanitizedException;
use Cline\Cloak\Sanitizers\PatternBasedSanitizer;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Context;
use PDOException;
use ReflectionClass;
use RuntimeException;
use Tests\Exceptions\ContextCallbackFailedException;

use function describe;
use function expect;
use function test;

describe('PatternBasedSanitizer', function (): void {
    test('sanitizes database connection strings', function (): void {
        $sanitizer = new PatternBasedSanitizer(
            patterns: ['/mysql:\/\/([^:]+):([^@]+)@([^\/]+)\/(.+)/i'],
            replacement: '[REDACTED]',
        );

        $message = 'Connection failed: mysql://root:password123@localhost/mydb';
        $sanitized = $sanitizer->sanitizeMessage($message);

        expect($sanitized)->toBe('Connection failed: [REDACTED]');
    });

    test('sanitizes postgres connection strings', function (): void {
        $sanitizer = new PatternBasedSanitizer(
            patterns: ['/postgres:\/\/([^:]+):([^@]+)@([^\/]+)\/(.+)/i'],
            replacement: '[REDACTED]',
        );

        $message = 'Error connecting to postgres://user:secret@db.example.com/production';
        $sanitized = $sanitizer->sanitizeMessage($message);

        expect($sanitized)->toBe('Error connecting to [REDACTED]');
    });

    test('sanitizes API keys', function (): void {
        $sanitizer = new PatternBasedSanitizer(
            patterns: ['/api[_-]?key["\']?\s*[:=]\s*["\']?([a-zA-Z0-9_\-]+)/i'],
            replacement: '[REDACTED]',
        );

        $message = 'Invalid api_key=test_abc123def456ghi789';
        $sanitized = $sanitizer->sanitizeMessage($message);

        expect($sanitized)->toContain('[REDACTED]');
    });

    test('sanitizes bearer tokens', function (): void {
        $sanitizer = new PatternBasedSanitizer(
            patterns: ['/bearer\s+([a-zA-Z0-9_\-\.]+)/i'],
            replacement: '[REDACTED]',
        );

        $message = 'Unauthorized: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9';
        $sanitized = $sanitizer->sanitizeMessage($message);

        expect($sanitized)->toBe('Unauthorized: [REDACTED]');
    });

    test('sanitizes AWS credentials', function (): void {
        $sanitizer = new PatternBasedSanitizer(
            patterns: [
                '/aws[_-]?access[_-]?key[_-]?id["\']?\s*[:=]\s*["\']?([A-Z0-9]+)/i',
                '/aws[_-]?secret[_-]?access[_-]?key["\']?\s*[:=]\s*["\']?([A-Za-z0-9\/\+]+)/i',
            ],
            replacement: '[REDACTED]',
        );

        $message = 'AWS Error: aws_access_key_id=AKIAIOSFODNN7EXAMPLE aws_secret_access_key=wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY';
        $sanitized = $sanitizer->sanitizeMessage($message);

        expect($sanitized)->toContain('[REDACTED]');
        expect($sanitized)->not->toContain('AKIAIOSFODNN7EXAMPLE');
        expect($sanitized)->not->toContain('wJalrXUtnFEMI');
    });

    test('sanitizes multiple patterns in one message', function (): void {
        $sanitizer = new PatternBasedSanitizer(
            patterns: [
                '/mysql:\/\/([^:]+):([^@]+)@([^\/]+)\/(.+)/i',
                '/api[_-]?key["\']?\s*[:=]\s*["\']?([a-zA-Z0-9_\-]+)/i',
            ],
            replacement: '[REDACTED]',
        );

        $message = 'Failed with mysql://root:pass@localhost/db and api_key=secret123';
        $sanitized = $sanitizer->sanitizeMessage($message);

        expect($sanitized)->not->toContain('pass');
        expect($sanitized)->not->toContain('secret123');
        expect($sanitized)->toContain('[REDACTED]');
    });

    test('returns generic message for configured exception types', function (): void {
        $sanitizer = new PatternBasedSanitizer(
            patterns: [],
            replacement: '[REDACTED]',
            sanitizeTypes: [QueryException::class],
            allowedTypes: [],
            genericMessages: [
                QueryException::class => 'A database error occurred.',
            ],
        );

        $pdoException = new PDOException('SQLSTATE[HY000]: General error: mysql://root:pass@localhost/db');
        $exception = new QueryException('default', 'SELECT * FROM users', [], $pdoException);

        $sanitized = $sanitizer->sanitize($exception);

        expect($sanitized)->toBeInstanceOf(SanitizedException::class);
        expect($sanitized->getMessage())->toBe('A database error occurred.');
    });

    test('does not sanitize allowed exception types', function (): void {
        $sanitizer = new PatternBasedSanitizer(
            patterns: ['/secret/i'],
            replacement: '[REDACTED]',
            sanitizeTypes: [],
            allowedTypes: [RuntimeException::class],
        );

        $exception = new RuntimeException('This contains secret information');

        $sanitized = $sanitizer->sanitize($exception);

        expect($sanitized)->toBe($exception);
        expect($sanitized->getMessage())->toBe('This contains secret information');
    });

    test('should sanitize returns true for configured exception types', function (): void {
        $sanitizer = new PatternBasedSanitizer(
            patterns: [],
            replacement: '[REDACTED]',
            sanitizeTypes: [PDOException::class],
        );

        $exception = new PDOException('Connection failed');

        expect($sanitizer->shouldSanitize($exception))->toBeTrue();
    });

    test('should sanitize returns false for allowed exception types', function (): void {
        $sanitizer = new PatternBasedSanitizer(
            patterns: [],
            replacement: '[REDACTED]',
            sanitizeTypes: [],
            allowedTypes: [RuntimeException::class],
        );

        $exception = new RuntimeException('Safe message');

        expect($sanitizer->shouldSanitize($exception))->toBeFalse();
    });

    test('should sanitize returns true when message contains sensitive patterns', function (): void {
        $sanitizer = new PatternBasedSanitizer(
            patterns: ['/mysql:\/\//i'],
            replacement: '[REDACTED]',
        );

        $exception = new RuntimeException('Error: mysql://user:pass@host/db');

        expect($sanitizer->shouldSanitize($exception))->toBeTrue();
    });

    test('preserves original exception as previous exception', function (): void {
        $sanitizer = new PatternBasedSanitizer(
            patterns: ['/password/i'],
            replacement: '[REDACTED]',
        );

        $original = new RuntimeException('Connection with password failed');
        $sanitized = $sanitizer->sanitize($original);

        expect($sanitized)->toBeInstanceOf(SanitizedException::class);

        /** @var SanitizedException $sanitized */
        expect($sanitized->getOriginalException())->toBe($original);
    });

    test('generates error ID when enabled', function (): void {
        $sanitizer = new PatternBasedSanitizer(
            patterns: ['/secret/i'],
            replacement: '[REDACTED]',
            errorIdType: 'uuid',
            errorIdContextKey: 'exception_id',
        );

        $exception = new RuntimeException('This contains secret data');
        $sanitized = $sanitizer->sanitize($exception);

        expect($sanitized)->toBeInstanceOf(SanitizedException::class);

        /** @var SanitizedException $sanitized */
        expect($sanitized->getErrorId())->not->toBeNull();
        expect($sanitized->getErrorId())->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/');
    });

    test('includes error ID in message when template is configured', function (): void {
        $sanitizer = new PatternBasedSanitizer(
            patterns: ['/secret/i'],
            replacement: '[REDACTED]',
            errorIdType: 'uuid',
            errorIdTemplate: '{message} [Error ID: {id}]',
        );

        $exception = new RuntimeException('This contains secret data');
        $sanitized = $sanitizer->sanitize($exception);

        expect($sanitized->getMessage())->toContain('[Error ID:');
        expect($sanitized->getMessage())->toContain('This contains [REDACTED] data');
    });

    test('sanitizes stack traces when enabled', function (): void {
        $sanitizer = new PatternBasedSanitizer(
            patterns: [
                '/\/Users\/([^\/]+)/i',      // macOS
                '/\/home\/([^\/]+)/i',       // Linux
                '/\/usr\/([^\/]+)/i',        // Linux system paths
                '/\/workspace\/([^\/]+)/i',  // CI environments
                '/C:\\\\Users\\\\([^\\\\]+)/i', // Windows
            ],
            replacement: '[REDACTED]',
            sanitizeTypes: [RuntimeException::class],
            sanitizeStackTraces: true, // Force sanitization
        );

        $exception = new RuntimeException('Test exception');
        $sanitized = $sanitizer->sanitize($exception);

        expect($sanitized)->toBeInstanceOf(SanitizedException::class);

        /** @var SanitizedException $sanitized */
        $trace = $sanitized->getSanitizedTrace();

        expect($trace)->toBeArray();

        if (empty($trace)) {
            return;
        }

        expect($trace[0])->toHaveKeys(['file', 'line']);
        expect($trace[0]['file'])->toContain('[REDACTED]');
    });

    test('omits sensitive arguments from sanitized stack trace', function (): void {
        $sanitizer = new PatternBasedSanitizer(
            patterns: [],
            sanitizeTypes: [RuntimeException::class],
            sanitizeStackTraces: true, // Force sanitization
        );

        $exception = new RuntimeException('Test exception');
        $sanitized = $sanitizer->sanitize($exception);

        expect($sanitized)->toBeInstanceOf(SanitizedException::class);

        /** @var SanitizedException $sanitized */
        $trace = $sanitized->getSanitizedTrace();

        if (empty($trace)) {
            return;
        }

        expect($trace[0])->not->toHaveKey('args');
    });

    test('adds custom context data via callbacks', function (): void {
        $contextCallbacks = [
            'user_id' => fn (): int => 123,
            'tenant_id' => fn (): string => 'tenant-abc',
        ];

        $sanitizer = new PatternBasedSanitizer(
            patterns: ['/secret/i'],
            replacement: '[REDACTED]',
            contextCallbacks: $contextCallbacks,
        );

        $exception = new RuntimeException('This contains secret data');
        $sanitizer->sanitize($exception);

        // Context should be added to Laravel Context
        expect(Context::get('user_id'))->toBe(123);
        expect(Context::get('tenant_id'))->toBe('tenant-abc');
    });

    test('handles failing context callbacks gracefully', function (): void {
        $contextCallbacks = [
            'user_id' => fn (): int => throw ContextCallbackFailedException::simulated(),
            'tenant_id' => fn (): string => 'tenant-abc',
        ];

        $sanitizer = new PatternBasedSanitizer(
            patterns: ['/secret/i'],
            replacement: '[REDACTED]',
            contextCallbacks: $contextCallbacks,
        );

        $exception = new RuntimeException('This contains secret data');
        $sanitized = $sanitizer->sanitize($exception);

        // Should not throw, and should still process other callbacks
        expect($sanitized)->toBeInstanceOf(SanitizedException::class);
        expect(Context::get('tenant_id'))->toBe('tenant-abc');
    });

    test('adds exception tags to context', function (): void {
        $exceptionTags = [
            RuntimeException::class => ['runtime', 'critical'],
            PDOException::class => ['database', 'critical'],
        ];

        $sanitizer = new PatternBasedSanitizer(
            patterns: [],
            sanitizeTypes: [RuntimeException::class],
            exceptionTags: $exceptionTags, // Force sanitization
        );

        $exception = new RuntimeException('Test exception');
        $sanitizer->sanitize($exception);

        expect(Context::get('exception_tags'))->toBe(['runtime', 'critical']);
    });

    test('sanitizes trace with non-string file value', function (): void {
        // We need to test the private sanitizeTrace method which handles non-string file values
        // Create a mock exception and use reflection to test the sanitizeTrace method
        $sanitizer = new PatternBasedSanitizer(
            patterns: [],
            sanitizeTypes: [RuntimeException::class],
            sanitizeStackTraces: true,
        );

        // Use reflection to access the private sanitizeTrace method
        $reflection = new ReflectionClass($sanitizer);
        $method = $reflection->getMethod('sanitizeTrace');

        // Create a trace array with non-string file values
        $trace = [
            [
                'file' => 123, // Non-string file value (integer)
                'line' => 42,
                'function' => 'testFunction',
                'class' => 'TestClass',
            ],
            [
                'file' => null, // Non-string file value (null)
                'line' => 'invalid', // Also test non-int line
                'function' => 'anotherFunction',
            ],
            [
                'file' => ['array', 'value'], // Non-string file value (array)
                'line' => 100,
            ],
        ];

        $sanitizedTrace = $method->invoke($sanitizer, $trace);

        // First frame should have 'unknown' for non-string file (integer)
        expect($sanitizedTrace[0]['file'])->toBe('unknown');
        expect($sanitizedTrace[0]['line'])->toBe(42);
        expect($sanitizedTrace[0]['class'])->toBe('TestClass');
        expect($sanitizedTrace[0]['function'])->toBe('testFunction');

        // Second frame should have 'unknown' for null file and 0 for non-int line
        expect($sanitizedTrace[1]['file'])->toBe('unknown');
        expect($sanitizedTrace[1]['line'])->toBe(0);
        expect($sanitizedTrace[1]['function'])->toBe('anotherFunction');
        expect($sanitizedTrace[1])->not->toHaveKey('class');

        // Third frame should have 'unknown' for array file value
        expect($sanitizedTrace[2]['file'])->toBe('unknown');
        expect($sanitizedTrace[2]['line'])->toBe(100);
        expect($sanitizedTrace[2])->not->toHaveKey('function');
        expect($sanitizedTrace[2])->not->toHaveKey('class');
    });

    test('generates ulid error ID when configured', function (): void {
        $sanitizer = new PatternBasedSanitizer(
            patterns: ['/secret/i'],
            replacement: '[REDACTED]',
            errorIdType: 'ulid',
            errorIdContextKey: 'exception_id',
        );

        $exception = new RuntimeException('This contains secret data');
        $sanitized = $sanitizer->sanitize($exception);

        expect($sanitized)->toBeInstanceOf(SanitizedException::class);

        /** @var SanitizedException $sanitized */
        expect($sanitized->getErrorId())->not->toBeNull();
        // ULID format: 26 characters, alphanumeric (excluding I, L, O, U to avoid confusion)
        expect($sanitized->getErrorId())->toMatch('/^[0-9A-Z]{26}$/');
        expect(Context::get('exception_id'))->toBe($sanitized->getErrorId());
    });

    test('does not sanitize stack traces when disabled', function (): void {
        $sanitizer = new PatternBasedSanitizer(
            patterns: ['/secret/i'],
            replacement: '[REDACTED]',
            sanitizeTypes: [RuntimeException::class],
            sanitizeStackTraces: false, // Disable stack trace sanitization
        );

        $exception = new RuntimeException('This contains secret data');
        $sanitized = $sanitizer->sanitize($exception);

        expect($sanitized)->toBeInstanceOf(SanitizedException::class);

        /** @var SanitizedException $sanitized */
        $trace = $sanitized->getSanitizedTrace();

        // When sanitizeStackTraces is false, should return empty array
        expect($trace)->toBeArray();
        expect($trace)->toBeEmpty();
    });
});
