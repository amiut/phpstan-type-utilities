<?php

declare(strict_types=1);

namespace Amiut\PHPStan\TypeUtilities;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Return_;
use PHPStan\Parser\Parser;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\InitializerExprContext;
use PHPStan\Reflection\InitializerExprTypeResolver;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\Constant\ConstantArrayType;
use PHPStan\Type\Constant\ConstantIntegerType;
use PHPStan\Type\MixedType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use function count;
use function explode;
use function in_array;
use function is_string;
use function preg_match;
use function sprintf;
use function strtolower;

final class ArrayReturnTypeInferer
{
    /**
     * @var InferredReturnTypeCache
     */
    private $cache;

    /**
     * @var ArrayTypeInferenceHelper
     */
    private $helper;

    /**
     * @var ReflectionProvider
     */
    private $reflectionProvider;

    /**
     * @var InitializerExprTypeResolver
     */
    private $initializerExprTypeResolver;

    /**
     * @var Parser
     */
    private $parser;

    /**
     * @var array<string, true>
     */
    private $inferring = [];

    public function __construct(
        InferredReturnTypeCache $cache,
        ArrayTypeInferenceHelper $helper,
        ReflectionProvider $reflectionProvider,
        InitializerExprTypeResolver $initializerExprTypeResolver,
        Parser $parser
    ) {
        $this->cache = $cache;
        $this->helper = $helper;
        $this->reflectionProvider = $reflectionProvider;
        $this->initializerExprTypeResolver = $initializerExprTypeResolver;
        $this->parser = $parser;
    }

    public function inferMethod(string $className, string $methodName, bool $allowInferReturnDoc): ArrayReturnTypeInferenceResult
    {
        $className = ltrim($className, '\\');
        $key = $className . '::' . $methodName;
        $cached = $this->cache->get($key);

        if ($cached !== null) {
            return ArrayReturnTypeInferenceResult::success($cached);
        }

        return $this->guarded($key, function () use ($className, $methodName, $allowInferReturnDoc, $key): ArrayReturnTypeInferenceResult {
            if (!$this->reflectionProvider->hasClass($className)) {
                return ArrayReturnTypeInferenceResult::failure(sprintf('Class %s was not found.', $className));
            }

            $classReflection = $this->reflectionProvider->getClass($className);
            $fileName = $classReflection->getFileName();

            if ($fileName === null) {
                return ArrayReturnTypeInferenceResult::failure(sprintf('Class %s does not have a source file.', $className));
            }

            $classMethod = $this->findClassMethod($this->parser->parseFile($fileName), $classReflection, $methodName);

            if ($classMethod === null) {
                return ArrayReturnTypeInferenceResult::failure(sprintf('Method %s::%s() was not found.', $className, $methodName));
            }

            $context = InitializerExprContext::fromClassMethod($className, null, $methodName, $fileName);
            $result = $this->inferFromClassMethod($classMethod, $className, $classReflection, $context, $allowInferReturnDoc);

            if ($result->getType() !== null) {
                $this->cache->store($key, $result->getType());
            }

            return $result;
        });
    }

    public function inferFunction(string $functionName, bool $allowInferReturnDoc): ArrayReturnTypeInferenceResult
    {
        $functionName = '\\' . ltrim($functionName, '\\');
        $key = 'function:' . strtolower($functionName);
        $cached = $this->cache->get($key);

        if ($cached !== null) {
            return ArrayReturnTypeInferenceResult::success($cached);
        }

        return $this->guarded($key, function () use ($functionName, $allowInferReturnDoc, $key): ArrayReturnTypeInferenceResult {
            $nameNode = new Name\FullyQualified(ltrim($functionName, '\\'));

            if (!$this->reflectionProvider->hasFunction($nameNode, null)) {
                return ArrayReturnTypeInferenceResult::failure(sprintf('Function %s() was not found.', $functionName));
            }

            $functionReflection = $this->reflectionProvider->getFunction($nameNode, null);
            $fileName = $functionReflection->getFileName();

            if ($fileName === null) {
                return ArrayReturnTypeInferenceResult::failure(sprintf('Function %s() does not have a source file.', $functionName));
            }

            $function = $this->findFunction($this->parser->parseFile($fileName), $functionName);

            if ($function === null) {
                return ArrayReturnTypeInferenceResult::failure(sprintf('Function %s() was not found.', $functionName));
            }

            $context = InitializerExprContext::fromFunction($functionName, $fileName);
            $result = $this->inferFromFunction($function, $functionName, $context, $allowInferReturnDoc);

            if ($result->getType() !== null) {
                $this->cache->store($key, $result->getType());
            }

            return $result;
        });
    }

