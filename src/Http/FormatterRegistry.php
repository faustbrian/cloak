<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cloak\Http;

use Cline\Cloak\Contracts\ResponseFormatter;
use Cline\Cloak\Exceptions\FormatterNotFoundException;
use Cline\Cloak\Http\Formatters\HalFormatter;
use Cline\Cloak\Http\Formatters\HydraFormatter;
use Cline\Cloak\Http\Formatters\JsonApiFormatter;
use Cline\Cloak\Http\Formatters\ProblemJsonFormatter;
use Cline\Cloak\Http\Formatters\SimpleFormatter;

use function array_keys;
use function config;
use function throw_unless;

/**
 * Response formatter registry for managing exception formatters.
 *
 * Manages built-in and custom response formatters, providing a central registry
 * for resolving formatters by name. Automatically registers built-in formatters
 * (simple, json-api, problem-json, hal, hydra) and custom formatters from
 * configuration during instantiation.
 *
 * ```php
 * $registry = new FormatterRegistry();
 *
 * // Get a specific formatter
 * $formatter = $registry->get('json-api');
 *
 * // Register custom formatter
 * $registry->register('my-format', new MyCustomFormatter());
 *
 * // Get default formatter from config
 * $default = $registry->getDefault();
 * ```
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see ResponseFormatter
 * @see CloakManager
 */
final class FormatterRegistry
{
    /**
     * Registered response formatters indexed by name.
     *
     * @var array<string, ResponseFormatter>
     */
    private array $formatters = [];

    /**
     * Create a new formatter registry instance.
     *
     * Automatically registers all built-in formatters and any custom formatters
     * defined in the 'cloak.custom_formatters' configuration array.
     */
    public function __construct()
    {
        $this->registerBuiltInFormatters();
        $this->registerCustomFormatters();
    }

    /**
     * Get a formatter by its registered name.
     *
     * Retrieves a registered formatter instance for the given name. Built-in
     * names include 'simple', 'json-api', 'problem-json', 'hal', and 'hydra'.
     * Custom formatters can be registered via configuration or the register() method.
     *
     * @param string $name Formatter name (e.g., 'json-api', 'hal', 'simple')
     *
     * @throws FormatterNotFoundException If no formatter is registered with the given name
     * @return ResponseFormatter          The formatter instance for the given name
     */
    public function get(string $name): ResponseFormatter
    {
        throw_unless(isset($this->formatters[$name]), FormatterNotFoundException::forName($name));

        return $this->formatters[$name];
    }

    /**
     * Get the default formatter from configuration.
     *
     * Returns the formatter specified in 'cloak.error_response_format' config,
     * falling back to 'simple' if not configured. This is the formatter used
     * when no explicit format is requested.
     *
     * @throws FormatterNotFoundException If the configured default formatter name is not registered
     * @return ResponseFormatter          Default formatter instance
     */
    public function getDefault(): ResponseFormatter
    {
        /** @var string $format */
        $format = config('cloak.error_response_format', 'simple');

        return $this->get($format);
    }

    /**
     * Register a custom formatter.
     *
     * Adds a formatter to the registry under the specified name. This allows
     * runtime registration of custom formatters beyond those defined in
     * configuration. Existing formatters with the same name will be replaced.
     *
     * @param string            $name      Formatter name for later retrieval via get()
     * @param ResponseFormatter $formatter Formatter instance implementing ResponseFormatter
     */
    public function register(string $name, ResponseFormatter $formatter): void
    {
        $this->formatters[$name] = $formatter;
    }

    /**
     * Check if a formatter is registered.
     *
     * Determines whether a formatter with the given name exists in the registry.
     * Useful for conditional formatter usage or validation before retrieval.
     *
     * @param string $name Formatter name to check
     *
     * @return bool True if formatter is registered, false otherwise
     */
    public function has(string $name): bool
    {
        return isset($this->formatters[$name]);
    }

    /**
     * Get all registered formatter names.
     *
     * Returns an array of all formatter names currently registered in the
     * registry, including both built-in and custom formatters.
     *
     * @return array<int, string> Array of formatter names
     */
    public function getRegisteredNames(): array
    {
        return array_keys($this->formatters);
    }

    /**
     * Register built-in response formatters.
     *
     * Initializes the registry with Cloak's built-in formatters: simple (basic
     * JSON), json-api (JSON:API spec), problem-json (RFC 7807), hal (HAL spec),
     * and hydra (Hydra/JSON-LD). Called automatically during construction.
     */
    private function registerBuiltInFormatters(): void
    {
        $this->formatters['simple'] = new SimpleFormatter();
        $this->formatters['json-api'] = new JsonApiFormatter();
        $this->formatters['problem-json'] = new ProblemJsonFormatter();
        $this->formatters['hal'] = new HalFormatter();
        $this->formatters['hydra'] = new HydraFormatter();
    }

    /**
     * Register custom formatters from Laravel configuration.
     *
     * Loads custom formatter classes from 'cloak.custom_formatters' config array
     * and instantiates them. Configuration format: ['name' => FormatterClass::class].
     * Called automatically during construction.
     */
    private function registerCustomFormatters(): void
    {
        /** @var array<string, class-string<ResponseFormatter>> $customFormatters */
        $customFormatters = config('cloak.custom_formatters', []);

        foreach ($customFormatters as $name => $formatterClass) {
            $this->formatters[$name] = new $formatterClass();
        }
    }
}
