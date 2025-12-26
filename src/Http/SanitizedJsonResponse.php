<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cloak\Http;

use Cline\Cloak\Exceptions\SanitizedException;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Creates sanitized JSON error responses from exceptions.
 *
 * Extends Laravel's JsonResponse to provide specialized error formatting
 * with built-in sanitization support. This class offers a convenient way
 * to generate simple error responses without using dedicated formatters,
 * while automatically including error IDs and optional stack traces from
 * SanitizedException instances.
 *
 * Ideal for quick error response generation in middleware, exception handlers,
 * or controller methods where a simple JSON error structure is sufficient.
 * For more complex API standards (JSON:API, RFC 7807, Hydra), use dedicated
 * formatters instead.
 *
 * ```php
 * $response = SanitizedJsonResponse::fromException(
 *     $exception,
 *     404,
 *     includeTrace: app()->environment('local')
 * );
 * // Returns: {"error": "...", "error_id": "...", "trace": [...]}
 * ```
 *
 * @author Brian Faust <brian@cline.sh>
 * @see SanitizedException
 * @see JsonResponse
 */
final class SanitizedJsonResponse extends JsonResponse
{
    /**
     * Creates a sanitized JSON error response from any exception.
     *
     * Static factory method that transforms exceptions into simple JSON
     * error responses. Automatically extracts error IDs and sanitized traces
     * from SanitizedException instances while gracefully handling standard
     * exceptions with minimal error information.
     *
     * ```php
     * // With standard exception
     * $response = SanitizedJsonResponse::fromException(
     *     new \Exception('User not found'),
     *     404
     * );
     * // Returns: {"error": "User not found"}
     *
     * // With SanitizedException (includes error_id)
     * $sanitized = new SanitizedException('Sensitive data removed', errorId: '01JE...');
     * $response = SanitizedJsonResponse::fromException($sanitized, 500, true);
     * // Returns: {"error": "...", "error_id": "01JE...", "trace": [...]}
     * ```
     *
     * @param Throwable            $exception    Exception to transform into error response. SanitizedException instances
     *                                           provide enhanced features including unique error IDs for tracking and
     *                                           pre-sanitized stack traces safe for client consumption.
     * @param int                  $status       HTTP status code for the error response. Defaults to 500 for server
     *                                           errors. Choose appropriate codes (404, 422, etc.) based on error context.
     * @param bool                 $includeTrace Whether to include sanitized stack trace in the response body. Only
     *                                           applies to SanitizedException instances. Recommended for development
     *                                           environments but should be disabled in production. Defaults to false.
     * @param array<string, mixed> $headers      Additional HTTP headers to merge into the response. Standard
     *                                           application/json Content-Type is automatically set.
     *
     * @return self New instance of SanitizedJsonResponse containing formatted error data
     */
    public static function fromException(
        Throwable $exception,
        int $status = 500,
        bool $includeTrace = false,
        array $headers = [],
    ): self {
        $data = [
            'error' => $exception->getMessage(),
        ];

        // Add error ID if available
        if ($exception instanceof SanitizedException && $exception->getErrorId()) {
            $data['error_id'] = $exception->getErrorId();
        }

        // Add sanitized trace if requested
        if ($includeTrace && $exception instanceof SanitizedException) {
            $data['trace'] = $exception->getSanitizedTrace();
        }

        return new self($data, $status, $headers);
    }
}
