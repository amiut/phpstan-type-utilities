<?php

declare(strict_types=1);

namespace Amiut\PHPStan\TypeUtilities;

use PhpParser\Node\Name\FullyQualified;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\Type;
use function ltrim;

final class CallableReturnTypeResolver
{
    /**
     * @var ArrayReturnTypeInferer
     */
    private $arrayReturnTypeInferer;

    /**
     * @var ReflectionProvider
     */
    private $reflectionProvider;

    public function __construct(ArrayReturnTypeInferer $arrayReturnTypeInferer, ReflectionProvider $reflectionProvider)
    {
        $this->arrayReturnTypeInferer = $arrayReturnTypeInferer;
        $this->reflectionProvider = $reflectionProvider;
    }

    public function resolveFunctionReturnType(string $functionName): ?Type
    {
        $type = $this->arrayReturnTypeInferer->inferFunction($functionName, false)->getType();

        if ($type !== null) {
            return $type;
        }

        $nameNode = new FullyQualified(ltrim($functionName, '\\'));

        if (!$this->reflectionProvider->hasFunction($nameNode, null)) {
            return null;
        }

        return $this->reflectionProvider->getFunction($nameNode, null)->getOnlyVariant()->getReturnType();
    }

    public function resolveMethodReturnType(string $className, string $methodName): ?Type
    {
        $type = $this->arrayReturnTypeInferer->inferMethod($className, $methodName, false)->getType();

        if ($type !== null) {
            return $type;
        }

        $className = ltrim($className, '\\');

        if (!$this->reflectionProvider->hasClass($className)) {
            return null;
        }

        $classReflection = $this->reflectionProvider->getClass($className);

        if (!$classReflection->hasNativeMethod($methodName)) {
            return null;
        }

        $variants = $classReflection->getNativeMethod($methodName)->getVariants();

        return $variants !== [] ? $variants[0]->getReturnType() : null;
    }
}
