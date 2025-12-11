<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Exceptions;

use RuntimeException;

/**
 * Test exception for simulating context callback failures.
 * @author Brian Faust <brian@cline.sh>
 */
final class ContextCallbackFailedException extends RuntimeException
{
    public static function simulated(): self
    {
        return new self('Context callback failed');
    }
}
