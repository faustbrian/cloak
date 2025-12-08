<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Exception Sanitization
    |--------------------------------------------------------------------------
    |
    | Controls whether Cloak should automatically sanitize exceptions to
    | prevent sensitive information leakage. When enabled, Cloak will
    | intercept and sanitize exceptions before they're rendered.
    |
    */

    'enabled' => env('CLOAK_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Sensitive Pattern Redaction
    |--------------------------------------------------------------------------
    |
    | Patterns to redact from exception messages and stack traces. These
    | regex patterns match sensitive information that should never be
    | exposed to end users or logs.
    |
    */

    'patterns' => [
        // Database connection strings
        '/mysql:\/\/([^:]+):([^@]+)@([^\/]+)\/(.+)/i',
        '/postgres:\/\/([^:]+):([^@]+)@([^\/]+)\/(.+)/i',
        '/mongodb:\/\/([^:]+):([^@]+)@([^\/]+)\/(.+)/i',
        '/redis:\/\/([^:]+):([^@]+)@([^\/]+)/i',

        // Database credentials in DSN format
        '/host=([^\s;]+)/i',
        '/user=([^\s;]+)/i',
        '/password=([^\s;]+)/i',
        '/dbname=([^\s;]+)/i',

        // API keys and tokens
        '/api[_-]?key["\']?\s*[:=]\s*["\']?([a-zA-Z0-9_\-]+)/i',
        '/token["\']?\s*[:=]\s*["\']?([a-zA-Z0-9_\-\.]+)/i',
        '/bearer\s+([a-zA-Z0-9_\-\.]+)/i',

        // AWS credentials
        '/aws[_-]?access[_-]?key[_-]?id["\']?\s*[:=]\s*["\']?([A-Z0-9]+)/i',
        '/aws[_-]?secret[_-]?access[_-]?key["\']?\s*[:=]\s*["\']?([A-Za-z0-9\/\+]+)/i',

        // Private keys
        '/-----BEGIN\s+(RSA\s+)?PRIVATE\s+KEY-----[\s\S]+?-----END\s+(RSA\s+)?PRIVATE\s+KEY-----/i',
        '/-----BEGIN\s+CERTIFICATE-----[\s\S]+?-----END\s+CERTIFICATE-----/i',

        // Email addresses in certain contexts
        '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/i',

        // IP addresses (when flagged as sensitive)
        '/\b(?:\d{1,3}\.){3}\d{1,3}\b/',

        // File paths that might contain usernames
        '/\/home\/([^\/\s]+)/i',
        '/\/Users\/([^\/\s]+)/i',
        '/C:\\\\Users\\\\([^\\\\]+)/i',
    ],

    /*
    |--------------------------------------------------------------------------
    | Replacement Text
    |--------------------------------------------------------------------------
    |
    | Text used to replace sensitive patterns. Keep it generic to avoid
    | revealing the type of sensitive data that was redacted.
    |
    */

    'replacement' => '[REDACTED]',

    /*
    |--------------------------------------------------------------------------
    | Exception Types to Sanitize
    |--------------------------------------------------------------------------
    |
    | Specific exception types that should always be sanitized. These
    | exceptions are known to potentially leak sensitive information.
    |
    */

    'sanitize_exceptions' => [
        'Illuminate\Database\QueryException',
        'PDOException',
        'Doctrine\DBAL\Exception',
        'League\Flysystem\FilesystemException',
        'Aws\Exception\AwsException',
        'Illuminate\Http\Client\RequestException',
        'Illuminate\Http\Client\ConnectionException',
        'GuzzleHttp\Exception\RequestException',
        'GuzzleHttp\Exception\ConnectException',
        'Symfony\Component\HttpClient\Exception\ClientException',
        'Symfony\Component\Mailer\Exception\TransportException',
        'Swift_TransportException',
        'Predis\Connection\ConnectionException',
        'Redis',
        'RedisException',
    ],

    /*
    |--------------------------------------------------------------------------
    | Generic Error Messages
    |--------------------------------------------------------------------------
    |
    | Generic messages to display for specific exception types. This prevents
    | leaking any implementation details while still providing useful
    | feedback to developers.
    |
    */

    'generic_messages' => [
        'Illuminate\Database\QueryException' => 'A database error occurred while processing your request.',
        'PDOException' => 'A database connection error occurred.',
        'Doctrine\DBAL\Exception' => 'A database error occurred.',
        'League\Flysystem\FilesystemException' => 'A file system error occurred.',
        'Aws\Exception\AwsException' => 'An external service error occurred.',
        'Illuminate\Http\Client\RequestException' => 'An external API request failed.',
        'Illuminate\Http\Client\ConnectionException' => 'Failed to connect to external service.',
        'GuzzleHttp\Exception\RequestException' => 'An external API request failed.',
        'GuzzleHttp\Exception\ConnectException' => 'Failed to connect to external service.',
        'Symfony\Component\HttpClient\Exception\ClientException' => 'An HTTP client error occurred.',
        'Symfony\Component\Mailer\Exception\TransportException' => 'An email delivery error occurred.',
        'Swift_TransportException' => 'An email delivery error occurred.',
        'Predis\Connection\ConnectionException' => 'A cache connection error occurred.',
        'Redis' => 'A cache error occurred.',
        'RedisException' => 'A cache error occurred.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Debug Mode Override
    |--------------------------------------------------------------------------
    |
    | When debug mode is enabled (APP_DEBUG=true), should Cloak still
    | sanitize exceptions? Set to false to see full exception details
    | during development.
    |
    */

    'sanitize_in_debug' => false,

    /*
    |--------------------------------------------------------------------------
    | Stack Trace Sanitization
    |--------------------------------------------------------------------------
    |
    | Whether to sanitize file paths and arguments in stack traces.
    | This prevents leaking server directory structure and function
    | arguments that might contain sensitive data.
    |
    */

    'sanitize_stack_traces' => true,

    /*
    |--------------------------------------------------------------------------
    | Log Original Exceptions
    |--------------------------------------------------------------------------
    |
    | Whether to log the original, unsanitized exception before sanitizing
    | it for the response. This allows developers to debug issues while
    | still protecting end users.
    |
    */

    'log_original' => true,

    /*
    |--------------------------------------------------------------------------
    | Error ID Generation
    |--------------------------------------------------------------------------
    |
    | Generate unique error IDs to include in sanitized messages. This allows
    | customers to provide the ID when reporting issues, making it easier
    | to correlate user reports with server logs.
    |
    | Options: 'ulid', 'uuid', null (disabled)
    |
    */

    'error_id_type' => env('CLOAK_ERROR_ID_TYPE', 'uuid'),

    /*
    |--------------------------------------------------------------------------
    | Error ID Template
    |--------------------------------------------------------------------------
    |
    | Template for including error ID in sanitized messages. Use {id} as
    | placeholder for the generated ID, {message} for the sanitized message.
    |
    */

    'error_id_template' => '{message} [Error ID: {id}]',

    /*
    |--------------------------------------------------------------------------
    | Error ID Context Key
    |--------------------------------------------------------------------------
    |
    | The key used to store the error ID in Laravel's Context system. This
    | makes the ID available to logging, monitoring tools like Nightwatch,
    | and other context-aware services.
    |
    */

    'error_id_context_key' => env('CLOAK_ERROR_ID_CONTEXT_KEY', 'exception_id'),

    /*
    |--------------------------------------------------------------------------
    | Custom Context Data
    |--------------------------------------------------------------------------
    |
    | Callbacks to add custom context data to exceptions. These are executed
    | when an exception is sanitized and the results are stored in Laravel's
    | Context system for logging and monitoring.
    |
    | Example:
    | 'context' => [
    |     'user_id' => fn() => auth()->id(),
    |     'tenant_id' => fn() => tenant()?->id,
    |     'request_id' => fn() => request()->header('X-Request-ID'),
    | ],
    |
    */

    'context' => [
        // 'user_id' => fn () => auth()->id(),
        // 'tenant_id' => fn () => tenant()?->id,
    ],

    /*
    |--------------------------------------------------------------------------
    | Exception Tags
    |--------------------------------------------------------------------------
    |
    | Automatically tag exceptions for better categorization in monitoring
    | tools. Maps exception classes to tags/categories.
    |
    | Example:
    | 'tags' => [
    |     \Illuminate\Database\QueryException::class => ['database', 'critical'],
    |     \Stripe\Exception\CardException::class => ['payment', 'third-party'],
    | ],
    |
    */

    'tags' => [
        // \Illuminate\Database\QueryException::class => ['database', 'critical'],
        // \PDOException::class => ['database', 'critical'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed Exception Types
    |--------------------------------------------------------------------------
    |
    | Exception types that should NOT be sanitized, even if they match
    | sensitive patterns. Useful for custom application exceptions that
    | are safe to display.
    |
    */

    'allowed_exceptions' => [
        // \App\Exceptions\SafeException::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Error Response Format
    |--------------------------------------------------------------------------
    |
    | The format to use for HTTP error responses. Cloak supports multiple
    | API standards out of the box. Choose the format that matches your
    | API architecture or register a custom formatter.
    |
    | Supported formats:
    | - 'simple'        Simple JSON: {"error": "message", "error_id": "uuid"}
    | - 'json-api'      JSON:API spec: https://jsonapi.org/format/#errors
    | - 'problem-json'  RFC 7807 Problem Details: https://tools.ietf.org/html/rfc7807
    | - 'hal'           HAL: https://datatracker.ietf.org/doc/html/draft-kelly-json-hal
    | - 'hydra'         JSON-LD + Hydra: https://www.hydra-cg.com/spec/latest/core/
    |
    */

    'error_response_format' => env('CLOAK_ERROR_RESPONSE_FORMAT', 'simple'),

    /*
    |--------------------------------------------------------------------------
    | Custom Response Formatters
    |--------------------------------------------------------------------------
    |
    | Register custom response formatters. Keys are formatter names,
    | values are fully-qualified class names implementing ResponseFormatter.
    |
    | Example:
    | 'custom_formatters' => [
    |     'my-format' => \App\Http\Formatters\MyCustomFormatter::class,
    | ],
    |
    */

    'custom_formatters' => [
        // 'my-format' => \App\Http\Formatters\MyCustomFormatter::class,
    ],
];
