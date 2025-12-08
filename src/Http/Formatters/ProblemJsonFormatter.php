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
 * RFC 7807 Problem Details formatter.
 *
 * Formats errors according to RFC 7807 (Problem Details for HTTP APIs):
 * https://tools.ietf.org/html/rfc7807
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class ProblemJsonFormatter implements ResponseFormatter
{
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

    public function getContentType(): string
    {
        return 'application/problem+json';
    }

    /**
     * Get human-readable title for HTTP status code.
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
