<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cloak;

use Cline\Cloak\Contracts\ExceptionSanitizer;
use Cline\Cloak\Exceptions\FormatterNotFoundException;
use Cline\Cloak\Http\FormatterRegistry;
use Cline\Cloak\Sanitizers\PatternBasedSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use ReflectionClass;
use ReflectionException;
use Throwable;

use function config;

/**
 * Central manager for exception sanitization and secure error responses.
 *
 * Orchestrates the sanitization process, formatter selection, and logging behavior
 * for the entire package. Provides a unified interface for converting exceptions
 * into safe, standardized API responses while preserving full error context in logs.
 * The manager supports multiple response formats (JSON:API, HAL, RFC 7807, etc.) and
 * integrates seamlessly with Laravel's exception handling pipeline.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see ExceptionSanitizer
 * @see FormatterRegistry
 * @see SanitizedException
 *
 * @api
 * @psalm-immutable
 */
final readonly class CloakManager
{
    /**
     * Create a new Cloak manager instance.
     *
     * @param ExceptionSanitizer $sanitizer         Exception sanitizer implementation used to remove
     *                                              sensitive information from exceptions before they
     *                                              are rendered or logged in production environments.
     * @param bool               $logOriginal       Whether to log original unsanitized exceptions to
     *                                              Laravel's log channels before sanitization occurs.
     *                                              Enables debugging while preventing sensitive data
     *                                              exposure in error responses.
     * @param FormatterRegistry  $formatterRegistry Response formatter registry managing built-in and
     *                                              custom formatters for rendering exceptions in various
     *                                              API standards (JSON:API, HAL, Hydra, Problem JSON).
     */
    public function __construct(
        private ExceptionSanitizer $sanitizer,
        private bool $logOriginal = true,
        private FormatterRegistry $formatterRegistry = new FormatterRegistry(
        ),
    ) {}

    /**
     * Create a manager instance from Laravel configuration.
     *
     * Factory method that constructs a CloakManager using settings from the
     * config/cloak.php file. This is the recommended way to instantiate the
     * manager in Laravel applications.
     *
     * @return self Configured CloakManager instance
     */
    public static function fromConfig(): self
    {
        $sanitizer = PatternBasedSanitizer::fromConfig();

        /** @var bool $logOriginal */
        $logOriginal = config('cloak.log_original', true);

        return new self(
            sanitizer: $sanitizer,
            logOriginal: $logOriginal,
        );
    }

    /**
     * Sanitize an exception for rendering.
     *
     * @param Throwable    $exception The exception to sanitize
     * @param null|Request $request   The current request
     *
     * @return Throwable The sanitized exception
     */
    public function sanitizeForRendering(Throwable $exception, ?Request $request = null): Throwable
    {
        if (!$this->isEnabled()) {
            return $exception;
        }

        // Log the original exception if configured
        if ($this->logOriginal) {
            $this->logOriginalException($exception, $request);
        }

        return $this->sanitizer->sanitize($exception);
    }

    /**
     * Check if Cloak exception sanitization is enabled.
     *
     * Reads the 'cloak.enabled' configuration value to determine whether
     * exception sanitization should occur. When disabled, exceptions are
     * returned unchanged.
     *
     * @return bool True if sanitization is enabled, false otherwise
     */
    public function isEnabled(): bool
    {
        /** @var bool */
        return config('cloak.enabled', true);
    }

    /**
     * Create a JSON response for a sanitized exception.
     *
     * Sanitizes the exception and formats it according to the specified or
     * default formatter. This is the primary method for generating API error
     * responses with consistent structure across different API standards.
     *
     * @param Throwable            $exception    Exception to sanitize and format into JSON response
     * @param null|Request         $request      Current HTTP request context for logging and formatting
     * @param int                  $status       HTTP status code for the response (default: 500)
     * @param bool                 $includeTrace Whether to include sanitized stack trace in response
     *                                           for debugging purposes (not recommended in production)
     * @param array<string, mixed> $headers      Additional HTTP headers to include in the response
     * @param null|string          $format       Response format name (e.g., 'json-api', 'hal', 'hydra').
     *                                           When null, uses default format from configuration.
     *
     * @throws FormatterNotFoundException If the specified format name is not registered
     *
     * @return JsonResponse Formatted JSON response with sanitized exception data
     */
    public function toJsonResponse(
        Throwable $exception,
        ?Request $request = null,
        int $status = 500,
        bool $includeTrace = false,
        array $headers = [],
        ?string $format = null,
    ): JsonResponse {
        $sanitized = $this->sanitizeForRendering($exception, $request);

        $formatter = $format !== null
            ? $this->formatterRegistry->get($format)
            : $this->formatterRegistry->getDefault();

        return $formatter->format(
            exception: $sanitized,
            status: $status,
            includeTrace: $includeTrace,
            headers: $headers,
        );
    }

    /**
     * Get the formatter registry.
     *
     * Provides access to the FormatterRegistry for registering custom formatters
     * or retrieving specific formatter instances.
     *
     * @return FormatterRegistry The formatter registry instance
     */
    public function getFormatterRegistry(): FormatterRegistry
    {
        return $this->formatterRegistry;
    }

    /**
     * Get the exception sanitizer.
     *
     * Returns the configured exception sanitizer implementation, allowing
     * direct access for custom sanitization workflows.
     *
     * @return ExceptionSanitizer The exception sanitizer instance
     */
    public function getSanitizer(): ExceptionSanitizer
    {
        return $this->sanitizer;
    }

    /**
     * Rethrow an exception with sanitized message while preserving class and metadata.
     *
     * Creates a new instance of the original exception class with sanitized message,
     * preserving the original code and previous exception chain. This allows throwing
     * the same exception type with safe messaging instead of wrapping in SanitizedException.
     *
     * @param Throwable    $exception Original exception to rethrow with sanitized message
     * @param null|Request $request   Current HTTP request context for logging
     *
     * @throws ReflectionException If exception class cannot be instantiated
     * @return Throwable           New exception instance of same class with sanitized message
     */
    public function rethrow(Throwable $exception, ?Request $request = null): Throwable
    {
        if (!$this->isEnabled()) {
            return $exception;
        }

        // Log original if configured
        if ($this->logOriginal) {
            $this->logOriginalException($exception, $request);
        }

        $sanitized = $this->sanitizer->sanitize($exception);

        // If sanitizer returned the original unchanged, return it
        if ($sanitized === $exception) {
            return $exception;
        }

        // Extract sanitized message from SanitizedException
        $sanitizedMessage = $sanitized->getMessage();

        // Recreate the original exception class with sanitized message
        $reflection = new ReflectionClass($exception);

        return $reflection->newInstance(
            $sanitizedMessage,
            $exception->getCode(),
            $exception->getPrevious(),
        );
    }

    /**
     * Log the original unsanitized exception to Laravel's log channels.
     *
     * Records complete exception details including class, message, file, line,
     * and request context before sanitization occurs. This enables debugging
     * while keeping sensitive data out of error responses.
     *
     * @param Throwable    $exception Exception instance to log with full details
     * @param null|Request $request   Current HTTP request providing URL and method context
     */
    private function logOriginalException(Throwable $exception, ?Request $request): void
    {
        $context = [
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ];

        if ($request instanceof Request) {
            $context['url'] = $request->fullUrl();
            $context['method'] = $request->method();
        }

        Log::error('Original exception before sanitization', $context);
    }
}
