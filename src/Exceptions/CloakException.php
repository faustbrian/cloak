<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cloak\Exceptions;

use Throwable;

/**
 * Marker interface for all Cloak package exceptions.
 *
 * Provides a common type that consumers can catch to handle any exception
 * originating from the Cloak package. This interface extends PHP's Throwable
 * interface, allowing it to be caught in exception handlers alongside standard
 * exceptions while providing package-specific identification.
 *
 * ```php
 * try {
 *     $manager->toJsonResponse($exception, format: 'invalid-format');
 * } catch (CloakException $e) {
 *     // Handle any Cloak-specific exception
 *     Log::error('Cloak error', ['exception' => $e]);
 * }
 * ```
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see SanitizedException
 * @see FormatterNotFoundException
 *
 * @api
 */
interface CloakException extends Throwable
{
    // Marker interface - no methods required
}
