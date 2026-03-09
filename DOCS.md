## Table of Contents

1. Overview (`docs/README.md`)
2. Examples (`docs/examples.md`)
3. Exception Handling (`docs/exception-handling.md`)
4. Patterns (`docs/patterns.md`)
5. Security Best Practices (`docs/security-best-practices.md`)
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

Learn how to use Cloak through real-world scenarios and examples.

## Database Connection Leaks

### Problem

Database exceptions often expose connection strings:

```php
// ❌ Without Cloak
SQLSTATE[HY000] [2002] Connection failed: mysql://root:mySecretP@ss@db-prod.company.com/production_db
```

### Solution

```php
// ✅ With Cloak
A database error occurred while processing your request.
```

### Configuration

```php
'sanitize_exceptions' => [
    \Illuminate\Database\QueryException::class,
    \PDOException::class,
],

'generic_messages' => [
    \Illuminate\Database\QueryException::class =>
        'A database error occurred while processing your request.',
],
```

## API Key Exposure

### Problem

API client exceptions can leak credentials:

```php
// ❌ Without Cloak
HTTP 401: Invalid API key: prod_abc123def456ghi789jkl
Bearer: eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.example.token
```

### Solution

```php
// ✅ With Cloak
HTTP 401: Invalid API key: [REDACTED]
Bearer: [REDACTED]
```

### Configuration

```php
'patterns' => [
    '/api[_-]?key["\']?\s*[:=]\s*["\']?([a-zA-Z0-9_\-]+)/i',
    '/bearer\s+([a-zA-Z0-9_\-\.]+)/i',
],
```

## AWS Credentials in Logs

### Problem

Cloud service errors can expose AWS credentials:

```php
// ❌ Without Cloak
AWS Error: Invalid credentials
AWS_ACCESS_KEY_ID=AKIAIOSFODNN7EXAMPLE
AWS_SECRET_ACCESS_KEY=wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY
```

### Solution

```php
// ✅ With Cloak
AWS Error: Invalid credentials
AWS_ACCESS_KEY_ID=[REDACTED]
AWS_SECRET_ACCESS_KEY=[REDACTED]
```

### Configuration

```php
'patterns' => [
    '/aws[_-]?access[_-]?key[_-]?id["\']?\s*[:=]\s*["\']?([A-Z0-9]+)/i',
    '/aws[_-]?secret[_-]?access[_-]?key["\']?\s*[:=]\s*["\']?([A-Za-z0-9\/\+]+)/i',
],

'sanitize_exceptions' => [
    \Aws\Exception\AwsException::class,
],
```

## File Path Disclosure

### Problem

File operations can reveal system paths:

```php
// ❌ Without Cloak
Error opening file: /Users/john.doe/projects/acme-corp/storage/app/secrets.json
```

### Solution

```php
// ✅ With Cloak
Error opening file: /Users/[REDACTED]/projects/acme-corp/storage/app/secrets.json
```

### Configuration

```php
'patterns' => [
    '/\/Users\/([^\/\s]+)/i',
    '/\/home\/([^\/\s]+)/i',
    '/C:\\\\Users\\\\([^\\\\]+)/i',
],
```

## Rethrowing Exceptions with Sanitized Messages

### Problem

You need to rethrow the same exception type with sanitized message (not wrapped in `SanitizedException`):

```php
// ❌ Loses exception type
throw Cloak::sanitizeForRendering($e); // Returns SanitizedException

// ❌ instanceof checks break
if ($e instanceof PDOException) { } // False - it's now SanitizedException
```

### Solution

Use `rethrow()` to recreate the original exception class:

```php
use function Cline\Cloak\rethrow;

public function handle($request, Closure $next)
{
    try {
        return $next($request);
    } catch (Throwable $e) {
        // Recreates original exception type with sanitized message
        throw rethrow($e, $request);
    }
}
```

### Benefits

- ✅ Preserves original exception class (instanceof checks work)
- ✅ Sanitizes message (removes sensitive data)
- ✅ Preserves exception code
- ✅ Preserves previous exception chain
- ✅ Logs original exception automatically

### Example

