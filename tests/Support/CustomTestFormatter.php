<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Support;

use Cline\Cloak\Contracts\ResponseFormatter;
use Illuminate\Http\JsonResponse;
use Throwable;

use function array_merge;

/**
 * Custom test formatter for testing custom formatter registration.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class CustomTestFormatter implements ResponseFormatter
{
    public function format(
        Throwable $exception,
        int $status = 500,
        bool $includeTrace = false,
        array $headers = [],
    ): JsonResponse {
        return new JsonResponse(
            [
                'custom_error' => $exception->getMessage(),
                'custom_code' => $exception->getCode(),
            ],
            $status,
            array_merge($headers, ['Content-Type' => $this->getContentType()]),
        );
    }

    public function getContentType(): string
    {
        return 'application/vnd.custom+json';
    }
}
