<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cloak\Exceptions;

use InvalidArgumentException;

use function sprintf;

/**
 * Exception thrown when a response formatter is not found in the registry.
 *
 * Indicates that a requested formatter name does not exist in the FormatterRegistry.
 * This typically occurs when attempting to use a custom formatter that hasn't been
 * registered, or when a typo is made in the formatter name. Built-in formatters
 * include: 'simple', 'json-api', 'hal', 'hydra', and 'problem-json'.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see FormatterRegistry
 * @see ResponseFormatter
 */
final class FormatterNotFoundException extends InvalidArgumentException implements CloakException
{
    /**
     * Create a formatter not found exception for the given formatter name.
     *
     * Factory method that generates a formatted error message indicating which
     * formatter was requested but not found in the registry.
     *
     * @param string $name The formatter name that was not found
     *
     * @return self Exception instance with descriptive error message
     */
    public static function forName(string $name): self
    {
        return new self(sprintf("Response formatter '%s' not found.", $name));
    }
}
