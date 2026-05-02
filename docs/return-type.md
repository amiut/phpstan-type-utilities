# `ReturnType<>` — Reuse Inferred Shapes

`ReturnType<>` is a PHPDoc utility type that resolves to the inferred shape of any method or function annotated with `@phpstan-infer-return`. Use it to share a type between multiple callables without duplicating annotations.

## Syntax

| Form                                                        | Resolves                                    |
| ----------------------------------------------------------- | ------------------------------------------- |
| `\ReturnType<self, 'methodName'>`                           | Method on the current class                 |
| `\ReturnType<ClassName, 'methodName'>`                      | Method on another class (fully qualified)   |
| `\ReturnType<Fully\Qualified\functionName>`                 | Standalone function (fully qualified)       |
| `\Amiut\PHPStan\TypeUtilities\ReturnType<self, 'method'>`  | Same as above — IDE-friendly fully qualified |

## Basic example

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

If the target callable does not exist or was not annotated with `@phpstan-infer-return`, PHPStan reports `arrayTypeInference.returnTypeUnresolved` at the point where `ReturnType<>` is used.
