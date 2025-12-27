---
title: Exception Handling
description: Configure how Cloak handles different exception types with whitelists, blacklists, and generic messages.
---

Cloak provides fine-grained control over which exceptions to sanitize and how to sanitize them.

## Exception Type Configuration

### Always Sanitize Specific Types

Force sanitization for specific exception types regardless of content:

```php
'sanitize_exceptions' => [
    \Illuminate\Database\QueryException::class,
    \PDOException::class,
    \Doctrine\DBAL\Exception::class,
    \League\Flysystem\FilesystemException::class,
    \Aws\Exception\AwsException::class,
],
```

These exceptions will **always** be sanitized, even if they don't match any patterns.

### Never Sanitize Specific Types

Whitelist exceptions that are safe to display:

```php
'allowed_exceptions' => [
    \App\Exceptions\UserFacingException::class,
    \App\Exceptions\ValidationException::class,
    \Illuminate\Validation\ValidationException::class,
],
```

These exceptions will **never** be sanitized, even if they match patterns.

## Generic Messages

Replace entire exception messages with generic ones:

```php
'generic_messages' => [
    \Illuminate\Database\QueryException::class =>
        'A database error occurred while processing your request.',

    \PDOException::class =>
        'A database connection error occurred.',

    \Aws\Exception\AwsException::class =>
        'An external service error occurred.',
],
```

### Benefits of Generic Messages

1. **Zero information leakage** - No details exposed at all
2. **User-friendly** - Clear, non-technical messages
3. **Consistent** - Same message for all instances

### When to Use Generic Messages

Use generic messages for:

- **Database exceptions** - Never expose queries or connection details
- **External service errors** - Hide API credentials and endpoints
- **File system errors** - Prevent path disclosure
- **Authentication errors** - Avoid user enumeration

```php
'generic_messages' => [
    // Database
    QueryException::class => 'A database error occurred.',
    PDOException::class => 'Database connection failed.',

    // External services
    AwsException::class => 'Cloud service error.',
    GuzzleException::class => 'External API error.',

    // File system
    FilesystemException::class => 'File operation failed.',
    UnreadableFileException::class => 'Cannot read file.',

    // Authentication
    AuthenticationException::class => 'Authentication failed.',
    TooManyRequestsException::class => 'Too many requests.',
],
```

## Decision Flow

Cloak evaluates exceptions in this order:

1. **Is it an allowed exception?** → Don't sanitize
2. **Is debug mode on and sanitize_in_debug false?** → Don't sanitize
3. **Is it in sanitize_exceptions?** → Sanitize with generic message or patterns
4. **Does message match sensitive patterns?** → Sanitize with patterns
5. **Otherwise** → Don't sanitize

```php
┌─────────────────────────┐
│   Exception Thrown      │
└──────────┬──────────────┘
           │
           ▼
┌─────────────────────────┐
│  Allowed Exception?     │───Yes──→ Return Original
└──────────┬──────────────┘
           No
           ▼
┌─────────────────────────┐
│  Debug Mode &           │───Yes──→ Return Original
│  !sanitize_in_debug?    │
└──────────┬──────────────┘
           No
           ▼
┌─────────────────────────┐
│  In sanitize_exceptions │───Yes──→ Generic Message
│  list?                  │          or Pattern Sanitize
└──────────┬──────────────┘
           No
           ▼
┌─────────────────────────┐
│  Matches sensitive      │───Yes──→ Pattern Sanitize
│  patterns?              │
└──────────┬──────────────┘
           No
           ▼
┌─────────────────────────┐
│   Return Original       │
└─────────────────────────┘
```

## Custom Exception Sanitizers

Implement custom logic by creating your own sanitizer:

```php
use Cline\Cloak\Contracts\ExceptionSanitizer;
use Throwable;

class CustomSanitizer implements ExceptionSanitizer
{
    public function sanitize(Throwable $exception): Throwable
    {
        // Your custom logic
        if ($exception instanceof SensitiveException) {
            return new SanitizedException(
                'Custom sanitized message',
                $exception->getCode(),
                $exception
            );
        }

        return $exception;
    }

    public function shouldSanitize(Throwable $exception): bool
    {
        return $exception instanceof SensitiveException;
    }

    public function sanitizeMessage(string $message): string
    {
        return preg_replace('/sensitive-data/', '[REDACTED]', $message);
    }
}
```

