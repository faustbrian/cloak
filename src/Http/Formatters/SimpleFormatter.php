<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cloak\Http\Formatters;

use Cline\Cloak\Contracts\ResponseFormatter;
use Cline\Cloak\Exceptions\SanitizedException;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Formats exceptions as simple, lightweight JSON error responses.
 *
 * Provides a minimal error response structure without adhering to specific
 * API standards like JSON:API or RFC 7807. This formatter is ideal for
 * applications prioritizing simplicity over standardization, producing
 * concise error responses with just the essential information.
 *
 * The formatter generates responses with an error message, optional error
 * identifier for tracking, and optional sanitized stack trace for debugging.
 * This is the default formatter when no specific API standard is required.
 *
 * ```php
 * $formatter = new SimpleFormatter();
 * $response = $formatter->format($exception, 404);
 * // Returns: {"error": "Resource not found", "error_id": "01JEXAMPLE"}
 * ```
 *
 * @author Brian Faust <brian@cline.sh>
 * @see ResponseFormatter
 * @psalm-immutable
 */
final readonly class SimpleFormatter implements ResponseFormatter
{
    /**
     * Transforms an exception into a simple JSON error response.
     *
     * ```php
     * $response = $formatter->format(
     *     new \Exception('Invalid credentials'),
     *     401,
     *     includeTrace: false
     * );
     * // Produces: {
     * //   "error": "Invalid credentials"
     * // }
     * ```
     *
     * @param Throwable            $exception    Exception to transform into simple error format. SanitizedException
     *                                           instances provide enhanced features like unique error IDs and sanitized
     *                                           stack traces for improved error tracking and debugging.
     * @param int                  $status       HTTP status code for the response. Defaults to 500 for server errors.
     *                                           The status code is returned but not included in the response body.
     * @param bool                 $includeTrace Whether to include sanitized stack trace in the response body. Only applies
     *                                           to SanitizedException instances. Useful for development and debugging while
     *                                           maintaining security. Defaults to false for production safety.
     * @param array<string, mixed> $headers      Additional HTTP headers to merge into the response. Standard application/json
     *                                           Content-Type is implied and does not need to be specified.
     *
     * @return JsonResponse Simple JSON error response with standard application/json Content-Type
     */
    public function format(
        Throwable $exception,
        int $status = 500,
        bool $includeTrace = false,
        array $headers = [],
    ): JsonResponse {
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

        return new JsonResponse($data, $status, $headers);
    }

    /**
     * Returns the standard JSON media type for simple error responses.
     *
     * Uses the standard application/json media type for maximum compatibility
     * with all HTTP clients and API consumers. Unlike specialized formatters,
     * this does not use a vendor-specific or standard-specific media type.
     *
     * @return string The standard JSON media type identifier: 'application/json'
     */
    public function getContentType(): string
    {
        return 'application/json';
    }
}
