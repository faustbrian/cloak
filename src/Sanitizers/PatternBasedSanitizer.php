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
 * Pattern-based exception sanitizer.
 *
 * Sanitizes exceptions by applying regex patterns to remove sensitive
 * information from exception messages and optionally from stack traces.
 *
 * @author Brian Faust <brian@cline.sh>
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
     * Sanitize an exception to remove sensitive information.
     *
     * Processes the exception through pattern matching and sanitization rules to
     * create a safe version for public consumption. Generates error IDs, executes
     * context callbacks, applies generic messages or pattern-based sanitization,
     * and optionally sanitizes stack traces. Returns the original exception if
     * sanitization is not needed.
     *
     * @param Throwable $exception The exception to sanitize
     *
     * @return Throwable Either the original exception (if no sanitization needed) or a SanitizedException wrapper
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
            Context::add($this->errorIdContextKey, $errorId);
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
            Context::add('exception_tags', $this->exceptionTags[$exceptionClass]);
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
     * Determine if an exception should be sanitized.
     *
     * Evaluates exception against allowlist (never sanitize), debug mode settings,
     * explicit sanitize list, and pattern matching to decide if sanitization should
     * occur. This provides flexible control over which exceptions require sanitization.
     *
     * @param Throwable $exception The exception to evaluate
     *
     * @return bool True if sanitization should be applied, false to leave unchanged
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
     * Sanitize a message by applying regex patterns.
     *
     * Iterates through all configured patterns and applies them to the message,
     * replacing matched sensitive data with the configured replacement text.
     * This is the core sanitization logic for message content.
     *
     * @param string $message The message to sanitize
     *
     * @return string The sanitized message with sensitive data replaced
     */
    public function sanitizeMessage(string $message): string
    {
        foreach ($this->patterns as $pattern) {
            $message = preg_replace($pattern, $this->replacement, $message) ?? $message;
        }

        return $message;
    }

    /**
     * Check if a message contains sensitive data based on patterns.
     *
     * @param string $message The message to check
     *
     * @return bool True if sensitive data detected
     */
    private function containsSensitiveData(string $message): bool
    {
        return array_any($this->patterns, fn ($pattern): bool => preg_match($pattern, $message) === 1);
    }

    /**
     * Get generic message for an exception type if configured.
     *
     * @param string $exceptionClass The exception class name
     *
     * @return null|string The generic message or null
     */
    private function getGenericMessage(string $exceptionClass): ?string
    {
        if (array_key_exists($exceptionClass, $this->genericMessages)) {
            return $this->genericMessages[$exceptionClass];
        }

        return null;
    }

    /**
     * Generate a unique error ID based on configuration.
     *
     * @return null|string The generated error ID or null if disabled
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
     * Sanitize a stack trace by redacting sensitive file paths and arguments.
     *
     * @param array<int, array<string, mixed>> $trace The original stack trace
     *
     * @return array<int, array{file: string, line: int, function?: string, class?: string}>
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
     * Sanitize a file path by redacting sensitive portions.
     *
     * @param string $path The original file path
     *
     * @return string The sanitized path
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
