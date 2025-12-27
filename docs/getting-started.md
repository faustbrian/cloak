---
title: Getting Started
description: Install and configure Cloak to prevent sensitive exception details from leaking in Laravel applications.
---

A security-focused Laravel package that prevents sensitive information from leaking through exception messages and stack traces.

## Requirements

Cloak requires PHP 8.5+ and Laravel 11+ or 12+.

## Installation

Install Cloak with composer:

```bash
composer require cline/cloak
```

The package will auto-register via Laravel's package discovery.

## Publish Configuration

Publish the configuration file to customize patterns and behavior:

```bash
php artisan vendor:publish --tag="cloak-config"
```

This creates `config/cloak.php` with extensive configuration options.

## Basic Usage

### Automatic Integration

In Laravel 12+, integrate Cloak into your exception handler in `bootstrap/app.php`:

```php
use Cline\Cloak\Facades\Cloak;

->withExceptions(function (Exceptions $exceptions) {
    $exceptions->render(function (Throwable $e, Request $request) {
        return Cloak::sanitizeForRendering($e, $request);
    });
})
```

### Manual Sanitization

You can also sanitize exceptions manually:

```php
use Cline\Cloak\Facades\Cloak;

try {
    // Code that might throw exceptions with sensitive data
    DB::connection('mysql://root:password@localhost/mydb')->select('...');
} catch (Throwable $e) {
    $sanitized = Cloak::sanitizeForRendering($e);
    Log::error($sanitized->getMessage());
}
```

### Using the Manager

For more control, inject the `CloakManager`:

```php
use Cline\Cloak\CloakManager;

class ExceptionHandler
{
    public function __construct(
        private CloakManager $cloak
    ) {}

    public function render($request, Throwable $e)
    {
        if ($this->cloak->isEnabled()) {
            $e = $this->cloak->sanitizeForRendering($e, $request);
        }

        return parent::render($request, $e);
    }
}
```

## What Gets Sanitized

By default, Cloak redacts:

- **Database credentials**: MySQL, PostgreSQL, MongoDB, Redis connection strings
- **API keys and tokens**: Bearer tokens, API keys, AWS credentials
- **Private keys**: PEM-formatted private keys and certificates
- **Email addresses**: In exception contexts
- **File paths**: User directories and system paths
- **IP addresses**: When flagged as sensitive

## Configuration Overview

Key configuration options in `config/cloak.php`:

```php
return [
    // Enable/disable sanitization
    'enabled' => env('CLOAK_ENABLED', true),

    // Sanitize even in debug mode
    'sanitize_in_debug' => false,

    // Log original exceptions before sanitization
    'log_original' => true,

    // Exception types to always sanitize
    'sanitize_exceptions' => [
        \Illuminate\Database\QueryException::class,
        \PDOException::class,
    ],

    // Generic messages per exception type
    'generic_messages' => [
        \Illuminate\Database\QueryException::class =>
            'A database error occurred.',
    ],
];
```

## Debug Mode Behavior

By default, Cloak **does not sanitize** when `APP_DEBUG=true`. This lets you see full details during development.

To sanitize even in debug mode:

```php
'sanitize_in_debug' => true,
```

## Logging Original Exceptions

Cloak logs the original unsanitized exception before sanitizing it for the response. This ensures you can debug issues while protecting end users:

```php
'log_original' => true,
```

Original exceptions are logged with context including:
- Exception class
- Full message
- File and line number
- Request URL and method (if available)

## Error ID Tracking

Enable unique error IDs in sanitized messages so customers can reference specific errors when reporting issues:

```php
// In .env
CLOAK_ERROR_ID_TYPE=uuid  // or 'ulid', or null to disable
CLOAK_ERROR_ID_CONTEXT_KEY=exception_id  // customize the context key
```

When enabled, sanitized exceptions include the ID:

```
A database error occurred. [Error ID: 87ccc529-0646-4d06-a5b8-4137a88fb405]
```

Error IDs are automatically:
- Included in the exception message for users
- Stored in Laravel's `Context` for logging and monitoring
- Available to tools like Laravel Nightwatch
- Accessible via `$exception->getErrorId()`

This makes it easy to correlate user reports with server logs:

```php
// User reports: "I got error ID 87ccc529-0646-4d06..."
// You search logs for: exception_id:87ccc529
// Find the original unsanitized exception with full details
```

## Stack Trace Sanitization

Cloak can sanitize stack traces to prevent leaking:
- Server directory structures
- Function arguments containing sensitive data
- File paths with usernames

```php
// config/cloak.php
return [
    'sanitize_stack_traces' => true,

    'patterns' => [
        '/\/home\/([^\/]+)/i',
        '/\/Users\/([^\/]+)/i',
    ],
];
```

Stack traces are sanitized by:
- Applying all configured patterns to file paths
- **Omitting function arguments** to prevent leaking sensitive parameters
- Preserving class names, function names, and line numbers for debugging

## Custom Context Injection

Add custom context data (user ID, tenant ID, request ID) to all exceptions:

```php
// config/cloak.php
return [
    'context' => [
        'user_id' => fn () => auth()->id(),
        'tenant_id' => fn () => tenant()?->id,
        'request_id' => fn () => request()->header('X-Request-ID'),
    ],
];
```

Context is automatically:
- Added to Laravel's Context system
- Available in logs and monitoring tools (Nightwatch, etc.)
- Executed only when exceptions are sanitized

## Exception Tags

Categorize exceptions for filtering and alerting:

```php
// config/cloak.php
return [
    'tags' => [
        \Illuminate\Database\QueryException::class => ['database', 'critical'],
        \Stripe\Exception\CardException::class => ['payment', 'third-party'],
    ],
];
```

Tags enable:
- Filtering in monitoring tools (e.g., `exception_tags:critical`)
- Setting up category-based alerts
- Grouping exceptions in dashboards

## API Error Responses

Create consistent JSON error responses:

```php
use Cline\Cloak\Facades\Cloak;

->withExceptions(function (Exceptions $exceptions) {
    $exceptions->render(function (Throwable $e, Request $request) {
        if ($request->is('api/*')) {
            return Cloak::toJsonResponse($e, $request);
        }

        return Cloak::sanitizeForRendering($e, $request);
    });
})
```

Produces consistent responses:
```json
{
    "error": "A database error occurred.",
    "error_id": "87ccc529-0646-4d06-a5b8-4137a88fb405"
}
```

## Next Steps

- Learn about [custom patterns](patterns.md) to match your specific needs
- Explore [exception handling strategies](exception-handling.md)
- Review [security best practices](security-best-practices.md)
- See [real-world examples](examples.md)