    public function inferFunctionFromFile(string $functionName, string $fileName, bool $allowInferReturnDoc): ArrayReturnTypeInferenceResult
    {
        $functionName = '\\' . ltrim($functionName, '\\');
        $key = 'function:' . strtolower($functionName);
        $cached = $this->cache->get($key);

        if ($cached !== null) {
            return ArrayReturnTypeInferenceResult::success($cached);
        }

        return $this->guarded($key, function () use ($functionName, $fileName, $allowInferReturnDoc, $key): ArrayReturnTypeInferenceResult {
            $function = $this->findFunction($this->parser->parseFile($fileName), $functionName);

            if ($function === null) {
                return ArrayReturnTypeInferenceResult::failure(sprintf('Function %s() was not found in %s.', $functionName, $fileName));
            }

            $context = InitializerExprContext::fromFunction($functionName, $fileName);
            $result = $this->inferFromFunction($function, $functionName, $context, $allowInferReturnDoc);

            if ($result->getType() !== null) {
                $this->cache->store($key, $result->getType());
            }

            return $result;
        });
    }

    /**
     * @param callable(): ArrayReturnTypeInferenceResult $callback
     */
    private function guarded(string $key, callable $callback): ArrayReturnTypeInferenceResult
    {
        if (isset($this->inferring[$key])) {
            return ArrayReturnTypeInferenceResult::failure(sprintf('Recursive inferred callable %s cannot be resolved.', $key));
        }

        $this->inferring[$key] = true;

        try {
            return $callback();
        } finally {
            unset($this->inferring[$key]);
        }
    }

    private function inferFromClassMethod(
        ClassMethod $classMethod,
        string $className,
        ClassReflection $classReflection,
        InitializerExprContext $context,
        bool $allowInferReturnDoc
    ): ArrayReturnTypeInferenceResult {
        if (!$this->hasNativeArrayReturnType($classMethod->returnType)) {
            return ArrayReturnTypeInferenceResult::failure(sprintf('Method %s::%s() does not declare native return type array.', $className, $classMethod->name->name));
        }

        if (!$allowInferReturnDoc && !$this->hasInferReturnDoc($classMethod)) {
            return ArrayReturnTypeInferenceResult::failure(sprintf('Method %s::%s() is not annotated with @phpstan-infer-return.', $className, $classMethod->name->name));
        }

        return $this->inferFromStatements($classMethod->getStmts(), $className, $classReflection, $context, sprintf('Method %s::%s()', $className, $classMethod->name->name));
    }

    private function inferFromFunction(
        Function_ $function,
        string $functionName,
        InitializerExprContext $context,
        bool $allowInferReturnDoc
    ): ArrayReturnTypeInferenceResult {
        if (!$this->hasNativeArrayReturnType($function->returnType)) {
            return ArrayReturnTypeInferenceResult::failure(sprintf('Function %s() does not declare native return type array.', $functionName));
        }

        if (!$allowInferReturnDoc && !$this->hasInferReturnDoc($function)) {
            return ArrayReturnTypeInferenceResult::failure(sprintf('Function %s() is not annotated with @phpstan-infer-return.', $functionName));
        }

        return $this->inferFromStatements($function->getStmts(), null, null, $context, sprintf('Function %s()', $functionName));
    }

