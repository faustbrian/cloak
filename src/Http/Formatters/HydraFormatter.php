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
 * JSON-LD + Hydra error response formatter.
 *
 * Formats error responses according to the Hydra Core Vocabulary specification,
 * which uses JSON-LD to provide semantic, machine-readable API documentation
 * and hypermedia controls. Hydra extends JSON-LD to describe RESTful APIs
 * with rich semantics and is particularly useful for APIs implementing
 * semantic web standards.
 *
 * Specification: https://www.hydra-cg.com/spec/latest/core/
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class HydraFormatter implements ResponseFormatter
{
    /**
     * Format an exception into a Hydra-compliant JSON-LD response.
     *
     * Creates a JSON-LD error response using Hydra vocabulary, including
     * semantic type information, human-readable title based on status code,
     * error description, and optional error identifier. Includes optional
     * stack trace when requested for debugging purposes.
     *
     * @param Throwable            $exception    Exception to format (preferably SanitizedException)
     * @param int                  $status       HTTP status code used to generate human-readable title
     * @param bool                 $includeTrace Whether to include sanitized trace in response
     * @param array<string, mixed> $headers      Additional HTTP headers (Content-Type will be set)
     *
     * @return JsonResponse Hydra-formatted JSON-LD response with application/ld+json content type
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
     * Get the JSON-LD content type header value.
     *
     * Returns the standard JSON-LD MIME type. While Hydra uses JSON-LD as
     * its format, it doesn't define a separate content type, following the
     * JSON-LD specification's 'application/ld+json' type.
     *
     * @return string JSON-LD content type: 'application/ld+json'
     */
    public function getContentType(): string
    {
        return 'application/ld+json';
    }

    /**
     * Get human-readable title for HTTP status code.
     *
     * Maps common HTTP status codes to their standard reason phrases for
     * use in the hydra:title field. Provides semantic meaning to status
     * codes in a format suitable for display and machine processing.
     *
     * @param int $status HTTP status code to translate
     *
     * @return string Human-readable status description (e.g., 'Internal Server Error')
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
