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
 * Provides consistent JSON error formatting for sanitized exceptions.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class SanitizedJsonResponse extends JsonResponse
{
    /**
     * Create a sanitized JSON response from an exception.
     *
     * @param Throwable            $exception    The exception (preferably SanitizedException)
     * @param int                  $status       HTTP status code
     * @param bool                 $includeTrace Whether to include sanitized stack trace
     * @param array<string, mixed> $headers      Additional headers
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
