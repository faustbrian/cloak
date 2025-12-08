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
 * JSON:API error formatter.
 *
 * Formats errors according to JSON:API specification:
 * https://jsonapi.org/format/#errors
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class JsonApiFormatter implements ResponseFormatter
{
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

    public function getContentType(): string
    {
        return 'application/vnd.api+json';
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
