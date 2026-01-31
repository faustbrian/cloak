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
 * Formats exceptions as Hydra-compliant JSON-LD error responses.
 *
 * Transforms PHP exceptions into machine-readable error responses following
 * the Hydra Core Vocabulary specification. Hydra extends JSON-LD to provide
 * semantic, hypermedia-driven API documentation with rich type information
 * and linked data capabilities. This formatter is ideal for APIs implementing
 * semantic web standards or requiring RDF-compatible error structures.
 *
 * The formatter produces responses with semantic types (hydra:Error),
 * human-readable titles derived from HTTP status codes, detailed error
 * descriptions, optional error identifiers as URNs, and configurable
 * stack trace inclusion for debugging.
 *
 * ```php
 * $formatter = new HydraFormatter();
 * $response = $formatter->format($exception, 404, includeTrace: true);
 * // Returns: {"@context": "/contexts/Error", "@type": "hydra:Error", ...}
 * ```
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://www.hydra-cg.com/spec/latest/core/ Hydra Core Vocabulary Specification
 * @see ResponseFormatter
 * @psalm-immutable
 */
final readonly class HydraFormatter implements ResponseFormatter
{
    /**
     * Transforms an exception into a Hydra-compliant JSON-LD error response.
     *
     * ```php
     * $response = $formatter->format(
     *     new \Exception('Resource not found'),
     *     404,
     *     includeTrace: false
     * );
     * // Produces: {
     * //   "@context": "/contexts/Error",
     * //   "@type": "hydra:Error",
     * //   "hydra:title": "Not Found",
     * //   "hydra:description": "Resource not found"
     * // }
     * ```
     *
     * @param Throwable            $exception    Exception to transform into Hydra error format. SanitizedException
     *                                           instances provide additional features like error IDs and sanitized traces.
     * @param int                  $status       HTTP status code determining the response status and human-readable title
     *                                           (e.g., 404 generates "Not Found"). Defaults to 500 for server errors.
     * @param bool                 $includeTrace Whether to include sanitized stack trace in response body. Only applies
     *                                           to SanitizedException instances. Useful for debugging without exposing
     *                                           sensitive data. Defaults to false for production safety.
     * @param array<string, mixed> $headers      Additional HTTP headers to merge into the response. Content-Type will
     *                                           be automatically set to application/ld+json regardless of input.
     *
     * @return JsonResponse Hydra-formatted JSON-LD response with application/ld+json Content-Type header
     */
    public function format(
        Throwable $exception,
        int $status = 500,
        bool $includeTrace = false,
        array $headers = [],
    ): JsonResponse {
        $data = [
            '@context' => '/contexts/Error',
            '@type' => 'hydra:Error',
            'hydra:title' => $this->getTitle($status),
            'hydra:description' => $exception->getMessage(),
        ];

        // Add error ID if available
        if ($exception instanceof SanitizedException && $exception->getErrorId()) {
            $data['@id'] = 'urn:uuid:'.$exception->getErrorId();
        }

        // Add sanitized trace if requested
        if ($includeTrace && $exception instanceof SanitizedException) {
            $data['trace'] = $exception->getSanitizedTrace();
        }

        $headers['Content-Type'] = $this->getContentType();

        return new JsonResponse($data, $status, $headers);
    }

    /**
     * Returns the JSON-LD media type for Hydra responses.
     *
     * Hydra uses the standard JSON-LD content type rather than defining
     * a Hydra-specific media type. This aligns with JSON-LD specification
     * and ensures compatibility with JSON-LD processors and semantic web tools.
     *
     * @return string The JSON-LD media type identifier: 'application/ld+json'
     */
    public function getContentType(): string
    {
        return 'application/ld+json';
    }

    /**
     * Converts HTTP status code to human-readable reason phrase.
     *
     * Maps standard HTTP status codes to their official reason phrases
     * for use in the hydra:title field. Covers common client and server
     * error codes, returning a generic "Error" fallback for unmapped codes.
     *
     * @param int $status HTTP status code to convert (e.g., 404, 500)
     *
     * @return string Standard HTTP reason phrase (e.g., 'Not Found', 'Internal Server Error')
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
