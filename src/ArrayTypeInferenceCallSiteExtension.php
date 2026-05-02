<?php

declare(strict_types=1);

namespace Amiut\PHPStan\TypeUtilities;

use PhpParser\Node\Expr;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Type\ExpressionTypeResolverExtension;
use PHPStan\Type\Type;
use function in_array;
use function is_string;
use function ltrim;
use function strtolower;

final class ArrayTypeInferenceCallSiteExtension implements ExpressionTypeResolverExtension
{
    /**
     * @var ArrayReturnTypeInferer
     */
    private $inferer;

    public function __construct(ArrayReturnTypeInferer $inferer)
    {
        $this->inferer = $inferer;
    }

    public function getType(Expr $expr, Scope $scope): ?Type
    {
        if ($expr instanceof MethodCall
            && $scope->isInClass()
            && $expr->var instanceof Variable
            && is_string($expr->var->name)
            && $expr->var->name === 'this'
            && $expr->name instanceof Identifier
        ) {
            return $this->inferer->inferMethod($scope->getClassReflection()->getName(), $expr->name->name, false)->getType();
        }

        if ($expr instanceof StaticCall
            && $expr->class instanceof Name
            && $expr->name instanceof Identifier
        ) {
            $className = $this->resolveStaticClass($expr->class, $scope);

            if ($className === null) {
                return null;
            }

            return $this->inferer->inferMethod($className, $expr->name->name, false)->getType();
        }

        if ($expr instanceof FuncCall && $expr->name instanceof Name) {
            $functionName = $this->resolveFunctionName($expr->name, $scope);

            return $this->inferer->inferFunctionFromFile($functionName, $scope->getFile(), false)->getType()
                ?? $this->inferer->inferFunction($functionName, false)->getType();
        }

        return null;
    }

    private function resolveStaticClass(Name $name, Scope $scope): ?string
    {
        $className = $name->toString();

        if (in_array(strtolower($className), ['self', 'static'], true)) {
            return $scope->isInClass() ? $scope->getClassReflection()->getName() : null;
        }

        return $scope->resolveName($name);
    }

    private function resolveFunctionName(Name $name, Scope $scope): string
    {
        if ($name->isFullyQualified()) {
            return '\\' . $name->toString();
        }

        $namespace = $scope->getNamespace();

        if ($namespace === null) {
            return '\\' . ltrim($name->toString(), '\\');
        }

        return '\\' . $namespace . '\\' . ltrim($name->toString(), '\\');
    }
}
