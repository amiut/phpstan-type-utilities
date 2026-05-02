# PHPStan Extension Development Skill

Use this skill when implementing, modifying, or debugging PHPStan extensions.
It applies to all tasks touching `Rule`, `IgnoreErrorExtension`, type inference
extensions, `.neon` configuration, or any `PHPStan\*` API.

---

## Core concepts

### Abstract Syntax Tree (AST)

PHPStan uses **php-parser** to turn source files into an AST.  Nodes come from
two namespaces:

- `PhpParser\Node\*` — actual PHP language nodes (`Expr\New_`, `Stmt\Class_`, …)
- `PHPStan\Node\*` — virtual nodes synthesised by PHPStan to make analysis
  easier (`MethodReturnStatementsNode`, `InClassNode`, `FileNode`, …)

To discover which node type covers a situation, temporarily register a rule for
`\PhpParser\Node::class`, dump `get_class($node)` in `processNode()`, and run
PHPStan with `--debug`.

### Scope

`PHPStan\Analyser\Scope` is passed to every extension method.  Key methods:

```php
$scope->getType($expr);            // PHPStan\Type\Type for any expression
$scope->isInClass();               // bool
$scope->getClassReflection();      // ClassReflection (non-null when isInClass())
$scope->getFunction();             // FunctionReflection|MethodReflection|null
$scope->getFile();                 // string — absolute file path
$scope->resolveName($nameNode);    // resolves 'self', 'static', relative names
```

### Type system

Every PHP type is represented by a class implementing `PHPStan\Type\Type`.
Common implementations: `StringType`, `IntegerType`, `ArrayType`,
`ConstantArrayType`, `ObjectType`, `UnionType`, `IntersectionType`, `MixedType`,
`NeverType`.

**Do not use `instanceof` to test a type** — it misses unions, intersections,
and accessory types.  Use the shortcut query methods instead:

```php
$type->isString()->yes()       // TrinaryLogic
$type->isArray()->yes()
$type->isNull()->yes()
$type->getIterableValueType()  // Type
```

Use `isSuperTypeOf()` for set-membership questions:

```php
(new StringType())->isSuperTypeOf($type);  // yes | maybe | no
```

Combine types with `TypeCombinator` to keep them normalised:

```php
TypeCombinator::union($a, $b);
TypeCombinator::intersect($a, $b);
```

### Trinary logic

Many methods return `TrinaryLogic` — not `bool`.  Always compare explicitly:

```php
$result->yes()    // definitely true
$result->maybe()  // unknown / might be true
$result->no()     // definitely false
```

Never cast `TrinaryLogic` to `bool`.

### Reflection

`ClassReflection`, `MethodReflection`, `PropertyReflection` etc. are value
objects obtained from `Scope` or `ReflectionProvider`.

```php
$classRef  = $scope->getClassReflection();
$methodRef = $node->getMethodReflection();   // on virtual nodes like MethodReturnStatementsNode
$returnType = $methodRef->getNativeReturnType();
$docComment = $methodRef->getDocComment();   // string|null
```

To look up arbitrary classes by name, inject `ReflectionProvider` as a
constructor service.

### Dependency injection

PHPStan uses `nette/di`.  Configuration is `.neon` files.

- **Services** — long-lived singletons registered under `services:`.
  Constructor dependencies are autowired automatically when there is a single
  candidate.
- **Value objects** — short-lived; created with `new` or obtained from Scope /
  Reflection.  Never register them as services.

Custom parameters require both an `arguments:` entry and a `parametersSchema:`
declaration.

---

## Extension taxonomy

