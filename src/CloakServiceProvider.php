<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Cloak;

use Cline\Cloak\Contracts\ExceptionSanitizer;
use Cline\Cloak\Sanitizers\PatternBasedSanitizer;
use Override;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

/**
 * Laravel service provider for Cloak exception sanitization package.
 *
 * Handles registration and bootstrapping of Cloak's components including the
 * CloakManager instance, exception sanitizer, and integration with Laravel's
 * exception handling system. Extends Spatie's PackageServiceProvider for
 * streamlined package setup with automatic configuration publishing.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see CloakManager
 * @see ExceptionSanitizer
 * @see PatternBasedSanitizer
 */
final class CloakServiceProvider extends PackageServiceProvider
{
    /**
     * Configure the package definition and publishable assets.
     *
     * Defines the package name and registers the configuration file for
     * publishing to the application's config directory. This method is
     * called automatically by Spatie's package tools during registration.
     *
     * @param Package $package Package instance provided by Spatie's package tools
     *                         for fluent configuration of package settings
     */
    public function configurePackage(Package $package): void
    {
        $package
            ->name('cloak')
            ->hasConfigFile();
    }

    /**
     * Register Cloak services in the Laravel service container.
     *
     * Called during Laravel's service provider registration phase before any
     * boot methods execute. Binds the exception sanitizer and CloakManager
     * as singletons to ensure consistent instances throughout the application.
     */
    #[Override()]
    public function registeringPackage(): void
    {
        $this->app->singleton(ExceptionSanitizer::class, fn() => PatternBasedSanitizer::fromConfig());

        $this->app->singleton(CloakManager::class, fn() => CloakManager::fromConfig());
    }

    /**
     * Bootstrap Cloak package services after registration.
     *
     * Called after all service providers have been registered. Handles any
     * initialization that requires all services to be available. Currently
     * prepares exception handler integration hooks.
     */
    #[Override()]
    public function bootingPackage(): void
    {
        $this->registerExceptionHandler();
    }

    /**
     * Register Cloak's exception handler integration points.
     *
     * In Laravel 12+, exception handlers are registered in bootstrap/app.php
     * using the withExceptions() method. This package provides CloakManager
     * for manual integration rather than automatic registration. Users should
     * integrate in bootstrap/app.php or use the Cloak facade for one-off
     * sanitization. Future versions may add automatic integration if Laravel
     * provides package-level exception handler hooks.
     */
    private function registerExceptionHandler(): void
    {
        // No automatic registration in Laravel 12
        // Users should integrate in bootstrap/app.php:
        // ->withExceptions(function (Exceptions $exceptions) {
        //     $exceptions->render(fn (Throwable $e, Request $request) =>
        //         Cloak::sanitizeForRendering($e, $request)
        //     );
        // })
    }
}
