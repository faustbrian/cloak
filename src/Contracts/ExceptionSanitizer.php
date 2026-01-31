<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cloak\Contracts;

use Throwable;

/**
 * Contract for exception sanitization implementations.
 *
 * Defines the interface for sanitizing exceptions to prevent sensitive
 * information leakage in error responses and logs. Implementations should
 * remove or mask patterns like database credentials, API keys, file paths,
 * and other security-sensitive data from exception messages and traces.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see SanitizedException
 * @see PatternBasedSanitizer
 *
 * @api
 */
interface ExceptionSanitizer
{
    /**
     * Sanitize an exception to remove sensitive information.
     *
     * Transforms an exception into a safe version by removing or masking
     * sensitive data from the message and stack trace. Returns a new
     * SanitizedException wrapping the sanitized content, or the original
     * exception if sanitization is not needed.
     *
     * @param Throwable $exception Exception instance containing potentially sensitive
     *                             information in its message, stack trace, or context
     *
     * @return Throwable Sanitized exception (typically SanitizedException) with
     *                   sensitive patterns removed or masked
     */
    public function sanitize(Throwable $exception): Throwable;

    /**
     * Determine if an exception should be sanitized.
     *
     * Evaluates whether an exception requires sanitization based on its type,
     * message content, or other criteria. Implementations may skip sanitization
     * for certain exception types or in specific environments.
     *
     * @param Throwable $exception Exception instance to evaluate for sanitization
     *
     * @return bool True if the exception contains or may contain sensitive data
     *              and should be sanitized before rendering or logging
     */
    public function shouldSanitize(Throwable $exception): bool;

    /**
     * Sanitize a message string by removing sensitive patterns.
     *
     * Applies pattern-based sanitization to remove or mask sensitive information
     * such as passwords, API keys, tokens, database credentials, file paths, and
     * other security-sensitive data. This method can be used independently for
     * sanitizing log messages or other text content.
     *
     * @param string $message Message string potentially containing sensitive patterns
     *                        like credentials, tokens, or system paths
     *
     * @return string Sanitized message with sensitive patterns removed or replaced
     *                with placeholders (e.g., '[REDACTED]', '[FILTERED]')
     */
    public function sanitizeMessage(string $message): string;
}