| Category              | Interface / base class                                    | NEON tag                                                    |
| --------------------- | --------------------------------------------------------- | ----------------------------------------------------------- |
| Custom rule           | `PHPStan\Rules\Rule<TNode>`                               | listed under `rules:` **or** tag `phpstan.rules.rule`       |
| Ignore error          | `PHPStan\Analyser\IgnoreErrorExtension`                   | `phpstan.ignoreErrorExtension`                              |
| Custom phpDoc type    | `PHPStan\PhpDoc\TypeNodeResolverExtension`                | `phpstan.phpDoc.typeNodeResolverExtension`                  |
| Dynamic return        | `PHPStan\Type\DynamicMethodReturnTypeExtension`           | `phpstan.broker.dynamicMethodReturnTypeExtension`           |
| Dynamic return (static) | `PHPStan\Type\DynamicStaticMethodReturnTypeExtension`   | `phpstan.broker.dynamicStaticMethodReturnTypeExtension`     |
| Dynamic return (fn)   | `PHPStan\Type\DynamicFunctionReturnTypeExtension`         | `phpstan.broker.dynamicFunctionReturnTypeExtension`         |
| Expression type       | `PHPStan\Type\ExpressionTypeResolverExtension`            | `phpstan.broker.expressionTypeResolverExtension`            |
| Type specifying       | `PHPStan\Type\TypeSpecifyingExtension`                    | various `phpstan.typeSpecifier.*` tags                      |
| Class reflection      | `PHPStan\Reflection\PropertiesClassReflectionExtension` etc. | various `phpstan.broker.*` tags                         |
| Collectors            | `PHPStan\Collectors\Collector<TNode, TValue>`             | listed under `services:` with tag `phpstan.collector`       |

---

## Custom rules

### Minimal rule

```php
<?php
declare(strict_types=1);

namespace MyNs;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\MethodReturnStatementsNode;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<MethodReturnStatementsNode>
 */
final class MyRule implements Rule
{
    public function getNodeType(): string
    {
        return MethodReturnStatementsNode::class;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        // guard: ensure the correct node type (needed for PHPStan analysis of the rule itself)
        if (!$node instanceof MethodReturnStatementsNode) {
            return [];
        }

        // ... analysis logic ...

        return [
            RuleErrorBuilder::message('Describe the problem here.')
                ->identifier('myExtension.ruleId')  // dot-separated identifier
                ->build(),
        ];
    }
}
```

### Registration

Simple case (no constructor args beyond autowired services):

```neon
rules:
    - MyNs\MyRule
```

With constructor arguments or custom tags:

```neon
services:
    -
        class: MyNs\MyRule
        arguments:
            myParam: %myExtension.myParam%
        tags:
            - phpstan.rules.rule
```

### Useful virtual nodes

| Node                              | When to use                                        |
| --------------------------------- | -------------------------------------------------- |
| `MethodReturnStatementsNode`      | Inspect all return statements of a method          |
| `InClassNode`                     | Class-level checks where `$scope->getClassReflection()` must be non-null |
| `InClassMethodNode`               | Method-level checks with correct scope             |
| `ClassPropertyNode`               | Properties including promoted constructor params   |
| `FileNode`                        | File-level checks (e.g., `declare(strict_types=1)`) |

---

## Ignore error extensions

Use when you want to suppress a built-in PHPStan error (identified by its
`identifier` string) only under specific conditions known at analysis time.

```php
<?php
declare(strict_types=1);

namespace MyNs;

use PhpParser\Node;
use PHPStan\Analyser\Error;
use PHPStan\Analyser\IgnoreErrorExtension;
use PHPStan\Analyser\Scope;

final class MyIgnoreExtension implements IgnoreErrorExtension
{
    public function shouldIgnore(Error $error, Node $node, Scope $scope): bool
    {
        if ($error->getIdentifier() !== 'missingType.iterableValue') {
            return false;
        }

        // additional context checks via $node and $scope ...

        return true;
    }
}
```

Registration:

```neon
services:
    -
        class: MyNs\MyIgnoreExtension
        tags:
            - phpstan.ignoreErrorExtension
```

The `shouldIgnore` method may be called for **every** error, so filter by
identifier first and return early.

---

## Custom phpDoc type extensions (`TypeNodeResolverExtension`)

Use this extension point to create custom generic type annotations that can be
used in phpDoc comments.  PHPStan resolves these at type-inference time, so
callers benefit automatically — no runtime code needed.

### Interfaces

| Interface                       | Purpose                                           |
| ------------------------------- | ------------------------------------------------- |
| `TypeNodeResolverExtension`     | Required — implement `resolve(TypeNode, NameScope): ?Type` |
| `TypeNodeResolverAwareExtension`| Optional — inject `TypeNodeResolver` to resolve sub-types recursively |

### Minimal implementation

