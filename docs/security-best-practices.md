---
title: Security Best Practices
description: Best practices for using Cloak to maximize security in your Laravel applications.
---

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
