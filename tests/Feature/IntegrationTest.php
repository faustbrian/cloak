<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Feature;

use Cline\Cloak\CloakManager;
use Illuminate\Database\QueryException;
use InvalidArgumentException;
use PDOException;
use RuntimeException;

use function config;
use function describe;
use function expect;
use function resolve;
use function test;

describe('Cloak Integration', function (): void {
    test('sanitizes QueryException with database credentials', function (): void {
        config([
            'cloak.enabled' => true,
            'cloak.error_id_type' => null,
            'cloak.sanitize_exceptions' => [QueryException::class],
            'cloak.generic_messages' => [
                QueryException::class => 'A database error occurred.',
            ],
        ]);

        $pdoException = new PDOException('SQLSTATE[HY000]: Connection failed: mysql://root:password@localhost/mydb');
        $exception = new QueryException('default', 'SELECT * FROM users', [], $pdoException);

        $manager = resolve(CloakManager::class);
        $sanitized = $manager->sanitizeForRendering($exception);

        expect($sanitized->getMessage())->toBe('A database error occurred.');
        expect($sanitized->getMessage())->not->toContain('password');
        expect($sanitized->getMessage())->not->toContain('mysql://');
    });

    test('does not sanitize in debug mode when configured', function (): void {
        config([
            'app.debug' => true,
            'cloak.enabled' => true,
            'cloak.error_id_type' => null,
            'cloak.sanitize_in_debug' => false,
        ]);

        $exception = new RuntimeException('This contains mysql://root:password@localhost/db');

        $manager = resolve(CloakManager::class);
        $sanitized = $manager->sanitizeForRendering($exception);

        expect($sanitized)->toBe($exception);
        expect($sanitized->getMessage())->toContain('password');
    });

    test('sanitizes in debug mode when explicitly configured', function (): void {
        config([
            'app.debug' => true,
            'cloak.enabled' => true,
            'cloak.error_id_type' => null,
            'cloak.sanitize_in_debug' => true,
            'cloak.patterns' => ['/mysql:\/\/([^:]+):([^@]+)@([^\/]+)\/(.+)/i'],
        ]);

        $exception = new RuntimeException('Error: mysql://root:password@localhost/db');

        $manager = resolve(CloakManager::class);
        $sanitized = $manager->sanitizeForRendering($exception);

        expect($sanitized->getMessage())->not->toContain('password');
        expect($sanitized->getMessage())->toContain('[REDACTED]');
    });

    test('complex scenario with multiple sensitive patterns', function (): void {
        config([
            'cloak.enabled' => true,
            'cloak.error_id_type' => null,
            'cloak.patterns' => [
                '/mysql:\/\/([^:]+):([^@]+)@([^\/]+)\/(.+)/i',
                '/api[_-]?key["\']?\s*[:=]\s*["\']?([a-zA-Z0-9_\-]+)/i',
                '/bearer\s+([a-zA-Z0-9_\-\.]+)/i',
            ],
        ]);

        $message = 'Failed to connect mysql://admin:secret@db.prod.com/app with api_key=prod_123ABC and Bearer token_xyz789';
        $exception = new RuntimeException($message);

        $manager = resolve(CloakManager::class);
        $sanitized = $manager->sanitizeForRendering($exception);

        expect($sanitized->getMessage())->not->toContain('secret');
        expect($sanitized->getMessage())->not->toContain('prod_123ABC');
        expect($sanitized->getMessage())->not->toContain('token_xyz789');
        expect($sanitized->getMessage())->toContain('[REDACTED]');
    });

    test('preserves exception code and previous exception', function (): void {
        config([
            'cloak.enabled' => true,
            'cloak.error_id_type' => null,
            'cloak.patterns' => ['/secret/i'],
        ]);

        $previous = new InvalidArgumentException('Previous error');
        $exception = new RuntimeException('This is secret', 500, $previous);

        $manager = resolve(CloakManager::class);
        $sanitized = $manager->sanitizeForRendering($exception);

        expect($sanitized->getCode())->toBe(500);
        expect($sanitized->getPrevious())->toBe($exception);
    });
});