```php
<?php
declare(strict_types=1);

namespace MyNs;

use PHPStan\Analyser\NameScope;
use PHPStan\PhpDoc\TypeNodeResolver;
use PHPStan\PhpDoc\TypeNodeResolverAwareExtension;
use PHPStan\PhpDoc\TypeNodeResolverExtension;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\Type\ErrorType;
use PHPStan\Type\Type;

final class MyCustomType implements TypeNodeResolverExtension, TypeNodeResolverAwareExtension
{
    /** @var TypeNodeResolver */
    private $typeNodeResolver;

    public function setTypeNodeResolver(TypeNodeResolver $typeNodeResolver): void
    {
        $this->typeNodeResolver = $typeNodeResolver;
    }

    public function resolve(TypeNode $typeNode, NameScope $nameScope): ?Type
    {
        // Returning null means "not interested" — PHPStan falls back to normal handling.
        if (! $typeNode instanceof GenericTypeNode) {
            return null;
        }
        // Type name matches the globally-qualified name used in phpDoc.
        if ('\MyCustomType' !== $typeNode->type->name) {
            return null;
        }

        $arguments = $typeNode->genericTypes;
        if (2 !== \count($arguments)) {
            return null; // wrong arity — let PHPStan error naturally
        }

        // Resolve sub-types using the injected resolver
        $firstType = $this->typeNodeResolver->resolve($arguments[0], $nameScope);
        $constantArrays = $firstType->getConstantArrays();

        if (0 === \count($constantArrays)) {
            return new ErrorType(); // signals a bad/unresolvable type
        }

        // ... transform and return a Type
    }
}
```

### Key APIs for array shape manipulation

```php
use PHPStan\Type\Constant\ConstantArrayType;
use PHPStan\Type\Constant\ConstantArrayTypeBuilder;
use PHPStan\Type\TypeCombinator;

// Build a new array shape from scratch
$builder = ConstantArrayTypeBuilder::createEmpty();
$builder->setOffsetValueType($keyType, $valueType, $isOptional); // $isOptional: bool
$result  = $builder->getArray();

// Iterate an existing shape
foreach ($constantArray->getKeyTypes() as $i => $keyType) {
    $valueType  = $constantArray->getValueTypes()[$i];
    $isOptional = $constantArray->isOptionalKey($i);
}

// Combine multiple types into a union
TypeCombinator::union(...$types); // normalises automatically

// Extract constant-valued sub-types from a resolved Type
$constantArrays  = $resolvedType->getConstantArrays();   // ConstantArrayType[]
$constantStrings = $resolvedType->getConstantStrings();  // ConstantStringType[]
$stringValue     = $constantStringType->getValue();      // string
```

### Registration

```neon
services:
    -
        class: MyNs\MyCustomType
        tags:
            - phpstan.phpDoc.typeNodeResolverExtension
```

### Common patterns

A `TypeNodeResolverExtension` can resolve any arbitrary phpDoc type name.
A concrete use case is a `ReturnType<Class, 'method'>` utility that lets
downstream code reference the inferred array shape of another callable without
duplicating the type annotation.

**Single arity — function return type:**

```php
/** @return array @phpstan-infer-return */
function buildConfig(): array { return ['debug' => false, 'env' => 'prod']; }

/** @var ReturnType<\buildConfig> $cfg */
$cfg = buildConfig(); // PHPStan knows shape: array{debug: bool, env: string}
```

**Two-arity — method return type:**

```php
class Formatter
{
    /** @return array @phpstan-infer-return */
    public function schema(): array { return ['type' => 'object', 'nullable' => false]; }
}

/** @param ReturnType<Formatter, 'schema'> $data */
function process(array $data): void { ... }
```

Inside `resolve()`, the extension:
1. Checks `$typeNode instanceof GenericTypeNode` and validates the type name.
2. Reads `$typeNode->genericTypes` — one arg = function, two args = class + method.
3. Uses `$nameScope->resolveStringName()` to expand relative class names (`self`, `static`).
4. Delegates to an inferer to get the cached `ConstantArrayType`, or returns `new ErrorType()` on failure.

**Accepting both the short and fully-qualified form** prevents IDE-namespace
resolution from breaking the lookup:

```php
private function isMyTypeName(string $name): bool
{
    $name = ltrim($name, '\\');
    return $name === 'ReturnType'
        || $name === 'My\\Namespace\\ReturnType';
}
```

The global `\` prefix on the type name is necessary: PHPStan resolves phpDoc
type names using the namespace context of the file being analysed. Prefixing
with `\` forces the global namespace and prevents collisions with class names
in the same namespace.

---

## Dynamic return type extensions

Use when a method's return type depends on its argument types.

```php
<?php
declare(strict_types=1);

namespace MyNs;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Type;

