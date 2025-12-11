<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Global helper functions for exception sanitization.
 *
 * Provides convenient global functions for working with the Cloak package,
 * enabling sanitization operations without requiring facade imports or
 * dependency injection. Functions are namespaced under Cline\Cloak to
 * avoid conflicts while remaining accessible throughout the application.
 */

namespace Cline\Cloak;

use Cline\Cloak\Facades\Cloak;
use Illuminate\Http\Request;
use Throwable;

use function function_exists;

if (!function_exists('Cline\Cloak\rethrow')) {
    /**
     * Rethrows an exception with a sanitized message while preserving type.
     *
     * Creates a new instance of the original exception class using reflection,
     * applying sanitization to the message while preserving the exception code
     * and previous exception chain. This provides an alternative to wrapping
     * exceptions in SanitizedException when the original exception type must
     * be maintained (e.g., for type-specific exception handlers).
     *
     * Useful when exception type matters for catching or handling logic but
     * the message contains sensitive data that must be redacted. The request
     * parameter enables context-aware logging during the rethrow process.
     *
     * ```php
     * try {
     *     // Code that throws exception with sensitive data
     *     throw new \PDOException('Connection failed for user: admin@secret.com');
     * } catch (\Throwable $e) {
     *     throw rethrow($e); // Returns: PDOException with sanitized message
     * }
     * ```
     *
     * @param Throwable    $exception Exception to sanitize and rethrow with same type
     * @param null|Request $request   Optional HTTP request providing context for logging and monitoring
     *
     * @return Throwable New instance of original exception class with sanitized message and preserved metadata
     */
    function rethrow(Throwable $exception, ?Request $request = null): Throwable
    {
        return Cloak::rethrow($exception, $request);
    }
}
