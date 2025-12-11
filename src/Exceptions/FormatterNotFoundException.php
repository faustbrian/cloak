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
 * @author Brian Faust <brian@cline.sh>
 */
final class FormatterNotFoundException extends InvalidArgumentException implements CloakException
{
    public static function forName(string $name): self
    {
        return new self(sprintf("Response formatter '%s' not found.", $name));
    }
}
