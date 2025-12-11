<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cloak\Contracts;

use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Response formatter contract for exception rendering.
 *
 * Defines how exceptions should be formatted in HTTP responses according to
 * various API standards and specifications. Implementations provide consistent
 * error response structures for standards like JSON:API, HAL, Hydra, RFC 7807
 * Problem Details, and custom formats.
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface ResponseFormatter
{
    /**
     * Format an exception into a standardized HTTP JSON response.
     *
     * Transforms an exception (ideally a SanitizedException) into a JSON
     * response following the formatter's specific API standard. Handles
     * error structure, status codes, optional trace inclusion, and custom
     * headers while maintaining compliance with the target specification.
     *
     * @param Throwable            $exception    Exception to format (preferably SanitizedException
     *                                           for security). Original exceptions may leak sensitive
     *                                           data if not sanitized before formatting.
     * @param int                  $status       HTTP status code for the response (typically 4xx or 5xx).
     *                                           Defaults to 500 for internal server errors.
     * @param bool                 $includeTrace Whether to include sanitized stack trace in response body.
     *                                           Not recommended for production but useful for debugging.
     * @param array<string, mixed> $headers      Additional HTTP headers to merge into the response.
     *                                           Formatter may override Content-Type header.
     *
     * @return JsonResponse JSON response with exception formatted according to the
     *                      formatter's API standard specification
     */
    public function format(
        Throwable $exception,
        int $status = 500,
        bool $includeTrace = false,
        array $headers = [],
    ): JsonResponse;

    /**
     * Get the Content-Type header value for this formatter.
     *
     * Returns the appropriate MIME type for the formatter's output format,
     * such as 'application/json', 'application/vnd.api+json', 'application/hal+json',
     * 'application/ld+json', or 'application/problem+json'.
     *
     * @return string Content-Type header value specific to this formatter's standard
     */
    public function getContentType(): string;
}
