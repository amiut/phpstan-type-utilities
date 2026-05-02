# PHPStan Type Utilities

> [!WARNING]
> This project is experimental and not stable. APIs may change without notice.

<p>
    <a href="https://github.com/amiut/phpstan-type-utilities/releases">
        <img alt="package version" src="https://img.shields.io/packagist/v/amiut/phpstan-type-utilities.svg?label=version" />
    </a>
    <img alt="php version" src="https://img.shields.io/packagist/php-v/amiut/phpstan-type-utilities.svg?color=brown" />
    <img alt="Packagist" src="https://img.shields.io/github/license/amiut/phpstan-type-utilities">
</p>

PHPStan Type Utilities is a set of opt-in PHPStan/PHPDoc type utilities for PHP projects.

The package currently includes utilities for detecting callable return types and inferring static PHP array shapes. The goal is to bring a small, explicit, TypeScript-utility-types style workflow to PHPStan without turning the extension into a general type solver.

The implementation is intentionally conservative. It infers PHP array literals as PHP array shapes only; it does not interpret arrays semantically or treat domain-specific array structures differently.

## Installation

Install the package as a development dependency:

```bash
composer require --dev amiut/phpstan-type-utilities
```

The extension is registered automatically through Composer's PHPStan extension discovery. If your project does not use extension discovery, include it manually:

```neon
includes:
    - %currentWorkingDirectory%/vendor/amiut/phpstan-type-utilities/extension.neon
```

Use `%currentWorkingDirectory%` for manual includes. A plain relative include can break when another tool wraps your PHPStan config from a generated temporary config file, which can make PHPStan behave as if the extension is not installed.

## Features

### `@phpstan-infer-return`

Infer the exact static array shape of a function or method from its return expression.

```php
/** @phpstan-return array @phpstan-infer-return */
public function options(): array
{
    return [
        'enabled' => true,
        'limit' => 100,
        'label' => 'default',
    ];
}
```

PHPStan receives the inferred return type:

```php
array{enabled: bool, limit: int, label: string}
```

This suppresses PHPStan's generic missing iterable value type error when the shape can be inferred. If inference fails, the extension reports a focused `arrayTypeInference.missingType` diagnostic.

[Read the `@phpstan-infer-return` docs](docs/infer-return.md)

### `ReturnType<callable>`

Detect and reuse the return type of a function or method as a PHPDoc type. `ReturnType<callable>` works with any callable return type PHPStan can resolve — from a native declaration, PHPDoc, another PHPStan extension, or `@phpstan-infer-return`.

The main benefit is avoiding duplicated array shape annotations. Instead of copying an `array{...}` PHPDoc in every place that consumes it, point `ReturnType` at the single method that defines it:

```php
final class PluginConfig
{
    /** @return array{enabled: bool, limit: int, label: string} */
    public function defaults(): array
    {
        return ['enabled' => true, 'limit' => 100, 'label' => 'default'];
    }
}

/**
 * @phpstan-type PluginDefaults \ReturnType<PluginConfig, 'defaults'>
 */
final class PluginService
{
    /**
     * @param array $config
     * @phpstan-param PluginDefaults $config
     */
    public function boot(array $config): void
    {
        // PHPStan knows $config['enabled'] is bool, $config['limit'] is int, etc.
    }
}
```

`PluginDefaults` resolves to:

```php
array{enabled: bool, limit: int, label: string}
```

When the `defaults()` signature changes, all consumers pick up the updated type automatically — without touching their own annotations.

#### Combined with `@phpstan-infer-return`

When a method uses `@phpstan-infer-return`, its return type becomes a precise static array shape, and `ReturnType<callable>` can reference it like any other documented type. This is the most common pattern: define the shape once via inference, reuse it everywhere:

```php
/**
 * @phpstan-type Options \ReturnType<self, 'defaults'>
 */
final class Config
{
    /** @phpstan-return array @phpstan-infer-return */
    public function defaults(): array
    {
        return [
            'enabled' => true,
            'limit'   => 100,
        ];
    }

    /**
     * @param array $options
     * @phpstan-param Options $options
     */
    public function apply(array $options): void
    {
        // PHPStan catches type errors on $options['enabled'] and $options['limit']
    }
}
```

`Options` resolves to the same inferred type as `defaults()`:

```php
array{enabled: bool, limit: int}
```

#### Referencing a function

```php
/**
 * @phpstan-return array @phpstan-infer-return
 */
function defaultHeaders(): array
{
    return [
        'Content-Type' => 'application/json',
        'Accept'       => 'application/json',
    ];
}

/**
 * @phpstan-type Headers \ReturnType<App\Http\defaultHeaders>
 */
final class HttpClient
{
    /**
     * @param array $headers
     * @phpstan-param Headers $headers
     */
    public function withHeaders(array $headers): void {}
}
```

#### Supported syntax

- `\ReturnType<functionName>`
- `\ReturnType<Fully\Qualified\functionName>`
- `\ReturnType<self, 'methodName'>`
- `\ReturnType<Fully\Qualified\ClassName, 'methodName'>`

Nested inferred calls are supported when the target is statically resolvable.

`ReturnType` always reuses the callable's literal PHP return type. If a method returns a JSON-schema definition array, `\ReturnType<self, 'schema'>` resolves to the PHP shape of that schema-definition array. It does not convert the JSON schema into a separate data shape.

PHPStan-powered IDE hovers should show the same resolved types PHPStan sees on the command line when the extension is active. The optional IDE stubs only reduce editor noise in tools like Intelephense or PHPStorm; those tools do not execute PHPStan's custom type resolver by themselves.

[Read the `ReturnType<callable>` docs](docs/return-type.md)

## License

MIT
