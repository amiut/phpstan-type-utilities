# PHPStan Type Utilities

<p>
<a href="https://github.com/amiut/phpstan-type-utilities/releases">
<img alt="package version" src="https://img.shields.io/packagist/v/amiut/phpstan-type-utilities.svg?label=version" />
</a>
    <img alt="php version" src="https://img.shields.io/packagist/php-v/amiut/phpstan-type-utilities.svg?color=brown" />
    <img alt="Packagist" src="https://img.shields.io/packagist/l/amiut/phpstan-type-utilities.svg">
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
    - vendor/amiut/phpstan-type-utilities/extension.neon
```

## Features

### `@phpstan-infer-return`

Infer the exact static array shape of a function or method from its return expression.

```php
/** @return array @phpstan-infer-return */
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

Detect and reuse the return type of a function or method as a PHPDoc type. `ReturnType<callable>` is a general callable utility; it works with any callable return type PHPStan can resolve. That type may come from a native declaration, PHPDoc, another PHPStan extension, or `@phpstan-infer-return`.

Regular return types work without `@phpstan-infer-return`:

```php
final class Counter
{
    public function count(): int
    {
        return 5;
    }

    /** @phpstan-param \ReturnType<self, 'count'> $count */
    public function setCount(int $count): void
    {
    }
}
```

Here `\ReturnType<self, 'count'>` resolves to:

```php
int
```

PHPDoc return types work too:

```php
final class Config
{
    /** @return array{enabled: bool, limit: int} */
    public function options(): array
    {
        return [
            'enabled' => true,
            'limit' => 100,
        ];
    }

    /** @phpstan-param \ReturnType<self, 'options'> $options */
    public function apply(array $options): void
    {
    }
}
```

It can also reuse an inferred array shape because `@phpstan-infer-return` makes the callable's return type more precise:

```php
/**
 * @phpstan-type Options \ReturnType<self, 'options'>
 */
final class Config
{
    /** @return array @phpstan-infer-return */
    public function options(): array
    {
        return [
            'enabled' => true,
            'limit' => 100,
        ];
    }

    /**
     * @param array $options
     * @phpstan-param Options $options
     */
    public function apply(array $options): void
    {
    }
}
```

Here `Options` resolves to the same inferred type as `options()`:

```php
array{enabled: bool, limit: int}
```

For functions, pass the function name:

```php
/**
 * @return array @phpstan-infer-return
 */
function defaultOptions(): array
{
    return [
        'enabled' => true,
        'limit' => 100,
    ];
}

/** @phpstan-type Options \ReturnType<defaultOptions> */
```

`ReturnType<callable>` supports:

- `\ReturnType<functionName>`
- `\ReturnType<Fully\Qualified\functionName>`
- `\ReturnType<self, 'methodName'>`
- `\ReturnType<Fully\Qualified\ClassName, 'methodName'>`

Nested inferred calls are supported when the target is statically resolvable.

[Read the `ReturnType<callable>` docs](docs/return-type.md)

## License

MIT
