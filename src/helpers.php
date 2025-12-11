<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cloak;

use Cline\Cloak\Facades\Cloak;
use Illuminate\Http\Request;
use ReflectionException;
use Throwable;

use function function_exists;

if (!function_exists('Cline\Cloak\rethrow')) {
    /**
     * Rethrow an exception with sanitized message while preserving class and metadata.
     *
     * Creates a new instance of the original exception class with sanitized message,
     * preserving the original code and previous exception chain. This allows throwing
     * the same exception type with safe messaging instead of wrapping in SanitizedException.
     *
     * @param Throwable    $exception Original exception to rethrow with sanitized message
     * @param null|Request $request   Current HTTP request context for logging
     *
     * @return Throwable New exception instance of same class with sanitized message
     */
    function rethrow(Throwable $exception, ?Request $request = null): Throwable
    {
        return Cloak::rethrow($exception, $request);
    }
}
