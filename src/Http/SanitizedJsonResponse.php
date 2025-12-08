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
 * Sanitized JSON error response.
 *
 * Provides consistent JSON error formatting for sanitized exceptions with support
 * for error IDs and optional stack traces. Extends Laravel's JsonResponse to maintain
 * compatibility with the framework's response handling while adding specialized
 * sanitization features.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class SanitizedJsonResponse extends JsonResponse
{
    /**
     * Create a sanitized JSON response from an exception.
     *
     * Generates a simple JSON error structure containing the exception message, optional
     * error ID (when using SanitizedException), and optional sanitized stack trace. This
     * provides a lightweight alternative to using dedicated formatters while maintaining
     * consistent error response structure.
     *
     * @param Throwable            $exception    The exception to format (preferably SanitizedException for full features)
     * @param int                  $status       HTTP status code to return (defaults to 500 Internal Server Error)
     * @param bool                 $includeTrace Whether to include sanitized stack trace in response (only for SanitizedException)
     * @param array<string, mixed> $headers      Additional HTTP headers to include in the response
     *
     * @return self Sanitized JSON response instance with error data
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