final class MyDynamicReturnExtension implements DynamicMethodReturnTypeExtension
{
    public function getClass(): string
    {
        return \My\TargetClass::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'targetMethod';
    }

    public function getTypeFromMethodCall(
        MethodReflection $methodReflection,
        MethodCall $methodCall,
        Scope $scope
    ): ?Type {
        if ($methodCall->getArgs() === []) {
            return null;  // fall back to the declared return type
        }

        return $scope->getType($methodCall->getArgs()[0]->value);
    }
}
```

Registration:

```neon
services:
    -
        class: MyNs\MyDynamicReturnExtension
        tags:
            - phpstan.broker.dynamicMethodReturnTypeExtension
```

---

## Testing

### Rule tests

```php
<?php
declare(strict_types=1);

namespace MyNs\Tests;

use MyNs\MyRule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<MyRule>
 */
final class MyRuleTest extends RuleTestCase
{
    protected function getRule(): \PHPStan\Rules\Rule
    {
        return new MyRule(/* inject deps */);
    }

    public function testRule(): void
    {
        $this->analyse([__DIR__ . '/data/my-rule.php'], [
            ['Expected error message', 10],  // [message, line]
        ]);
    }

    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/../../extension.neon'];
    }
}
```

Place fixture files under `tests/data/` — they contain ordinary PHP that
triggers (or doesn't trigger) errors.

### Type inference tests

For extensions that influence inferred types (not rules):

```php
use PHPStan\Testing\TypeInferenceTestCase;

final class MyExtensionTest extends TypeInferenceTestCase
{
    /**
     * @return iterable<mixed>
     */
    public static function dataFileAsserts(): iterable
    {
        yield from self::gatherAssertTypes(__DIR__ . '/data/my-types.php');
    }

    /** @dataProvider dataFileAsserts */
    public function testFileAsserts(string $assertType, string $file, mixed ...$args): void
    {
        $this->assertFileAsserts($assertType, $file, ...$args);
    }

    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/../../extension.neon'];
    }
}
```

Data file uses `\PHPStan\Testing\assertType()`:

```php
use function PHPStan\Testing\assertType;

assertType('string', $obj->method());
```

---

## Project conventions (this repository)

This package targets **PHP 7.4+**, so:

- **No constructor property promotion** — use explicit property declarations
- **No union types in declarations** — use PHPDoc `@param`/`@return` instead
- **No named arguments** in calls
- **No `mixed` in native declarations** — use PHPDoc only
- Use `@implements Rule<TNode>` PHPDoc for the generic type parameter
- All extension classes are `final`
- `declare(strict_types=1)` at the top of every file
- PSR-4 namespace: `Amiut\PHPStan\InferableArrayReturn\` → `src/`

### `MethodReturnStatementsNode` API cheat sheet

```php
$node->getMethodReflection()    // MethodReflection
$node->getClassReflection()     // ClassReflection
$node->getMethodName()          // string
$node->getReturnStatements()    // ReturnStatement[]
$node->isGenerator()            // bool

// ReturnStatement
$stmt->getReturnNode()          // PhpParser\Node\Stmt\Return_
$stmt->getScope()               // Scope  (use to call ->getType($expr))
```

### Extension registration pattern

```neon
services:
    -
        class: Amiut\PHPStan\InferableArrayReturn\SomeHelper

    -
        class: Amiut\PHPStan\InferableArrayReturn\SomeIgnoreExtension
        tags:
            - phpstan.ignoreErrorExtension

rules:
    - Amiut\PHPStan\InferableArrayReturn\SomeRule
```

Shared helper classes (like `ArrayReturnTypeInference`) are registered as plain
services without tags; PHPStan's DI autowires them into extension constructors.

---

## Reference links

- Extension types overview: https://phpstan.org/developing-extensions/extension-types
- Custom rules: https://phpstan.org/developing-extensions/rules
- Custom phpDoc types: https://phpstan.org/developing-extensions/custom-phpdoc-types
- Ignore error extensions: https://phpstan.org/developing-extensions/ignore-error-extensions
- Type system: https://phpstan.org/developing-extensions/type-system
- Scope: https://phpstan.org/developing-extensions/scope
- DI & configuration: https://phpstan.org/developing-extensions/dependency-injection-configuration
- Testing: https://phpstan.org/developing-extensions/testing
- API reference: https://apiref.phpstan.org/
