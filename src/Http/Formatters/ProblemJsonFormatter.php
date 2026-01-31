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
 * Formats exceptions as RFC 7807 Problem Details responses.
 *
 * Transforms PHP exceptions into standardized error responses following
 * RFC 7807 (Problem Details for HTTP APIs). This IETF standard provides
 * a machine-readable format for HTTP API errors with type URIs, human-readable
 * titles, HTTP status codes, detailed descriptions, and problem instance URIs.
 *
 * Problem Details is widely adopted for REST APIs requiring standardized
 * error responses with extensible metadata. The formatter generates responses
 * with type (default: about:blank), title, status, detail, optional instance
 * URIs for error tracking, and configurable stack traces for debugging.
 *
 * ```php
 * $formatter = new ProblemJsonFormatter();
 * $response = $formatter->format($exception, 400);
 * // Returns: {"type": "about:blank", "title": "Bad Request", "status": 400, ...}
 * ```
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://tools.ietf.org/html/rfc7807 RFC 7807 Problem Details Specification
 * @see ResponseFormatter
 * @psalm-immutable
 */
final readonly class ProblemJsonFormatter implements ResponseFormatter
{
    /**
     * Transforms an exception into an RFC 7807 Problem Details response.
     *
     * ```php
     * $response = $formatter->format(
     *     new \Exception('Database connection failed'),
     *     503,
     *     includeTrace: false
     * );
     * // Produces: {
     * //   "type": "about:blank",
     * //   "title": "Service Unavailable",
     * //   "status": 503,
     * //   "detail": "Database connection failed"
     * // }
     * ```
     *
     * @param Throwable            $exception    Exception to transform into Problem Details format. SanitizedException
     *                                           instances provide additional features like error IDs (mapped to instance
     *                                           field) and sanitized traces.
     * @param int                  $status       HTTP status code determining the response status and human-readable title
     *                                           (e.g., 503 generates "Service Unavailable"). Defaults to 500 for server errors.
     * @param bool                 $includeTrace Whether to include sanitized stack trace as a top-level field. Only applies
     *                                           to SanitizedException instances. Useful for debugging while preventing
     *                                           sensitive data exposure. Defaults to false for production safety.
     * @param array<string, mixed> $headers      Additional HTTP headers to merge into the response. Content-Type will be
     *                                           automatically set to application/problem+json per RFC 7807 specification.
     *
     * @return JsonResponse RFC 7807-formatted error response with application/problem+json Content-Type header
     */
    public function format(
        Throwable $exception,
        int $status = 500,
        bool $includeTrace = false,
        array $headers = [],
    ): JsonResponse {
        $data = [
            'type' => 'about:blank', // Can be overridden to point to error documentation
            'title' => $this->getTitle($status),
            'status' => $status,
            'detail' => $exception->getMessage(),
        ];

        // Add error ID as instance if available
        if ($exception instanceof SanitizedException && $exception->getErrorId()) {
            $data['instance'] = 'urn:uuid:'.$exception->getErrorId();
        }

        // Add sanitized trace if requested
        if ($includeTrace && $exception instanceof SanitizedException) {
            $data['trace'] = $exception->getSanitizedTrace();
        }

        $headers['Content-Type'] = $this->getContentType();

        return new JsonResponse($data, $status, $headers);
    }

    /**
     * Returns the Problem Details media type for error responses.
     *
     * RFC 7807 defines application/problem+json as the standard media type
     * for Problem Details responses. This media type signals to clients that
     * the response follows the Problem Details structure and conventions.
     *
     * @return string The Problem Details media type identifier: 'application/problem+json'
     */
    public function getContentType(): string
    {
        return 'application/problem+json';
    }

    /**
     * Converts HTTP status code to human-readable reason phrase.
     *
     * Maps standard HTTP status codes to their official reason phrases
     * for use in the Problem Details title field. Covers common client and
     * server error codes, returning a generic "Error" fallback for unmapped codes.
     *
     * @param int $status HTTP status code to convert (e.g., 404, 503)
     *
     * @return string Standard HTTP reason phrase (e.g., 'Not Found', 'Service Unavailable')
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
