<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cloak\Facades;

use Cline\Cloak\CloakManager;
use Cline\Cloak\Contracts\ExceptionSanitizer;
use Cline\Cloak\Http\FormatterRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;

/**
 * Cloak facade for exception sanitization.
 *
 * Provides static access to CloakManager methods for convenient exception
 * sanitization in Laravel applications. Use this facade for one-off exception
 * sanitization or when dependency injection is impractical.
 *
 * ```php
 * // Sanitize and render exception
 * $sanitized = Cloak::sanitizeForRendering($exception, $request);
 *
 * // Create JSON response with specific format
 * return Cloak::toJsonResponse($exception, $request, format: 'json-api');
 *
 * // Check if sanitization is enabled
 * if (Cloak::isEnabled()) {
 *     $sanitized = Cloak::sanitizeForRendering($exception);
 * }
 * ```
 *
 * @method static FormatterRegistry  getFormatterRegistry()
 * @method static ExceptionSanitizer getSanitizer()
 * @method static bool               isEnabled()
 * @method static \Throwable         rethrow(\Throwable $exception, ?Request $request = null)
 * @method static \Throwable         sanitizeForRendering(\Throwable $exception, ?Request $request = null)
 * @method static JsonResponse       toJsonResponse(\Throwable $exception, ?Request $request = null, int $status = 500, bool $includeTrace = false, array<string, mixed> $headers = [], ?string $format = null)
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see CloakManager
 */
final class Cloak extends Facade
{
    /**
     * Get the registered name of the component in the service container.
     *
     * Returns the service container binding key that this facade provides
     * static access to. Resolves to the CloakManager singleton instance.
     *
     * @return string Service container binding key for CloakManager
     */
    protected static function getFacadeAccessor(): string
    {
        return CloakManager::class;
    }
}
