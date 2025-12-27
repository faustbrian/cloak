---
title: Real-World Examples
description: Practical examples of using Cloak to prevent information leakage in production applications.
---

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