Register your custom sanitizer:

```php
use Cline\Cloak\Contracts\ExceptionSanitizer;

$this->app->singleton(ExceptionSanitizer::class, function () {
    return new CustomSanitizer();
});
```

## Conditional Sanitization

Sanitize based on runtime conditions:

```php
use Cline\Cloak\Facades\Cloak;

public function render($request, Throwable $e)
{
    // Only sanitize for non-admin users
    if (!$request->user()?->isAdmin()) {
        $e = Cloak::sanitizeForRendering($e, $request);
    }

    return parent::render($request, $e);
}
```

### Per-Environment Sanitization

```php
$exceptions->render(function (Throwable $e, Request $request) {
    // Different behavior per environment
    return match (app()->environment()) {
        'production' => Cloak::sanitizeForRendering($e, $request),
        'staging' => $e, // Full details in staging
        'local' => $e,   // Full details locally
    };
});
```

### Per-Route Sanitization

```php
$exceptions->render(function (Throwable $e, Request $request) {
    // Sanitize API routes more aggressively
    if ($request->is('api/*')) {
        return Cloak::sanitizeForRendering($e, $request);
    }

    return $e;
});
```

## Rethrowing with Sanitized Messages

Use `rethrow()` to recreate the original exception class with a sanitized message:

```php
use Cline\Cloak\Facades\Cloak;

try {
    throw new RuntimeException('Database error: mysql://root:password@localhost/db', 123);
} catch (Throwable $e) {
    // Recreates RuntimeException with sanitized message, preserving code and previous
    throw Cloak::rethrow($e);
}
```

**Or use the helper function for cleaner code:**

```php
use function Cline\Cloak\rethrow;

try {
    throw new RuntimeException('Database error: mysql://root:password@localhost/db', 123);
} catch (Throwable $e) {
    throw rethrow($e);
}
```

This returns the **original exception type** (not `SanitizedException`) with:
- ✅ Sanitized message
- ✅ Original exception code preserved
- ✅ Previous exception chain preserved

**Use when:**
- You want to throw the same exception type with sanitized message
- You need to preserve exception instanceof checks
- You're rethrowing in middleware or exception handlers

**Example:**

```php
public function handle($request, Closure $next)
{
    try {
        return $next($request);
    } catch (Throwable $e) {
        // Rethrow same exception type with sanitized message
        throw Cloak::rethrow($e, $request);
    }
}
```

## Original Exception Preservation

Sanitized exceptions wrap the original:

```php
use Cline\Cloak\Exceptions\SanitizedException;

try {
    // ...
} catch (Throwable $e) {
    $sanitized = Cloak::sanitizeForRendering($e);

    if ($sanitized instanceof SanitizedException) {
        // Get original exception for logging
        $original = $sanitized->getOriginalException();

        Log::error('Original exception', [
            'message' => $original->getMessage(),
            'trace' => $original->getTraceAsString(),
        ]);

        // Return sanitized to user
        return response()->json([
            'error' => $sanitized->getMessage(),
        ], 500);
    }
}
```

## Exception Code Preservation

Sanitized exceptions preserve the original code:

```php
try {
    throw new CustomException('Sensitive data: password123', 1234);
} catch (Throwable $e) {
    $sanitized = Cloak::sanitizeForRendering($e);

    // Code is preserved
    assert($sanitized->getCode() === 1234); // ✅
}
```

## Testing Exception Handling

Test your sanitization logic:

```php
use Cline\Cloak\CloakManager;
use Illuminate\Database\QueryException;

test('sanitizes database exceptions', function () {
    config(['cloak.enabled' => true]);

    $pdo = new PDOException('SQLSTATE[HY000]: mysql://root:secret@localhost/db');
    $exception = new QueryException('default', 'SELECT *', [], $pdo);

    $manager = app(CloakManager::class);
    $sanitized = $manager->sanitizeForRendering($exception);

    expect($sanitized->getMessage())
        ->not->toContain('secret')
        ->not->toContain('mysql://');
});
```

## Next Steps

- Learn about [generic messages](generic-messages.md) for complete redaction
- Explore [logging strategies](logging.md) for debugging
- Review [security best practices](security-best-practices.md)
