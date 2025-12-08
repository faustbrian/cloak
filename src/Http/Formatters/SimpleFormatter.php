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
 * Simple JSON error formatter.
 *
 * Default format with minimal structure:
 * {
 *   "error": "message",
 *   "error_id": "uuid"
 * }
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class SimpleFormatter implements ResponseFormatter
{
    /**
     * Format an exception into a simple JSON error response.
     *
     * Creates a minimal JSON response with an error message and optional error_id
     * and trace fields. This is the default formatter providing a lightweight error
     * structure without adhering to specific API error standards.
     *
     * @param Throwable            $exception    The exception to format into a simple error response
     * @param int                  $status       HTTP status code to return (defaults to 500 Internal Server Error)
     * @param bool                 $includeTrace Whether to include sanitized stack trace field (only for SanitizedException)
     * @param array<string, mixed> $headers      Additional HTTP headers to include in the response
     *
     * @return JsonResponse Simple formatted error response with application/json Content-Type
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
     * Get the Content-Type header value for simple JSON responses.
     *
     * @return string The standard JSON media type
     */
    public function getContentType(): string
    {
        return 'application/json';
    }
}
