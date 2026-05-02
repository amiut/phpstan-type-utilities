# PHPStan Type Utilities

PHPStan helpers for type-level utilities inspired by TypeScript utility types. The package currently focuses on static PHP arrays: infer shapes automatically from return expressions and reuse those shapes across your codebase without hand-writing `array{...}` annotations.

Compatible with PHP 7.4+ and PHPStan 2.x.

## The Problem

Running PHPStan at level 6 or above requires explicit value types on all iterables. For functions and methods that return a fully static array the shape is unambiguous, yet PHPStan still raises:

```text
Return type has no value type specified in iterable type array.
Identifier: missingType.iterableValue
```

For small arrays the fix is straightforward — add a `@return array{enabled: bool, limit: int}` annotation. For larger or deeply nested arrays this becomes impractical. The annotation can easily exceed the array it describes, and any structural change to the return value requires a manual update to keep the two in sync.

The common workaround is to write a deliberately vague annotation such as `@return array<mixed>` or `@return array<string, mixed>`. This silences the error, but throws away all type information that PHPStan could otherwise use — defeating the purpose of running strict analysis in the first place.

## Project Goals

This package is meant to be a home for small, explicit PHPDoc/PHPStan type utilities. The current utilities are intentionally conservative: they read PHP code and PHPDoc, infer only what can be proven statically, and return clear diagnostics when they cannot.

It does not interpret array contents semantically. Arrays are treated as PHP array literals only; any future domain-specific utility must be explicit and separate from return-shape inference.

## Features

### `@phpstan-infer-return` — Automatic shape inference

Add `@phpstan-infer-return` alongside `@return array` to let PHPStan infer the exact shape from the return expression. No `array{...}` annotation needed.

```php
/** @return array @phpstan-infer-return */
public function options(): array
{
    return [
        'enabled' => true,
        'limit'   => 100,
        'label'   => 'default',
    ];
}
```

### `ReturnType<>` — Reuse inferred shapes

Reference the inferred shape of any annotated method or function as a PHPDoc type, without repeating the annotation.

```php
/**
 * @phpstan-type Options \ReturnType<self, 'options'>
 */
class Config
{
    /** @return array @phpstan-infer-return */
    public function options(): array
    {
        return ['enabled' => true, 'limit' => 100];
    }

    /** @phpstan-param Options $opts */
    public function apply(array $opts): void {}
}
```

## Installation

```bash
composer require --dev amiut/phpstan-type-utilities
```

Then include the extension in your PHPStan configuration:

```neon
# phpstan.neon
includes:
    - vendor/amiut/phpstan-type-utilities/extension.neon
```

## License

MIT