    /**
     * @param Node\Stmt[]|null $stmts
     */
    private function inferFromStatements(
        ?array $stmts,
        ?string $className,
        ?ClassReflection $classReflection,
        InitializerExprContext $context,
        string $label
    ): ArrayReturnTypeInferenceResult {
        if ($stmts === null || $stmts === []) {
            return ArrayReturnTypeInferenceResult::failure($label . ' does not contain statements.');
        }

        $returnExprs = $this->collectReturnExprs($stmts);

        if ($returnExprs === []) {
            return ArrayReturnTypeInferenceResult::failure($label . ' does not contain a single static return expression.');
        }

        $types = [];

        foreach ($returnExprs as $expr) {
            $type = $this->evalExpr($expr, $className, $classReflection, $context);

            if ($type === null || !$this->helper->hasUsefulArrayValueType($type)) {
                return ArrayReturnTypeInferenceResult::failure($label . ' return type could not be inferred from static array expressions.');
            }

            $types[] = $type;
        }

        return ArrayReturnTypeInferenceResult::success(TypeCombinator::union(...$types));
    }

    private function evalExpr(Node\Expr $expr, ?string $className, ?ClassReflection $classReflection, InitializerExprContext $context): ?Type
    {
        if ($expr instanceof MethodCall
            && $className !== null
            && $classReflection !== null
            && $expr->var instanceof Variable
            && is_string($expr->var->name)
            && $expr->var->name === 'this'
            && $expr->name instanceof Identifier
        ) {
            return $this->inferMethod($className, $expr->name->name, false)->getType();
        }

        if ($expr instanceof StaticCall
            && $className !== null
            && $expr->class instanceof Name
            && $expr->name instanceof Identifier
        ) {
            $calledClass = $this->resolveStaticCallClass($expr->class, $className);

            return $this->inferMethod($calledClass, $expr->name->name, false)->getType();
        }

        if ($expr instanceof FuncCall && $expr->name instanceof Name) {
            $functionName = $this->resolveFunctionCallName($expr->name, $context);
            $fileName = $context->getFile();

            if ($fileName !== null) {
                $result = $this->inferFunctionFromFile($functionName, $fileName, false);

                if ($result->getType() !== null) {
                    return $result->getType();
                }
            }

            $result = $this->inferFunction($functionName, false);

            return $result->getType();
        }

        if ($expr instanceof Node\Expr\Array_) {
            $keyTypes = [];
            $valueTypes = [];
            $autoIndex = 0;

            foreach ($expr->items as $item) {
                if ($item->key !== null) {
                    $keyType = $this->initializerExprTypeResolver->getType($item->key, $context);
                    $constantStrings = $keyType->getConstantStrings();

                    if (!$keyType instanceof ConstantIntegerType && count($constantStrings) !== 1) {
                        return null;
                    }

                    if (!$keyType instanceof ConstantIntegerType) {
                        $keyType = $constantStrings[0];
                    }
                } else {
                    $keyType = new ConstantIntegerType($autoIndex++);
                }

                $valueType = $this->evalExpr($item->value, $className, $classReflection, $context);

                if ($valueType === null) {
                    return null;
                }

                $keyTypes[] = $keyType;
                $valueTypes[] = $valueType;
            }

            return new ConstantArrayType($keyTypes, $valueTypes);
        }

        $type = $this->initializerExprTypeResolver->getType($expr, $context);

        if ($type instanceof MixedType) {
            return null;
        }

        return $type;
    }

    private function resolveStaticCallClass(Name $name, string $className): string
    {
        $lowerName = strtolower($name->toString());

        if (in_array($lowerName, ['self', 'static'], true)) {
            return $className;
        }

        return $name->toString();
    }