```php
use function Cline\Cloak\rethrow;

try {
    throw new PDOException('mysql://root:password@localhost/db', 1234);
} catch (Throwable $e) {
    $rethrown = rethrow($e);

    // ✅ Still a PDOException (not SanitizedException)
    assert($rethrown instanceof PDOException);

    // ✅ Message is sanitized
    assert($rethrown->getMessage() === 'mysql://root:[REDACTED]@localhost/db');

    // ✅ Code preserved
    assert($rethrown->getCode() === 1234);

    throw $rethrown;
}
```

## Multi-Tenant Application

### Problem

Tenant-specific data in exceptions:

```php
// ❌ Without Cloak
Query failed for tenant 'acme_corp' using database: acme_prod_db
Connection: mysql://acme_user:acmePass2024@mysql-acme.internal:3306/acme_prod_db
```

### Solution

Create tenant-aware sanitization:

```php
use Cline\Cloak\Facades\Cloak;

class TenantExceptionHandler
{
    public function render($request, Throwable $e)
    {
        $sanitized = Cloak::sanitizeForRendering($e, $request);

        // Additional tenant sanitization
        $tenant = $request->tenant();
        $message = str_replace(
            [$tenant->name, $tenant->database],
            ['[TENANT]', '[DATABASE]'],
            $sanitized->getMessage()
        );

        return response()->json(['error' => $message], 500);
    }
}
```

### Result

```php
// ✅ Sanitized
Query failed for tenant '[TENANT]' using database: [DATABASE]
Connection: [REDACTED]
```

## Email in Exception Messages

### Problem

User emails in error messages:

```php
// ❌ Without Cloak
User john.doe@company.com not found in system
Email validation failed for admin@internal-company-domain.com
```

### Solution

```php
// ✅ With Cloak
User [REDACTED] not found in system
Email validation failed for [REDACTED]
```

### Configuration

```php
'patterns' => [
    '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/',
],
```

## Development vs Production

### Problem

Need full details in development, sanitized in production:

### Solution

Environment-specific configuration:

```php
// config/cloak.php
return [
    'enabled' => env('CLOAK_ENABLED', app()->environment('production')),

    'sanitize_in_debug' => env('CLOAK_SANITIZE_IN_DEBUG', false),

    'patterns' => app()->environment('production') ? [
        // Aggressive sanitization in production
        '/mysql:\/\//i',
        '/postgres:\/\//i',
        '/password/i',
        '/secret/i',
        '/token/i',
        '/api[_-]?key/i',
    ] : [
        // Minimal sanitization in development
        '/password=([^\s;]+)/i',
    ],
];
```

## Logging Original Exceptions

### Problem

Need to debug while showing sanitized messages:

### Solution

Cloak automatically logs originals:

```php
'log_original' => true,
```

Then in logs:

```php
// Log entry
[2024-01-15 10:30:45] production.ERROR: Original exception before sanitization
{
    "exception": "PDOException",
    "message": "SQLSTATE[HY000]: mysql://root:secret@localhost/db",
    "file": "/var/www/app/Services/DatabaseService.php",
    "line": 42,
    "url": "https://api.example.com/users",
    "method": "GET"
}
```

## API vs Web Responses

### Problem

Different sanitization needs for API vs web:

### Solution

```php
use Cline\Cloak\Facades\Cloak;

->withExceptions(function (Exceptions $exceptions) {
    $exceptions->render(function (Throwable $e, Request $request) {
        // API routes get aggressive sanitization
        if ($request->is('api/*')) {
            config(['cloak.sanitize_in_debug' => true]);
            $e = Cloak::sanitizeForRendering($e, $request);

            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }

        // Web routes get normal sanitization
        return Cloak::sanitizeForRendering($e, $request);
    });
})
```

## Custom Exception Types

### Problem

Your custom exceptions need sanitization:

### Solution

```php
namespace App\Exceptions;

use RuntimeException;

class PaymentException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $transactionId,
        public readonly string $gatewayResponse,
    ) {
        parent::__construct($message);
    }
}
```

Configure Cloak to sanitize it:

```php
'sanitize_exceptions' => [
    \App\Exceptions\PaymentException::class,
],

'generic_messages' => [
    \App\Exceptions\PaymentException::class =>
        'A payment processing error occurred. Please try again.',
],
```

## Stack Trace Sanitization

### Problem

Stack traces can leak file paths and function arguments:

```php
// ❌ Without sanitization
#0 /home/production-user/apps/secret-project/app/Services/PaymentService.php(42): PaymentService->processPayment('secret-api-key', 'password123')
#1 /home/production-user/apps/secret-project/app/Controllers/PaymentController.php(100)
```

### Solution

Cloak automatically sanitizes stack traces when enabled:

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

**Result:**
```php
// ✅ Sanitized
#0 /home/[REDACTED]/apps/secret-project/app/Services/PaymentService.php(42): PaymentService->processPayment()
#1 /home/[REDACTED]/apps/secret-project/app/Controllers/PaymentController.php(100)
```

Cloak automatically:
- Applies your configured patterns to file paths
- **Omits function arguments** to prevent leaking sensitive data passed as parameters
- Preserves class and function names for debugging
- Preserves line numbers

## Testing Sanitization

Test your configuration:

```php
use Cline\Cloak\CloakManager;

test('sanitizes production database errors', function () {
    config([
        'cloak.enabled' => true,
        'cloak.sanitize_exceptions' => [QueryException::class],
        'cloak.generic_messages' => [
            QueryException::class => 'Database error occurred.',
        ],
    ]);

    $pdo = new PDOException('mysql://root:secret@prod-db/app');
    $exception = new QueryException('default', 'SELECT *', [], $pdo);

    $manager = app(CloakManager::class);
    $sanitized = $manager->sanitizeForRendering($exception);

    expect($sanitized->getMessage())
        ->toBe('Database error occurred.')
        ->not->toContain('secret')
        ->not->toContain('prod-db');
});
```

## Error ID Tracking with Nightwatch

### Problem

When customers report errors, you need to correlate their reports with server logs:

```php
// Customer: "I got an error when checking out"
// You: "Which error? When? What page?"
// 🤷 Hard to find the specific exception
```

### Solution

Enable error ID tracking:

```bash
# .env
CLOAK_ERROR_ID_TYPE=uuid
CLOAK_ERROR_ID_CONTEXT_KEY=exception_id
```

```php
// ✅ Customer sees:
A database error occurred. [Error ID: 87ccc529-0646-4d06-a5b8-4137a88fb405]

// ✅ You search Nightwatch for: exception_id:87ccc529
// Find the original exception with full details
```

### Configuration

```php
return [
    // Generate UUID for each sanitized exception
    'error_id_type' => 'uuid', // or 'ulid', or null

    // Include ID in message
    'error_id_template' => '{message} [Error ID: {id}]',

    // Store in Context for Nightwatch
    'error_id_context_key' => 'exception_id',
];
```

### Accessing Error IDs

```php
try {
    // ... code that throws
} catch (Throwable $e) {
    $sanitized = Cloak::sanitizeForRendering($e);

    // Get the error ID
    if ($sanitized instanceof SanitizedException) {
        $errorId = $sanitized->getErrorId();

        // Show to user, include in support emails, etc.
        return response()->json([
            'error' => $sanitized->getMessage(),
            'error_id' => $errorId,
        ], 500);
    }
}
```

Laravel Context automatically includes the error ID in all logs and monitoring tools like Nightwatch.

## Custom Context Injection

### Problem

You need additional context data (user ID, tenant ID, request ID) logged with exceptions for debugging:

```php
// ❌ Missing context
[2024-01-15 10:30:45] production.ERROR: Database connection failed
// Which user? Which tenant? Hard to track down!
```

### Solution

Configure context callbacks to automatically inject data:

```php
// config/cloak.php
return [
    'context' => [
        'user_id' => fn () => auth()->id(),
        'tenant_id' => fn () => tenant()?->id,
        'request_id' => fn () => request()->header('X-Request-ID'),
        'ip_address' => fn () => request()->ip(),
    ],
];
```

These callbacks execute when an exception is sanitized, and results are stored in Laravel Context for logging and monitoring.

### Result

```php
// ✅ Full context in logs
[2024-01-15 10:30:45] production.ERROR: Database connection failed
{
    "exception_id": "87ccc529-0646-4d06-a5b8-4137a88fb405",
    "user_id": 123,
    "tenant_id": "acme-corp",
    "request_id": "req_abc123",
    "ip_address": "192.168.1.100"
}
```

