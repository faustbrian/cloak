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

use function request;

/**
 * HAL (Hypertext Application Language) error response formatter.
 *
 * Formats error responses according to the HAL specification, which extends
 * JSON with hypermedia capabilities through standardized _links and _embedded
 * properties. HAL is commonly used in REST APIs that implement HATEOAS
 * (Hypermedia as the Engine of Application State) principles.
 *
 * ```php
 * $formatter = new HalFormatter();
 * $response = $formatter->format($exception, status: 404);
 *
 * // Response structure:
 * // {
 * //   "message": "Resource not found",
 * //   "status": 404,
 * //   "error_id": "uuid-here",
 * //   "_links": {
 * //     "self": {"href": "https://api.example.com/resource"}
 * //   },
 * //   "_embedded": {
 * //     "trace": [...]
 * //   }
 * // }
 * ```
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see ResponseFormatter
 * @see https://datatracker.ietf.org/doc/html/draft-kelly-json-hal HAL Specification
 * @psalm-immutable
 */
final readonly class HalFormatter implements ResponseFormatter
{
    /**
     * Format an exception into a HAL-compliant JSON response.
     *
     * Creates a HAL-formatted error response containing the error message,
     * status code, optional error ID, and hypermedia links. Includes optional
     * stack trace in the _embedded property when requested. The response
     * always includes a self link referencing the current request URL.
     *
     * @param Throwable            $exception    Exception to format (preferably SanitizedException)
     * @param int                  $status       HTTP status code (default: 500)
     * @param bool                 $includeTrace Whether to include sanitized trace in _embedded
     * @param array<string, mixed> $headers      Additional HTTP headers (Content-Type will be set)
     *
     * @return JsonResponse HAL-formatted response with application/hal+json content type
     */
    public function format(
        Throwable $exception,
        int $status = 500,
        bool $includeTrace = false,
        array $headers = [],
    ): JsonResponse {
        $data = [
            'message' => $exception->getMessage(),
            'status' => $status,
        ];

        // Add error ID if available
        if ($exception instanceof SanitizedException && $exception->getErrorId()) {
            $data['error_id'] = $exception->getErrorId();
        }

        // Add sanitized trace if requested
        if ($includeTrace && $exception instanceof SanitizedException) {
            $data['_embedded'] = [
                'trace' => $exception->getSanitizedTrace(),
            ];
        }

        // HAL requires _links (even if empty for errors)
        $data['_links'] = [
            'self' => [
                'href' => request()->url(),
            ],
        ];

        $headers['Content-Type'] = $this->getContentType();

        return new JsonResponse($data, $status, $headers);
    }

    /**
     * Get the HAL content type header value.
     *
     * Returns the standard HAL+JSON MIME type as specified by the HAL
     * specification. This indicates to clients that the response follows
     * HAL hypermedia conventions.
     *
     * @return string HAL+JSON content type: 'application/hal+json'
     */
    public function getContentType(): string
    {
        return 'application/hal+json';
    }
}
