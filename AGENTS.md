# Agent Instructions — phpstan-type-utilities

A PHPStan extension (type `phpstan-extension`) that provides **static type utilities** for PHP projects, inspired by TypeScript utility types but implemented through PHPDoc/PHPStan extensions. Today it exposes `\ReturnType<callable>` for reusing any callable return type PHPStan can resolve, and provides best-effort static inference for functions and methods returning plain `array` through `@return array @phpstan-infer-return`.

The current implementation is intentionally conservative. It infers static PHP array shapes from code; it does **not** treat arrays differently based on their contents or try to interpret domain-specific array structures. Any future domain-specific helpers must be implemented as explicit utilities rather than hidden behavior in array inference.

## Scope

| Context          | Inference strategy                                          |
| ---------------- | ----------------------------------------------------------- |
| Callable returns | `\ReturnType<self, 'method'>`, `\ReturnType<Fully\Qualified\functionName>`, or the fully-qualified marker class form |
| Inferred arrays  | `@return array @phpstan-infer-return`, inferred from static return expressions |

## Project Goals

- Build explicit PHPStan/PHPDoc type utilities that feel familiar to developers who use TypeScript utility types.
- Keep each utility opt-in and easy to explain from the annotation syntax.
- Keep `ReturnType<callable>` independent from inferred arrays: it asks for a callable's return type, whatever mechanism provides that type: native declarations, PHPDoc, PHPStan extensions, or `@phpstan-infer-return`.
- Prefer precise static inference when the source code is provably static.
- Fail with custom diagnostics instead of silently widening to useful-looking but incorrect types.
- Avoid semantic magic: array literals are inferred as PHP array shapes only, not interpreted as domain-specific languages.

## Behavior Rules

- **`\ReturnType<Fully\Qualified\functionName>`** → resolves a function return type.
- **`\ReturnType<Class, 'method'>`** → resolves a method return type.
- **`\Amiut\PHPStan\TypeUtilities\ReturnType<...>`** → fully-qualified marker form also resolves for IDEs that prefer a known class name.
- **`@return array @phpstan-infer-return`** → marker for inferring the annotated callable's own static array shape.
- **IDE-facing parameter/return docs** → use regular `@param array` / `@return array` plus PHPStan-specific `@phpstan-param ReturnTypeAlias` / `@phpstan-return ReturnTypeAlias` so editors do not treat `ReturnType<...>` as a native array replacement.
- **Inference succeeds** → `missingType.iterableValue` is suppressed; no custom error is emitted; inferred type is stored in the call-site cache.
- **Inference fails** → `missingType.iterableValue` is suppressed and `arrayTypeInference.missingType` or `arrayTypeInference.returnTypeUnresolved` is reported instead.
- **Assignment mismatch** → writes to known inferred array-shape offsets report `arrayTypeInference.assignmentType`.
- **Call-site type** → when PHPStan evaluates a function/method call whose return type was successfully inferred, it receives the inferred `ConstantArrayType` (not plain `array`).

## PHPStan Node Architecture (critical)

Function/method `missingType.iterableValue` errors are reported by PHPStan on wrapper nodes such as **`InClassMethodNode`** and **`InFunctionNode`**, not on return-statement nodes. The `IgnoreErrorExtension` suppresses only `@return array @phpstan-infer-return` diagnostics that this extension will replace.

| PHPStan API                       | Used for                                                                        |
| --------------------------------- | ------------------------------------------------------------------------------- |
| `TypeNodeResolverExtension`       | Resolves `\ReturnType<...>` PHPDoc utilities and the fully-qualified marker form |
| `CallableReturnTypeResolver`      | Resolves callable return types via PHPStan reflection or inferred-array precision |
| `IgnoreErrorExtension`            | Suppresses built-in iterable/unresolvable errors for inferred return markers    |
| `Rule<FileNode>`                  | Reports custom errors for failed infer-return / `ReturnType` usage              |
| `Rule<Assign>`                    | Reports writes that violate inferred array-shape offset types                   |
| `ExpressionTypeResolverExtension` | Intercepts nested function/method calls and returns inferred types              |
| `InitializerExprTypeResolver`     | Resolves scalar literals, constants, and other initializer-safe expressions      |

## Call-Site Type Propagation

`ReturnType<callable>` resolves through `CallableReturnTypeResolver`, which first asks for any more precise inferred-array return type and otherwise falls back to PHPStan's reflected callable return type. `ArrayReturnTypeInferer` is separate: it parses callables marked with `@return array @phpstan-infer-return`, evaluates static array return expressions, resolves nested inferred calls, and stores successful types in `InferredReturnTypeCache`.

## Project Structure

```
src/
  ArrayReturnTypeInferer.php           # Shared on-demand inference logic
  ArrayReturnTypeInferenceResult.php   # Success/failure value object
  ArrayTypeInferenceHelper.php         # Shared type usefulness checks
  InferredReturnTypeCache.php           # Shared mutable cache: ClassName::methodName → Type
  CallableReturnTypeResolver.php        # Resolves callable return types for ReturnType
  ReturnTypeNodeResolverExtension.php    # Resolves ReturnType PHPDoc utility
  ArrayTypeInferenceIgnoreExtension.php # Suppresses replaced built-in PHPStan errors
  ArrayTypeInferencePhpDocRule.php      # Reports custom inference failures
  ArrayShapeAssignmentRule.php          # Reports inferred shape assignment mismatches
  ArrayTypeInferenceCallSiteExtension.php # ExpressionTypeResolverExtension for nested calls
ide/
  ReturnType.php                        # IDE-only marker class, intentionally not Composer-autoloaded
tests/
  fixtures/
    InferFixture.php        # Fixture class/functions analyzed by resolver tests
  e2e/
    ConfigBuilder.php       # End-to-end fixture: PHPStan max level, must produce no errors
  bootstrap.php
extension.neon                          # Registers services and rules
phpstan.neon                            # PHPStan config for e2e analysis (tests/e2e)
phpunit.xml.dist                        # PHPUnit configuration
composer.json                           # Package manifest (type: phpstan-extension)
```

## Testing

Tests use `PHPStan\Testing\RuleTestCase` which runs the registered rule against a fixture file and asserts expected errors. `getAdditionalConfigFiles()` loads `extension.neon` so the `ArrayTypeInferenceIgnoreExtension` is active during tests.

```bash
composer test
# or
vendor/bin/phpunit
```

When adding a new test case to a fixture file, run the test once with line `0` to let the failure output reveal the actual line number, then update the assertion.

## Key Conventions

- **PHP 7.4 syntax only** — no constructor property promotion, no union types in declarations, no named arguments. The package targets `^7.4 || ^8.0`.
- **PHPDoc for type annotations** — use `@var`, `@return`, `@implements` (e.g., `@implements Rule<MethodReturnStatementsNode>`) because PHP 7.4 lacks generics syntax.
- PSR-4 namespace: `Amiut\PHPStan\TypeUtilities\` → `src/`
- All classes are `final`.
- `declare(strict_types=1)` at the top of every PHP file.

## PHPStan API Compatibility

Requires `phpstan/phpstan: ^2.1.7`. Use the PHPStan 2.x API surfaces — e.g. `RuleErrorBuilder`, `IdentifierRuleError`, `IgnoreErrorExtension::shouldIgnore`.

## Skills

Extended PHPStan extension development knowledge (custom phpDoc types, `TypeNodeResolverExtension`, call-site inference, neon registration) is documented in `.agents/skills/phpstan-extension/SKILL.md`.