**Important notes:**
- Callbacks that throw exceptions are silently ignored (won't break sanitization)
- Callbacks returning `null` are skipped
- All context is automatically available to Nightwatch and other monitoring tools

## Exception Tags and Categories

### Problem

Need to categorize exceptions for filtering and alerting:

```php
// ❌ No categorization
// All exceptions look the same in monitoring
// Can't filter by severity or type
```

### Solution

Tag exceptions by class for automatic categorization:

```php
// config/cloak.php
return [
    'tags' => [
        \Illuminate\Database\QueryException::class => ['database', 'critical'],
        \PDOException::class => ['database', 'critical'],
        \Stripe\Exception\CardException::class => ['payment', 'third-party'],
        \Stripe\Exception\ApiErrorException::class => ['payment', 'third-party', 'critical'],
        \App\Exceptions\RateLimitException::class => ['rate-limit', 'warning'],
    ],
];
```

Tags are automatically added to Laravel Context as `exception_tags`.

### Result

**In Nightwatch or logs:**
```json
{
    "exception_id": "87ccc529-0646-4d06-a5b8-4137a88fb405",
    "exception_tags": ["database", "critical"],
    "user_id": 123
}
```

**Use cases:**
- Filter Nightwatch by `exception_tags:critical` to see only critical errors
- Set up alerts for specific categories (e.g., alert on `payment` + `critical`)
- Group exceptions by type in dashboards
- Route errors to different teams based on tags

## API Error Responses

### Problem

Inconsistent error response formats across API endpoints and no support for industry-standard API formats:

```php
// ❌ Inconsistent
Route::post('/users', function () {
    try {
        // ...
    } catch (Throwable $e) {
        return response()->json(['message' => $e->getMessage()], 500);
    }
});

Route::post('/orders', function () {
    try {
        // ...
    } catch (Throwable $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
});
```

### Solution

Cloak supports **all formats from API Platform**:
- **Simple** - Basic JSON (default)
- **JSON:API** - [JSON:API specification](https://jsonapi.org/)
- **Problem+JSON** - [RFC 7807](https://tools.ietf.org/html/rfc7807)
- **HAL** - [Hypertext Application Language](https://datatracker.ietf.org/doc/html/draft-kelly-json-hal)
- **Hydra** - [JSON-LD + Hydra](https://www.hydra-cg.com/)

**Configure globally:**
```php
// config/cloak.php or .env
'error_response_format' => 'json-api', // or problem-json, hal, hydra, simple
```

**Or specify per-response:**
```php
use Cline\Cloak\Facades\Cloak;

->withExceptions(function (Exceptions $exceptions) {
    $exceptions->render(function (Throwable $e, Request $request) {
        if ($request->is('api/*')) {
            return Cloak::toJsonResponse(
                exception: $e,
                request: $request,
                format: 'json-api' // Override default
            );
        }

        return Cloak::sanitizeForRendering($e, $request);
    });
})
```

### Format Examples

**Simple (default):**
```json
{
    "error": "A database error occurred.",
    "error_id": "87ccc529-0646-4d06-a5b8-4137a88fb405"
}
```

**JSON:API:**
```json
{
    "errors": [{
        "id": "87ccc529-0646-4d06-a5b8-4137a88fb405",
        "status": "500",
        "title": "Internal Server Error",
        "detail": "A database error occurred."
    }]
}
```

**Problem+JSON (RFC 7807):**
```json
{
    "type": "about:blank",
    "title": "Internal Server Error",
    "status": 500,
    "detail": "A database error occurred.",
    "instance": "urn:uuid:87ccc529-0646-4d06-a5b8-4137a88fb405"
}
```

**HAL:**
```json
{
    "message": "A database error occurred.",
    "status": 500,
    "error_id": "87ccc529-0646-4d06-a5b8-4137a88fb405",
    "_links": {
        "self": {"href": "/api/users"}
    }
}
```

**Hydra (JSON-LD):**
```json
{
    "@context": "/contexts/Error",
    "@type": "hydra:Error",
    "@id": "urn:uuid:87ccc529-0646-4d06-a5b8-4137a88fb405",
    "hydra:title": "Internal Server Error",
    "hydra:description": "A database error occurred."
}
```

### Custom Formatters

Create your own formatter:

```php
namespace App\Http\Formatters;

use Cline\Cloak\Contracts\ResponseFormatter;
use Illuminate\Http\JsonResponse;
use Throwable;

class MyCustomFormatter implements ResponseFormatter
{
    public function format(
        Throwable $exception,
        int $status = 500,
        bool $includeTrace = false,
        array $headers = [],
    ): JsonResponse {
        $data = [
            'success' => false,
            'message' => $exception->getMessage(),
            'code' => $status,
        ];

        return new JsonResponse($data, $status, $headers);
    }

    public function getContentType(): string
    {
        return 'application/vnd.myapi+json';
    }
}
```

Register it:
```php
// config/cloak.php
'custom_formatters' => [
    'my-format' => \App\Http\Formatters\MyCustomFormatter::class,
],

// Use it
Cloak::toJsonResponse($exception, format: 'my-format');
```

## Next Steps

- Review [security best practices](security-best-practices.md)
- Learn about [custom patterns](patterns.md)
- Explore [exception handling strategies](exception-handling.md)

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

Cloak uses regex patterns to identify and redact sensitive information. You can customize these patterns to match your application's specific needs.

## Default Patterns

Cloak ships with patterns for common sensitive data:

### Database Connections

```php
'patterns' => [
    // MySQL connections
    '/mysql:\/\/([^:]+):([^@]+)@([^\/]+)\/(.+)/i',

    // PostgreSQL connections
    '/postgres:\/\/([^:]+):([^@]+)@([^\/]+)\/(.+)/i',

    // MongoDB connections
    '/mongodb:\/\/([^:]+):([^@]+)@([^\/]+)\/(.+)/i',

    // Redis connections
    '/redis:\/\/([^:]+):([^@]+)@([^\/]+)/i',
],
```

### DSN Format

```php
'patterns' => [
    '/host=([^\s;]+)/i',
    '/user=([^\s;]+)/i',
    '/password=([^\s;]+)/i',
    '/dbname=([^\s;]+)/i',
],
```

### API Keys and Tokens

```php
'patterns' => [
    // Generic API keys
    '/api[_-]?key["\']?\s*[:=]\s*["\']?([a-zA-Z0-9_\-]+)/i',

    // Tokens
    '/token["\']?\s*[:=]\s*["\']?([a-zA-Z0-9_\-\.]+)/i',

    // Bearer tokens
    '/bearer\s+([a-zA-Z0-9_\-\.]+)/i',
],
```

### Cloud Provider Credentials

```php
'patterns' => [
    // AWS Access Keys
    '/aws[_-]?access[_-]?key[_-]?id["\']?\s*[:=]\s*["\']?([A-Z0-9]+)/i',

    // AWS Secret Keys
    '/aws[_-]?secret[_-]?access[_-]?key["\']?\s*[:=]\s*["\']?([A-Za-z0-9\/\+]+)/i',
],
```

## Adding Custom Patterns

Add your own patterns in `config/cloak.php`:

```php
'patterns' => [
    // Add to existing patterns
    ...config('cloak.patterns'),

    // Custom patterns
    '/your-custom-pattern-here/i',
    '/secret[_-]?token["\']?\s*[:=]\s*["\']?([a-zA-Z0-9]+)/i',
],
```

## Pattern Best Practices

### 1. Use Case-Insensitive Matching

Always use the `i` flag for case-insensitive matching:

```php
'/api[_-]?key/i'  // ✅ Matches "api_key", "API_KEY", "Api-Key"
'/api[_-]?key/'   // ❌ Only matches "api_key" or "api-key"
```

### 2. Capture Sensitive Values

Use capture groups `()` to identify what to redact:

```php
'/password=([^\s;]+)/i'  // ✅ Captures the password value
'/password=/i'           // ❌ Doesn't capture what to redact
```

### 3. Match Context, Not Just Values

Include context to avoid false positives:

```php
'/api[_-]?key["\']?\s*[:=]\s*["\']?([a-zA-Z0-9_\-]+)/i'  // ✅ Requires "api_key=" prefix
'/[a-zA-Z0-9_\-]+/i'                                     // ❌ Matches everything
```

### 4. Test Your Patterns

Test patterns against real exception messages:

```php
use Cline\Cloak\Sanitizers\PatternBasedSanitizer;

$sanitizer = new PatternBasedSanitizer(
    patterns: ['/your-pattern/i'],
    replacement: '[REDACTED]',
);

$message = 'Error with secret_token=abc123';
$sanitized = $sanitizer->sanitizeMessage($message);

dump($sanitized); // "Error with [REDACTED]"
```

## Environment-Specific Patterns

Use different patterns per environment:

```php
'patterns' => env('APP_ENV') === 'production' ? [
    // Aggressive sanitization in production
    '/mysql:\/\//i',
    '/password/i',
    '/secret/i',
    '/token/i',
] : [
    // Minimal sanitization in development
    '/password=([^\s;]+)/i',
],
```

## Common Pattern Examples

### Credit Card Numbers

```php
'/\b(?:\d{4}[-\s]?){3}\d{4}\b/'
```

### Social Security Numbers

```php
'/\b\d{3}-\d{2}-\d{4}\b/'
```

### Email Addresses

```php
'/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/'
```

### IPv4 Addresses

```php
'/\b(?:\d{1,3}\.){3}\d{1,3}\b/'
```

### File Paths

```php
// Unix/Linux paths
'/\/home\/([^\/\s]+)/i',
'/\/Users\/([^\/\s]+)/i',

// Windows paths
'/C:\\\\Users\\\\([^\\\\]+)/i',
```

### JWT Tokens

```php
'/eyJ[a-zA-Z0-9_-]+\.eyJ[a-zA-Z0-9_-]+\.[a-zA-Z0-9_-]+/'
```

## Custom Replacement Text

Change the redaction text globally:

```php
'replacement' => '[SENSITIVE_DATA_REMOVED]',
```

Or use different text for different patterns by creating multiple sanitizers:

```php
use Cline\Cloak\Sanitizers\PatternBasedSanitizer;

$dbSanitizer = new PatternBasedSanitizer(
    patterns: ['/mysql:\/\//i'],
    replacement: '[DATABASE_CREDENTIALS]',
);

$apiSanitizer = new PatternBasedSanitizer(
    patterns: ['/api[_-]?key/i'],
    replacement: '[API_KEY]',
);
```

## Performance Considerations

### Pattern Complexity

Keep patterns efficient:

```php
// ✅ Efficient - specific and bounded
'/api_key=([a-zA-Z0-9]{20,40})/i'

// ❌ Inefficient - too greedy
'/api_key=(.+)/i'
```

### Pattern Count

Too many patterns can impact performance. Consider:

```php
// ✅ Single comprehensive pattern
'/(?:password|secret|token|key)=([^\s;]+)/i'

// ❌ Multiple similar patterns
'/password=([^\s;]+)/i',
'/secret=([^\s;]+)/i',
'/token=([^\s;]+)/i',
'/key=([^\s;]+)/i',
```

## Debugging Patterns

Enable pattern debugging:

```php
use Cline\Cloak\Sanitizers\PatternBasedSanitizer;

$sanitizer = new PatternBasedSanitizer(
    patterns: config('cloak.patterns'),
    replacement: '[REDACTED]',
);

$message = 'Error with mysql://root:pass@localhost/db and api_key=secret123';

// Test each pattern
foreach (config('cloak.patterns') as $pattern) {
    if (preg_match($pattern, $message)) {
        dump("Pattern matched: {$pattern}");
    }
}

// See final result
dump($sanitizer->sanitizeMessage($message));
```

## Next Steps

- Learn about [exception handling strategies](exception-handling.md)
- Explore [generic messages](generic-messages.md) for complete redaction
- Review [security best practices](security-best-practices.md)

Follow these security best practices to get the most out of Cloak and prevent information leakage.

## Production Configuration

### Always Enable in Production

Never disable Cloak in production:

```php
// ❌ Dangerous
'enabled' => false,

// ✅ Safe
'enabled' => env('CLOAK_ENABLED', true),
```

### Sanitize in Debug Mode

In production, sanitize even if debug mode is accidentally enabled:

```php
// ✅ Recommended for production
'sanitize_in_debug' => env('APP_ENV') === 'production',
```

### Disable Debug Mode

Always set this in production `.env`:

```bash
APP_DEBUG=false
CLOAK_ENABLED=true
CLOAK_SANITIZE_IN_DEBUG=true
```

## Pattern Configuration

### Start Broad, Then Refine

Begin with aggressive patterns, then whitelist safe exceptions:

```php
// Step 1: Aggressive initial patterns
'patterns' => [
    '/password/i',
    '/secret/i',
    '/token/i',
    '/key/i',
    '/credential/i',
],

// Step 2: Allow safe exceptions
'allowed_exceptions' => [
    \App\Exceptions\UserFriendlyException::class,
],
```

### Cover All Credential Types

Include patterns for all services you use:

```php
'patterns' => [
    // Databases
    '/mysql:\/\//i',
    '/postgres:\/\//i',
    '/mongodb:\/\//i',

    // Caching/Queues
    '/redis:\/\//i',
    '/memcached:\/\//i',

    // Cloud providers
    '/aws[_-]?access/i',
    '/gcp[_-]?key/i',
    '/azure[_-]?key/i',

    // Payment processors
    '/stripe[_-]?key/i',
    '/paypal[_-]?secret/i',

    // Your specific services
    '/your-service[_-]?token/i',
],
```

### Use Generic Messages for Critical Exceptions

For exceptions that always contain sensitive data:

```php
'sanitize_exceptions' => [
    QueryException::class,
    PDOException::class,
    AwsException::class,
],

'generic_messages' => [
    QueryException::class => 'A database error occurred.',
    PDOException::class => 'Database connection failed.',
    AwsException::class => 'Cloud service error.',
],
```

## Logging Best Practices

### Always Log Originals

Keep detailed logs for debugging:

```php
'log_original' => true,
```

### Protect Log Files

Ensure logs are not publicly accessible:

```bash
# .gitignore
storage/logs/*.log

# File permissions (Linux)
chmod 600 storage/logs/*.log
```

### Rotate Logs Regularly

Use Laravel's log rotation:

```php
// config/logging.php
'daily' => [
    'driver' => 'daily',
    'path' => storage_path('logs/laravel.log'),
    'level' => env('LOG_LEVEL', 'debug'),
    'days' => 14, // Keep logs for 14 days
],
```

### Sanitize Logs Too

For extra security, sanitize logs:

```php
use Monolog\Processor\ProcessorInterface;
use Cline\Cloak\Facades\Cloak;

class SanitizingLogProcessor implements ProcessorInterface
{
    public function __invoke(array $record): array
    {
        if (isset($record['message'])) {
            $record['message'] = Cloak::getSanitizer()
                ->sanitizeMessage($record['message']);
        }

        return $record;
    }
}
```

Register the processor:

```php
// config/logging.php
'channels' => [
    'stack' => [
        'driver' => 'stack',
        'channels' => ['single'],
        'processors' => [SanitizingLogProcessor::class],
    ],
],
```

## Response Security

### Never Return Stack Traces

In production, never show stack traces:

```php
// bootstrap/app.php
->withExceptions(function (Exceptions $exceptions) {
    $exceptions->render(function (Throwable $e, Request $request) {
        $sanitized = Cloak::sanitizeForRendering($e, $request);

        // Production: Generic response
        if (app()->environment('production')) {
            return response()->json([
                'error' => 'An error occurred.',
            ], 500);
        }

        // Development: Sanitized details
        return response()->json([
            'error' => $sanitized->getMessage(),
        ], 500);
    });
})
```

### Use HTTP Status Codes Carefully

Don't leak information through status codes:

```php
// ❌ Reveals user existence
return response()->json(['error' => 'User not found'], 404);

// ✅ Generic authentication error
return response()->json(['error' => 'Authentication failed'], 401);
```

## Environment-Specific Security

### Development Environment

Allow detailed errors, but still sanitize credentials:

```php
// .env.local
APP_DEBUG=true
CLOAK_ENABLED=true
CLOAK_SANITIZE_IN_DEBUG=false

// config/cloak.php
'patterns' => env('APP_ENV') === 'local' ? [
    // Only critical credentials
    '/password=([^\s;]+)/i',
] : [
    // All sensitive patterns
    ...
],
```

### Staging Environment

Mirror production settings:

```php
// .env.staging
APP_DEBUG=false
CLOAK_ENABLED=true
CLOAK_SANITIZE_IN_DEBUG=true
```

### Testing Environment

Enable but configure minimally:

```php
// .env.testing
APP_DEBUG=true
CLOAK_ENABLED=true
CLOAK_SANITIZE_IN_DEBUG=false
```

## API Security

### Sanitize API Responses

API responses need extra care:

```php
->withExceptions(function (Exceptions $exceptions) {
    $exceptions->render(function (Throwable $e, Request $request) {
        if ($request->is('api/*')) {
            // Always sanitize API routes
            config(['cloak.sanitize_in_debug' => true]);
            $e = Cloak::sanitizeForRendering($e, $request);

            return response()->json([
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ], 500);
        }

        return Cloak::sanitizeForRendering($e, $request);
    });
})
```

### Rate Limit Error Responses

Prevent information gathering:

```php
Route::middleware(['throttle:60,1'])->group(function () {
    // API routes
});
```

## Monitoring & Alerts

### Monitor for Sensitive Leaks

Set up alerts for potential leaks:

```php
use Illuminate\Support\Facades\Log;

class LeakDetectionMiddleware
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        // Check response for potential leaks
        $content = $response->getContent();

        $sensitivePatterns = [
            'mysql://',
            'password=',
            'api_key=',
            'aws_access_key',
        ];

        foreach ($sensitivePatterns as $pattern) {
            if (str_contains($content, $pattern)) {
                Log::critical('Potential sensitive data leak detected', [
                    'url' => $request->url(),
                    'pattern' => $pattern,
                ]);
            }
        }

        return $response;
    }
}
```

### Track Sanitization Metrics

Monitor sanitization frequency:

```php
use Illuminate\Support\Facades\Cache;

class MetricsMiddleware
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        if ($response->status() >= 500) {
            Cache::increment('exceptions.sanitized.count');
            Cache::increment('exceptions.sanitized.date.' . now()->toDateString());
        }

        return $response;
    }
}
```

## Testing Security

### Test Sanitization

Ensure patterns work:

```php
test('sanitizes database credentials', function () {
    $message = 'Error: mysql://root:MyP@ssw0rd@db.prod.com/app';

    $sanitized = Cloak::getSanitizer()->sanitizeMessage($message);

    expect($sanitized)
        ->not->toContain('MyP@ssw0rd')
        ->not->toContain('db.prod.com');
});
```

### Test Generic Messages

Verify exception types are handled:

```php
test('uses generic message for database exceptions', function () {
    config([
        'cloak.sanitize_exceptions' => [QueryException::class],
        'cloak.generic_messages' => [
            QueryException::class => 'Database error occurred.',
        ],
    ]);

    $pdo = new PDOException('SQLSTATE: mysql://user:pass@host/db');
    $exception = new QueryException('default', 'SELECT *', [], $pdo);

    $sanitized = Cloak::sanitizeForRendering($exception);

    expect($sanitized->getMessage())->toBe('Database error occurred.');
});
```

### Penetration Testing

Test with realistic attacks:

```php
test('prevents sql injection information leak', function () {
    try {
        DB::select("SELECT * FROM users WHERE id = ?; DROP TABLE users--");
    } catch (QueryException $e) {
        $sanitized = Cloak::sanitizeForRendering($e);

        // Should not reveal table names or query structure
        expect($sanitized->getMessage())
            ->not->toContain('users')
            ->not->toContain('DROP TABLE');
    }
});
```

## Incident Response

### Document Exception Handling

Maintain documentation:

```markdown
# Exception Handling Policy

1. All exceptions sanitized via Cloak
2. Original exceptions logged to storage/logs
3. Generic messages for database/auth errors
4. Stack traces never shown in production
5. Weekly review of exception logs
```

### Audit Trail

Track who accesses exception logs:

```php
Route::get('/admin/logs', function () {
    Log::info('Exception logs accessed', [
        'user' => auth()->id(),
        'ip' => request()->ip(),
        'time' => now(),
    ]);

    return view('admin.logs');
})->middleware(['auth', 'admin']);
```

## Checklist

Before deploying to production:

- [ ] `APP_DEBUG=false` in production `.env`
- [ ] `CLOAK_ENABLED=true` in production
- [ ] All credential types covered in patterns
- [ ] Generic messages for critical exceptions
- [ ] Log files protected from public access
- [ ] Stack traces disabled in responses
- [ ] Sanitization tested with real exceptions
- [ ] Monitoring/alerts configured
- [ ] Documentation updated
- [ ] Team trained on exception handling

## Next Steps

- Learn about [advanced configuration](advanced-configuration.md)
- Explore [logging strategies](logging.md)
- Review [testing guide](testing.md)
