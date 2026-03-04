<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cloak\Exceptions;

use Exception;
use Throwable;

use function sprintf;

/**
 * Sanitized exception wrapper for safe error responses.
 *
 * Wraps an original exception with a sanitized message while preserving
 * the original exception as the previous exception for logging purposes.
 * This allows safe rendering of exceptions in production while maintaining
 * full error details for debugging through Laravel's logging system.
 *
 * The sanitized exception includes an optional error ID for correlating log
 * entries with user-facing errors, and a sanitized stack trace with sensitive
 * file paths and arguments removed for safe inclusion in API responses.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see ExceptionSanitizer
 * @see CloakManager
 * @see PatternBasedSanitizer
 */
final class SanitizedException extends Exception implements CloakException
{
    /**
     * Create a new sanitized exception instance.
     *
     * @param string                                                                        $message        Sanitized exception message with sensitive patterns
     *                                                                                                      removed or masked. Safe for display in error responses.
     * @param int                                                                           $code           Exception code from the original exception, preserved
     *                                                                                                      for error categorization and handling logic.
     * @param null|Throwable                                                                $previous       Original unsanitized exception preserved for logging
     *                                                                                                      and debugging. Accessible via getPrevious() method.
     * @param null|string                                                                   $errorId        Unique error identifier (UUID) for tracking and correlating
     *                                                                                                      errors across logs, responses, and monitoring systems.
     * @param array<int, array{file: string, line: int, function?: string, class?: string}> $sanitizedTrace Sanitized stack trace with sensitive file paths and
     *                                                                                                      arguments removed. Each frame includes file, line number,
     *                                                                                                      and optionally the function/class information.
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        private readonly ?string $errorId = null,
        private readonly array $sanitizedTrace = [],
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get the original unsanitized exception.
     *
     * Returns the original exception that was sanitized, preserving the full
     * error context for logging. This is accessible via the standard PHP
     * exception chain mechanism.
     *
     * @return null|Throwable Original exception before sanitization, or null if none
     */
    public function getOriginalException(): ?Throwable
    {
        return $this->getPrevious();
    }

    /**
     * Get the unique error identifier for this exception.
     *
     * Returns a UUID that can be used to correlate error responses with log
     * entries, monitoring alerts, and support tickets. Useful for tracking
     * specific error occurrences across distributed systems.
     *
     * @return null|string UUID error identifier, or null if not generated
     */
    public function getErrorId(): ?string
    {
        return $this->errorId;
    }

    /**
     * Get the sanitized stack trace array.
     *
     * Returns the stack trace with sensitive information removed from file
     * paths and function arguments. Each frame contains file, line number,
     * and optionally the function and class names.
     *
     * @return array<int, array{file: string, line: int, function?: string, class?: string}>
     *                                                                                       Array of stack trace frames with sanitized file paths and safe metadata
     */
    public function getSanitizedTrace(): array
    {
        return $this->sanitizedTrace;
    }

    /**
     * Get the sanitized stack trace formatted as a string.
     *
     * Formats the sanitized trace array into a human-readable string similar
     * to PHP's standard exception trace format. Useful for logging or displaying
     * trace information without exposing sensitive file system details.
     *
     * @return string Formatted stack trace string with numbered frames, or empty
     *                string if no trace is available
     */
    public function getSanitizedTraceAsString(): string
    {
        if ($this->sanitizedTrace === []) {
            return '';
        }

        $trace = '';

        foreach ($this->sanitizedTrace as $index => $frame) {
            $trace .= sprintf('#%d %s(%d): ', $index, $frame['file'], $frame['line']);

            if (isset($frame['class'])) {
                $trace .= $frame['class'].'->';
            }

            if (isset($frame['function'])) {
                $trace .= $frame['function'].'()
';
            } else {
                $trace .= "\n";
            }
        }

        return $trace;
    }
}
