<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cloak\Sanitizers;

use Cline\Cloak\Contracts\ExceptionSanitizer;
use Cline\Cloak\Exceptions\SanitizedException;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Throwable;

use function array_any;
use function array_key_exists;
use function config;
use function in_array;
use function is_int;
use function is_string;
use function preg_match;
use function preg_replace;
use function str_replace;

/**
 * Sanitizes exceptions using configurable regex patterns.
 *
 * Applies pattern-based redaction to exception messages and stack traces,
 * removing sensitive information like passwords, API keys, tokens, and
 * personally identifiable information (PII). Provides granular control over
 * sanitization behavior through allowlists, blocklists, generic message
 * replacement, and conditional sanitization based on debug mode.
 *
 * The sanitizer supports generating unique error IDs (ULIDs or UUIDs) for
 * tracking, storing context data in Laravel's Context system for logging
 * and monitoring, and executing custom callbacks to enrich error metadata.
 * Stack traces are sanitized by removing sensitive file paths and function
 * arguments while preserving call structure for debugging.
 *
 * ```php
 * $sanitizer = PatternBasedSanitizer::fromConfig();
 * $sanitized = $sanitizer->sanitize(
 *     new \Exception('Database password: secret123')
 * );
 * // Returns: SanitizedException with message: "Database password: [REDACTED]"
 * ```
 *
 * @author Brian Faust <brian@cline.sh>
 * @see ExceptionSanitizer
 * @see SanitizedException
 * @psalm-immutable
 */
