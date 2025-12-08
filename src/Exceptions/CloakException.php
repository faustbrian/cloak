<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cloak\Exceptions;

use RuntimeException;

/**
 * Base exception for Cloak package.
 *
 * All Cloak-specific exceptions extend from this base exception, providing
 * a common exception type for error handling. Used for internal Cloak errors
 * such as configuration issues, invalid formatter names, or other operational
 * failures within the package itself.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class CloakException extends RuntimeException {}
