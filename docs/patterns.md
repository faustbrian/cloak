---
title: Custom Patterns
description: Configure regex patterns to match and redact sensitive information in exceptions.
---

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
