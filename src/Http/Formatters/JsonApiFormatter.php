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
 * Formats exceptions as JSON:API-compliant error responses.
 *
 * Transforms PHP exceptions into standardized error responses following
 * the JSON:API specification. JSON:API defines a consistent structure for
 * API responses, including detailed error objects with status, title, detail,
 * id, code, and meta fields. This formatter ensures API consumers receive
 * predictable, well-documented error information.
 *
 * The formatter produces error objects with string status codes, human-readable
 * titles, detailed messages, optional error identifiers, exception codes, and
 * configurable stack trace metadata for debugging.
 *
 * ```php
 * $formatter = new JsonApiFormatter();
 * $response = $formatter->format($exception, 422);
 * // Returns: {"errors": [{"status": "422", "title": "Unprocessable Entity", ...}]}
 * ```
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://jsonapi.org/format/#errors JSON:API Error Objects Specification
 * @see ResponseFormatter
 * @psalm-immutable
 */
final readonly class JsonApiFormatter implements ResponseFormatter
{
    /**
     * Transforms an exception into a JSON:API-compliant error response.
     *
     * ```php
     * $response = $formatter->format(
     *     new \Exception('Invalid email format', 1001),
     *     422,
     *     includeTrace: false
     * );
     * // Produces: {
     * //   "errors": [{
     * //     "status": "422",
     * //     "title": "Unprocessable Entity",
     * //     "detail": "Invalid email format",
     * //     "code": "1001"
     * //   }]
     * // }
     * ```
     *
     * @param Throwable            $exception    Exception to transform into JSON:API error format. SanitizedException
     *                                           instances provide enhanced features like unique error IDs and sanitized traces.
     * @param int                  $status       HTTP status code determining the response status and human-readable title
     *                                           (e.g., 422 generates "Unprocessable Entity"). Defaults to 500 for server errors.
     * @param bool                 $includeTrace Whether to include sanitized stack trace in the meta.trace field. Only applies
     *                                           to SanitizedException instances. Useful for debugging while maintaining security.
     *                                           Defaults to false for production safety.
     * @param array<string, mixed> $headers      Additional HTTP headers to merge into the response. Content-Type will be
     *                                           automatically set to application/vnd.api+json per JSON:API specification.
     *
     * @return JsonResponse JSON:API-formatted error response with application/vnd.api+json Content-Type header
     */
    public function format(
        Throwable $exception,
        int $status = 500,
        bool $includeTrace = false,
        array $headers = [],
    ): JsonResponse {
        $error = [
            'status' => (string) $status,
            'title' => $this->getTitle($status),
            'detail' => $exception->getMessage(),
        ];

        // Add error ID if available
        if ($exception instanceof SanitizedException && $exception->getErrorId()) {
            $error['id'] = $exception->getErrorId();
        }

        // Add code if available
        if ($exception->getCode() !== 0) {
            $error['code'] = (string) $exception->getCode();
        }

        // Add sanitized trace as meta if requested
        if ($includeTrace && $exception instanceof SanitizedException) {
            $error['meta'] = [
                'trace' => $exception->getSanitizedTrace(),
            ];
        }

        $data = [
            'errors' => [$error],
        ];

        $headers['Content-Type'] = $this->getContentType();

        return new JsonResponse($data, $status, $headers);
    }

    /**
     * Returns the JSON:API media type for error responses.
     *
     * The JSON:API specification mandates the application/vnd.api+json
     * media type for all requests and responses, including error responses.
     * This ensures consistent content negotiation and API client compatibility.
     *
     * @return string The JSON:API media type identifier: 'application/vnd.api+json'
     */
    public function getContentType(): string
    {
        return 'application/vnd.api+json';
    }

    /**
     * Converts HTTP status code to human-readable reason phrase.
     *
     * Maps standard HTTP status codes to their official reason phrases
     * for use in the JSON:API error title field. Covers common client and
     * server error codes, returning a generic "Error" fallback for unmapped codes.
     *
     * @param int $status HTTP status code to convert (e.g., 404, 422)
     *
     * @return string Standard HTTP reason phrase (e.g., 'Not Found', 'Unprocessable Entity')
     */
    private function getTitle(int $status): string
    {
        return match ($status) {
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            409 => 'Conflict',
            422 => 'Unprocessable Entity',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            default => 'Error',
        };
    }
}
