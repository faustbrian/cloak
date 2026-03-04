<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit;

use Cline\Cloak\Contracts\ResponseFormatter;
use Cline\Cloak\Http\FormatterRegistry;
use Cline\Cloak\Http\Formatters\SimpleFormatter;
use InvalidArgumentException;
use Tests\Support\CustomTestFormatter;

use function config;
use function describe;
use function expect;
use function test;

describe('FormatterRegistry', function (): void {
    test('registers built-in formatters', function (): void {
        $registry = new FormatterRegistry();

        expect($registry->has('simple'))->toBeTrue();
        expect($registry->has('json-api'))->toBeTrue();
        expect($registry->has('problem-json'))->toBeTrue();
        expect($registry->has('hal'))->toBeTrue();
        expect($registry->has('hydra'))->toBeTrue();
    });

    test('gets formatter by name', function (): void {
        $registry = new FormatterRegistry();

        $formatter = $registry->get('simple');

        expect($formatter)->toBeInstanceOf(SimpleFormatter::class);
    });

    test('throws exception for unknown formatter', function (): void {
        $registry = new FormatterRegistry();

        expect(fn (): ResponseFormatter => $registry->get('unknown'))
            ->toThrow(InvalidArgumentException::class, "Response formatter 'unknown' not found.");
    });

    test('gets default formatter from config', function (): void {
        config(['cloak.error_response_format' => 'json-api']);

        $registry = new FormatterRegistry();
        $formatter = $registry->getDefault();

        expect($formatter->getContentType())->toBe('application/vnd.api+json');
    });

    test('registers custom formatter', function (): void {
        $registry = new FormatterRegistry();
        $customFormatter = new SimpleFormatter();

        $registry->register('custom', $customFormatter);

        expect($registry->has('custom'))->toBeTrue();
        expect($registry->get('custom'))->toBe($customFormatter);
    });

    test('lists all registered formatter names', function (): void {
        $registry = new FormatterRegistry();

        $names = $registry->getRegisteredNames();

        expect($names)->toContain('simple');
        expect($names)->toContain('json-api');
        expect($names)->toContain('problem-json');
        expect($names)->toContain('hal');
        expect($names)->toContain('hydra');
    });

    test('registers custom formatters from config', function (): void {
        // Arrange
        config([
            'cloak.custom_formatters' => [
                'custom-test' => CustomTestFormatter::class,
                'custom-another' => CustomTestFormatter::class,
            ],
        ]);

        // Act
        $registry = new FormatterRegistry();

        // Assert
        expect($registry->has('custom-test'))->toBeTrue();
        expect($registry->has('custom-another'))->toBeTrue();
        expect($registry->get('custom-test'))->toBeInstanceOf(CustomTestFormatter::class);
        expect($registry->get('custom-another'))->toBeInstanceOf(CustomTestFormatter::class);
        expect($registry->get('custom-test')->getContentType())->toBe('application/vnd.custom+json');
    });

    test('custom formatters are included in registered names', function (): void {
        // Arrange
        config([
            'cloak.custom_formatters' => [
                'my-custom-format' => CustomTestFormatter::class,
            ],
        ]);

        // Act
        $registry = new FormatterRegistry();
        $names = $registry->getRegisteredNames();

        // Assert
        expect($names)->toContain('simple');
        expect($names)->toContain('json-api');
        expect($names)->toContain('my-custom-format');
    });

    test('custom formatters can be used as default formatter', function (): void {
        // Arrange
        config([
            'cloak.custom_formatters' => [
                'ultimate-format' => CustomTestFormatter::class,
            ],
            'cloak.error_response_format' => 'ultimate-format',
        ]);

        // Act
        $registry = new FormatterRegistry();
        $defaultFormatter = $registry->getDefault();

        // Assert
        expect($defaultFormatter)->toBeInstanceOf(CustomTestFormatter::class);
        expect($defaultFormatter->getContentType())->toBe('application/vnd.custom+json');
    });
});