final readonly class PatternBasedSanitizer implements ExceptionSanitizer
{
    /**
     * Create a new pattern-based sanitizer instance.
     *
     * @param array<int, string>           $patterns            Regex patterns to match and redact sensitive data like passwords,
     *                                                          tokens, API keys, and other secrets from exception messages and traces.
     * @param string                       $replacement         Text to replace sensitive matches with (defaults to "[REDACTED]" to
     *                                                          clearly indicate sanitization occurred without revealing data).
     * @param array<string>                $sanitizeTypes       Exception class names that should always be sanitized regardless of
     *                                                          whether patterns match, useful for exceptions known to contain sensitive data.
     * @param array<string>                $allowedTypes        Exception class names that should never be sanitized (bypass all sanitization),
     *                                                          typically used for exceptions that are safe to expose in full detail.
     * @param array<string, string>        $genericMessages     Maps exception class names to generic replacement messages, completely
     *                                                          replacing the original message to prevent any potential data leakage.
     * @param bool                         $sanitizeInDebug     Whether to apply sanitization even when app.debug is enabled, defaults to
     *                                                          false to allow full error details during development.
     * @param null|string                  $errorIdType         Type of unique error identifier to generate (ulid, uuid, or null to disable),
     *                                                          useful for correlating user-facing errors with internal logs.
     * @param null|string                  $errorIdTemplate     Template string for injecting error ID into messages using {message} and {id}
     *                                                          placeholders, enables user-friendly error messages with tracking IDs.
     * @param null|string                  $errorIdContextKey   Laravel Context key for storing the error ID, enables automatic inclusion
     *                                                          in logs and monitoring tools throughout the request lifecycle.
     * @param bool                         $sanitizeStackTraces Whether to sanitize stack trace file paths and arguments to prevent
     *                                                          leaking sensitive information through trace data (defaults to true).
     * @param array<string, callable>      $contextCallbacks    Associative array mapping context keys to callables that return additional
     *                                                          context data, executed during sanitization to enrich error tracking.
     * @param array<string, array<string>> $exceptionTags       Maps exception class names to arrays of tags for categorization and filtering
     *                                                          in error monitoring systems, stored in Laravel Context for logging.
     */
    public function __construct(
        private array $patterns,
        private string $replacement = '[REDACTED]',
        private array $sanitizeTypes = [],
        private array $allowedTypes = [],
        private array $genericMessages = [],
        private bool $sanitizeInDebug = false,
        private ?string $errorIdType = null,
        private ?string $errorIdTemplate = null,
        private ?string $errorIdContextKey = null,
        private bool $sanitizeStackTraces = true,
        private array $contextCallbacks = [],
        private array $exceptionTags = [],
    ) {}

    /**
     * Create a sanitizer from application configuration.
     *
     * Convenience factory method that loads all sanitizer settings from the
     * config/cloak.php configuration file, allowing centralized management of
     * sanitization rules and behavior across the application.
     *
     * @return self New sanitizer instance configured from application settings
     */
    public static function fromConfig(): self
    {
        /** @var array<int, string> $patterns */
        $patterns = config('cloak.patterns', []);

        /** @var string $replacement */
        $replacement = config('cloak.replacement', '[REDACTED]');

        /** @var array<string> $sanitizeTypes */
        $sanitizeTypes = config('cloak.sanitize_exceptions', []);

        /** @var array<string> $allowedTypes */
        $allowedTypes = config('cloak.allowed_exceptions', []);

        /** @var array<string, string> $genericMessages */
        $genericMessages = config('cloak.generic_messages', []);

        /** @var bool $sanitizeInDebug */
        $sanitizeInDebug = config('cloak.sanitize_in_debug', false);

        /** @var null|string $errorIdType */
        $errorIdType = config('cloak.error_id_type');

        /** @var null|string $errorIdTemplate */
        $errorIdTemplate = config('cloak.error_id_template');

        /** @var null|string $errorIdContextKey */
        $errorIdContextKey = config('cloak.error_id_context_key');

        /** @var bool $sanitizeStackTraces */
        $sanitizeStackTraces = config('cloak.sanitize_stack_traces', true);

        /** @var array<string, callable> $contextCallbacks */
        $contextCallbacks = config('cloak.context', []);

        /** @var array<string, array<string>> $exceptionTags */
        $exceptionTags = config('cloak.tags', []);

        return new self(
            patterns: $patterns,
            replacement: $replacement,
            sanitizeTypes: $sanitizeTypes,
            allowedTypes: $allowedTypes,
            genericMessages: $genericMessages,
            sanitizeInDebug: $sanitizeInDebug,
            errorIdType: $errorIdType,
            errorIdTemplate: $errorIdTemplate,
            errorIdContextKey: $errorIdContextKey,
            sanitizeStackTraces: $sanitizeStackTraces,
            contextCallbacks: $contextCallbacks,
            exceptionTags: $exceptionTags,
        );
    }

    /**
     * Sanitizes an exception by removing sensitive information.
     *
     * Evaluates the exception against configured rules to determine if sanitization
     * is necessary, then applies pattern-based redaction to messages and stack traces.
     * Generates unique error IDs, executes context callbacks for enriched logging,
     * applies generic message replacements or pattern sanitization, and creates
     * a SanitizedException wrapper with sanitized data.
     *
     * Returns the original exception unchanged if sanitization is not required based
     * on allowlist rules or debug mode settings.
     *
     * ```php
     * $exception = new \Exception('API key: sk_live_abc123');
     * $result = $sanitizer->sanitize($exception);
     * // Returns: SanitizedException("API key: [REDACTED]", errorId: "01JE...")
     * ```
     *
     * @param Throwable $exception Exception to evaluate and potentially sanitize
     *
     * @return Throwable Original exception if no sanitization needed, or SanitizedException with redacted data
     */
    public function sanitize(Throwable $exception): Throwable
    {
        if (!$this->shouldSanitize($exception)) {
            return $exception;
        }

        // Generate error ID if enabled
        $errorId = $this->generateErrorId();

        // Store error ID in Laravel Context for logging/monitoring
        if ($errorId && $this->errorIdContextKey) {
            try {
                Context::add($this->errorIdContextKey, $errorId);
            } catch (Throwable) {
                // Silently fail - don't let context operations break sanitization
            }
        }

        // Execute custom context callbacks to add additional data
        foreach ($this->contextCallbacks as $key => $callback) {
            try {
                $value = $callback();

                if ($value !== null) {
                    Context::add($key, $value);
                }
            } catch (Throwable) {
                // Silently fail - don't let context extraction break sanitization
            }
        }

        // Add exception tags to Context for categorization
        $exceptionClass = $exception::class;

        if (isset($this->exceptionTags[$exceptionClass])) {
            try {
                Context::add('exception_tags', $this->exceptionTags[$exceptionClass]);
            } catch (Throwable) {
                // Silently fail - don't let context operations break sanitization
            }
        }

        // Use generic message if configured for this exception type
        $message = $this->getGenericMessage($exceptionClass)
            ?? $this->sanitizeMessage($exception->getMessage());

        // Apply error ID template if configured
        if ($errorId && $this->errorIdTemplate) {
            $message = str_replace(
                ['{message}', '{id}'],
                [$message, $errorId],
                $this->errorIdTemplate,
            );
        }

        // Sanitize stack trace if enabled
        $sanitizedTrace = $this->sanitizeStackTraces
            ? $this->sanitizeTrace($exception->getTrace())
            : [];

        // Create a sanitized wrapper exception
        return new SanitizedException(
            message: $message,
            code: $exception->getCode(),
            previous: $exception,
            errorId: $errorId,
            sanitizedTrace: $sanitizedTrace,
        );
    }

    /**
     * Determines whether an exception requires sanitization.
     *
     * Evaluates the exception through a multi-step decision process:
     * 1. Checks allowlist - exceptions in allowedTypes bypass all sanitization
     * 2. Evaluates debug mode - returns false if app.debug is true and sanitizeInDebug is false
     * 3. Checks blocklist - exceptions in sanitizeTypes are always sanitized
     * 4. Pattern matching - sanitizes if message contains sensitive data patterns
     *
     * This layered approach provides fine-grained control over sanitization behavior
     * while maintaining security defaults.
     *
     * @param Throwable $exception Exception to evaluate for sanitization requirements
     *
     * @return bool True if sanitization should be applied, false to return exception unchanged
     */
    public function shouldSanitize(Throwable $exception): bool
    {
        $exceptionClass = $exception::class;

        // Never sanitize allowed exceptions
        if (in_array($exceptionClass, $this->allowedTypes, true)) {
            return false;
        }

        // Check debug mode
        if (config('app.debug') && !$this->sanitizeInDebug) {
            return false;
        }

        // Always sanitize configured exception types
        if (in_array($exceptionClass, $this->sanitizeTypes, true)) {
            return true;
        }

        // Check if message contains sensitive patterns
        return $this->containsSensitiveData($exception->getMessage());
    }

    /**
     * Sanitizes a message by applying all configured regex patterns.
     *
     * Iterates through the pattern array, applying each regex to the message
     * and replacing matches with the configured replacement text (typically
     * "[REDACTED]"). Patterns are applied sequentially, allowing multiple
     * types of sensitive data to be redacted in a single message.
     *
     * @param string $message Message text potentially containing sensitive data
     *
     * @return string Sanitized message with all pattern matches replaced
     */
    public function sanitizeMessage(string $message): string
    {
        foreach ($this->patterns as $pattern) {
            $message = preg_replace($pattern, $this->replacement, $message) ?? $message;
        }

        return $message;
    }

    /**
     * Checks if a message contains sensitive data matching configured patterns.
     *
     * Tests the message against all regex patterns, returning true if any
     * pattern matches. Used as part of the shouldSanitize decision process
     * to determine if automatic sanitization should occur.
     *
     * @param string $message Message text to test for sensitive data
     *
     * @return bool True if any configured pattern matches the message, false otherwise
     */
    private function containsSensitiveData(string $message): bool
    {
        return array_any($this->patterns, fn ($pattern): bool => preg_match($pattern, $message) === 1);
    }

    /**
     * Retrieves a generic replacement message for an exception class.
     *
     * Checks if a generic message is configured for the given exception class
     * name. Generic messages completely replace the original exception message,
     * useful for exceptions that always contain sensitive data or require
     * uniform public-facing messages.
     *
     * @param string $exceptionClass Fully qualified exception class name
     *
     * @return null|string Generic replacement message if configured, null otherwise
     */
    private function getGenericMessage(string $exceptionClass): ?string
    {
        if (array_key_exists($exceptionClass, $this->genericMessages)) {
            return $this->genericMessages[$exceptionClass];
        }

        return null;
    }

    /**
     * Generates a unique error identifier based on configured type.
     *
     * Creates either a ULID (Universally Unique Lexicographically Sortable
     * Identifier) or UUID (Universally Unique Identifier) for tracking errors
     * across logs, monitoring systems, and user-facing error messages. Returns
     * null if error ID generation is disabled in configuration.
     *
     * @return null|string Generated error ID (ULID or UUID) or null if disabled
     */
    private function generateErrorId(): ?string
    {
        return match ($this->errorIdType) {
            'ulid' => (string) Str::ulid(),
            'uuid' => (string) Str::uuid(),
            default => null,
        };
    }

    /**
     * Sanitizes a stack trace by redacting sensitive information.
     *
     * Processes each stack frame to remove sensitive file paths, function
     * arguments, and other potentially sensitive data while preserving the
     * call structure (file, line, class, function). Function arguments are
     * intentionally omitted to prevent leaking sensitive data passed as
     * parameters. File paths are sanitized through pattern matching.
     *
     * @param array<int, array<string, mixed>> $trace Original exception stack trace from Throwable::getTrace()
     *
     * @return array<int, array{file: string, line: int, function?: string, class?: string}> Sanitized stack frames
     */
    private function sanitizeTrace(array $trace): array
    {
        $sanitized = [];

        foreach ($trace as $index => $frame) {
            $file = $frame['file'] ?? 'unknown';
            $line = $frame['line'] ?? 0;

            $sanitizedFrame = [
                'file' => $this->sanitizePath(is_string($file) ? $file : 'unknown'),
                'line' => is_int($line) ? $line : 0,
            ];

            if (isset($frame['class']) && is_string($frame['class'])) {
                $sanitizedFrame['class'] = $frame['class'];
            }

            if (isset($frame['function']) && is_string($frame['function'])) {
                $sanitizedFrame['function'] = $frame['function'];
            }

            // Note: We intentionally omit 'args' to prevent leaking sensitive data
            // passed as function arguments

            $sanitized[$index] = $sanitizedFrame;
        }

        return $sanitized;
    }

    /**
     * Sanitizes a file path by applying configured redaction patterns.
     *
     * Applies all configured regex patterns to the file path to remove
     * sensitive directory names, usernames, or other identifying information
     * while maintaining path structure for debugging purposes.
     *
     * @param string $path Original file path from stack trace
     *
     * @return string Sanitized file path with sensitive portions redacted
     */
    private function sanitizePath(string $path): string
    {
        // Apply all configured patterns to the path
        foreach ($this->patterns as $pattern) {
            $path = preg_replace($pattern, $this->replacement, $path) ?? $path;
        }

        return $path;
    }
}
