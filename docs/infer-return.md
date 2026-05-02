# `@phpstan-infer-return` — Automatic Shape Inference

The `@phpstan-infer-return` marker tells PHPStan to infer the exact array shape from the function or method's return expression, so you do not have to write a verbose `array{...}` annotation by hand.

For small arrays a manual `@return array{key: type, ...}` annotation is manageable. For larger or deeply nested static arrays the annotation can easily exceed the array itself in size, must be kept in sync with every structural change, and is easy to get wrong. The typical workaround — writing `@return array<mixed>` or `@return array<string, mixed>` — silences PHPStan but discards all shape information. `@phpstan-infer-return` lets PHPStan derive the shape directly from the return expression, which is the single source of truth.

## Usage

Add `@phpstan-infer-return` on the same line as the `@phpstan-return array` tag:

```php
/** @phpstan-return array @phpstan-infer-return */
public function options(): array
{
    return [
        'enabled' => true,
        'limit'   => 100,
        'label'   => 'default',
    ];
}
```

PHPStan will infer `array{enabled: bool, limit: int, label: string}` and suppress the `missingType.iterableValue` error that is raised at level 6 and above.

The `@phpstan-return` prefix is intentional. PhpStorm treats only `@return` as an override for its own type inference; `@phpstan-return` is transparent to PhpStorm, so it falls back to inferring the shape directly from the return literal. This means PhpStorm's hover tooltip also shows the precise shape without a separate PHPStan language server.

The plain `@return array @phpstan-infer-return` form is also accepted and behaves identically for PHPStan, but PhpStorm will show `array` on hover because the `@return` annotation overrides its inference.

## Functions

The marker works equally well on standalone functions:

```php
/**
 * @phpstan-return array @phpstan-infer-return
 */
function defaultConfig(): array
{
    return [
        'timeout' => 30,
        'retries' => 3,
    ];
}
```

## Nested calls

The return expression may call other methods or functions that are also annotated with `@phpstan-infer-return`. Their shapes are resolved recursively:

```php
/** @phpstan-return array @phpstan-infer-return */
private function baseHeaders(): array
{
    return ['Content-Type' => 'application/json'];
}

/** @phpstan-return array @phpstan-infer-return */
public function requestHeaders(): array
{
    return [
        'headers' => $this->baseHeaders(),
        'version' => 2,
    ];
}
```

Supported nested call forms: `$this->method()`, `self::method()`, `static::method()`, and direct function calls.

## Error reporting

If PHPStan cannot statically resolve the return expression (e.g. it contains a variable value or a non-inferred call), it reports `arrayTypeInference.missingType` instead of the generic `missingType.iterableValue` (raised at level 6+). This keeps the error actionable: the specific identifier tells you the extension attempted inference but could not complete it.

## Scope

Only **static** return expressions are supported — arrays whose keys and values are all compile-time literals or calls to other inferred callables. Dynamic values (variables, conditionals, loops) cause inference to fail gracefully with the error above.
