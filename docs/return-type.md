# `ReturnType<callable>` — Reuse Callable Return Types

`ReturnType<callable>` is a PHPDoc utility type that resolves to the return type of a method or function. It is a general callable utility and does not require `@phpstan-infer-return`. If PHPStan can understand the callable's return type, `ReturnType<callable>` can reuse it.

## Syntax

| Form                                                        | Resolves                                    |
| ----------------------------------------------------------- | ------------------------------------------- |
| `\ReturnType<self, 'methodName'>`                           | Method on the current class                 |
| `\ReturnType<ClassName, 'methodName'>`                      | Method on another class (fully qualified)   |
| `\ReturnType<Fully\Qualified\functionName>`                 | Standalone function (fully qualified)       |
| `\Amiut\PHPStan\TypeUtilities\ReturnType<self, 'method'>`  | Same as above — IDE-friendly fully qualified |

## Basic example

```php
final class Counter
{
    public function count(): int
    {
        return 5;
    }

    /** @phpstan-param \ReturnType<self, 'count'> $count */
    public function setCount(int $count): void {}
}
```

`\ReturnType<self, 'count'>` resolves to `int`.

## PHPDoc return type example

```php
final class Config
{
    /** @return array{enabled: bool, limit: int} */
    public function options(): array
    {
        return ['enabled' => true, 'limit' => 100];
    }

    /** @phpstan-param \ReturnType<self, 'options'> $options */
    public function apply(array $options): void {}
}
```

`\ReturnType<self, 'options'>` resolves to `array{enabled: bool, limit: int}`.

## With inferred arrays

`@phpstan-infer-return` is a separate utility. When a callable uses it, the callable's return type becomes a precise static array shape, and `ReturnType<callable>` can reuse that type like any other return type.

```php
final class Config
{
    /** @return array @phpstan-infer-return */
    public function options(): array
    {
        return [
            'enabled' => true,
            'limit'   => 100,
        ];
    }

    /**
     * @param \ReturnType<self, 'options'> $opts
     */
    public function apply(array $opts): void
    {
        // PHPStan knows $opts['enabled'] is bool and $opts['limit'] is int
    }
}
```

## Defining a class-level alias

Repeating `\ReturnType<self, 'options'>` everywhere is verbose. Define it once as a `@phpstan-type` alias at the top of the class:

```php
/**
 * @phpstan-type Options \ReturnType<self, 'options'>
 */
final class Config
{
    /** @return array @phpstan-infer-return */
    public function options(): array
    {
        return ['enabled' => true, 'limit' => 100];
    }

    /** @phpstan-param Options $opts */
    public function apply(array $opts): void {}

    /**
     * @return array
     * @phpstan-return Options
     */
    public function defaults(): array
    {
        return $this->options();
    }
}
```

## Referencing another class

```php
/**
 * @phpstan-type ProductOptions \ReturnType<App\Product\Config, 'options'>
 */
final class ProductService
{
    /** @phpstan-param ProductOptions $opts */
    public function process(array $opts): void {}
}
```

## Referencing a function

```php
/**
 * @return array @phpstan-infer-return
 */
function defaultHeaders(): array
{
    return ['Content-Type' => 'application/json', 'Accept' => 'application/json'];
}

/**
 * @phpstan-type Headers \ReturnType<App\Http\defaultHeaders>
 */
final class HttpClient
{
    /** @phpstan-param Headers $headers */
    public function withHeaders(array $headers): void {}
}
```

## IDE compatibility

IDEs may show an error for `\ReturnType<...>` because they treat it as a class reference. Two options:

**Option 1** — Use the fully-qualified form that resolves to the real marker class:

```php
/** @param \Amiut\PHPStan\TypeUtilities\ReturnType<self, 'options'> $opts */
```

The `ide/ReturnType.php` stub ships with the package for IDEs that perform class existence checks.

**Option 2** — Keep editor-facing tags as plain `array` and restrict `ReturnType` to PHPStan-only tags:

```php
/**
 * @param array $opts        ← IDE sees this
 * @phpstan-param Options $opts  ← PHPStan sees this
 */
public function apply(array $opts): void {}
```

## Error reporting

If the target callable does not exist, PHPStan reports `arrayTypeInference.returnTypeUnresolved` at the point where `ReturnType<>` is used. If the callable is marked with `@phpstan-infer-return` but its static array shape cannot be inferred, PHPStan reports the same identifier with the inference failure reason.