    private function resolveFunctionCallName(Name $name, InitializerExprContext $context): string
    {
        if ($name->isFullyQualified()) {
            return '\\' . $name->toString();
        }

        $namespace = $context->getNamespace();

        if ($namespace === null) {
            return '\\' . $name->toString();
        }

        $namespacedName = '\\' . $namespace . '\\' . $name->toString();

        if ($this->reflectionProvider->hasFunction(new Name\FullyQualified(ltrim($namespacedName, '\\')), null)) {
            return $namespacedName;
        }

        return '\\' . $name->toString();
    }

    private function hasNativeArrayReturnType(?Node $returnType): bool
    {
        return ($returnType instanceof Name && strtolower($returnType->toString()) === 'array')
            || ($returnType instanceof Identifier && strtolower($returnType->name) === 'array');
    }

    private function hasInferReturnDoc(Node $node): bool
    {
        $doc = $node->getDocComment();
        $docText = $doc !== null ? $doc->getText() : '';

        return $docText !== '' && preg_match('/@(?:phpstan-return|psalm-return|return)\s+array\b[^\r\n*]*@phpstan-infer-return\b/', $docText) === 1;
    }

    /**
     * @param Node[] $fileStmts
     */
    private function findClassMethod(array $fileStmts, ClassReflection $classReflection, string $methodName): ?ClassMethod
    {
        $parts = explode('\\', $classReflection->getName());
        $shortName = $parts[count($parts) - 1];

        return $this->searchClassMethod($fileStmts, $shortName, $methodName);
    }

    /**
     * @param Node[] $stmts
     */
    private function searchClassMethod(array $stmts, string $shortClassName, string $methodName): ?ClassMethod
    {
        foreach ($stmts as $stmt) {
            if ($stmt instanceof Node\Stmt\Namespace_) {
                $found = $this->searchClassMethod($stmt->stmts, $shortClassName, $methodName);

                if ($found !== null) {
                    return $found;
                }

                continue;
            }

            if (!$stmt instanceof Node\Stmt\Class_) {
                continue;
            }

            if ($stmt->name === null || $stmt->name->name !== $shortClassName) {
                continue;
            }

            foreach ($stmt->stmts as $member) {
                if ($member instanceof ClassMethod && $member->name->name === $methodName) {
                    return $member;
                }
            }
        }

        return null;
    }

    /**
     * @param Node[] $fileStmts
     */
    private function findFunction(array $fileStmts, string $functionName): ?Function_
    {
        return $this->searchFunction($fileStmts, ltrim($functionName, '\\'), null);
    }

    /**
     * @param Node[] $stmts
     */
    private function searchFunction(array $stmts, string $functionName, ?string $namespace): ?Function_
    {
        foreach ($stmts as $stmt) {
            if ($stmt instanceof Node\Stmt\Namespace_) {
                $namespaceName = $stmt->name !== null ? $stmt->name->toString() : null;
                $found = $this->searchFunction($stmt->stmts, $functionName, $namespaceName);

                if ($found !== null) {
                    return $found;
                }

                continue;
            }

            if (!$stmt instanceof Function_) {
                continue;
            }

            $candidate = $namespace !== null && $namespace !== ''
                ? $namespace . '\\' . $stmt->name->name
                : $stmt->name->name;

            if (strtolower($candidate) === strtolower($functionName)) {
                return $stmt;
            }
        }

        return null;
    }

    /**
     * @param Node\Stmt[] $stmts
     * @return list<Node\Expr>
     */
    private function collectReturnExprs(array $stmts): array
    {
        $exprs = [];

        foreach ($stmts as $stmt) {
            if ($stmt instanceof Return_) {
                if ($stmt->expr === null) {
                    return [];
                }

                $exprs[] = $stmt->expr;
                continue;
            }

            if ($stmt instanceof Node\Stmt\If_
                || $stmt instanceof Node\Stmt\Switch_
                || $stmt instanceof Node\Stmt\TryCatch
                || $stmt instanceof Node\Stmt\Foreach_
                || $stmt instanceof Node\Stmt\For_
                || $stmt instanceof Node\Stmt\While_
            ) {
                return [];
            }
        }

        return $exprs;
    }
}
