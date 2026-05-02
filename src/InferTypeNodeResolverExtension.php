<?php

declare(strict_types=1);

namespace Amiut\PHPStan\TypeUtilities;

use PHPStan\Analyser\NameScope;
use PHPStan\PhpDoc\TypeNodeResolverExtension;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\Type\ErrorType;
use PHPStan\Type\Type;
use function count;
use function in_array;
use function ltrim;
use function strpos;
use function substr;
use function strtolower;
use function trim;

final class InferTypeNodeResolverExtension implements TypeNodeResolverExtension
{
    /**
     * @var ArrayReturnTypeInferer
     */
    private $inferer;

    public function __construct(ArrayReturnTypeInferer $inferer)
    {
        $this->inferer = $inferer;
    }

    public function resolve(TypeNode $typeNode, NameScope $nameScope): ?Type
    {
        if (!$typeNode instanceof GenericTypeNode) {
            return null;
        }

        if (!$this->isReturnTypeName($typeNode->type->name)) {
            return null;
        }

        return $this->resolveReturnType($typeNode, $nameScope);
    }

    private function resolveReturnType(GenericTypeNode $typeNode, NameScope $nameScope): Type
    {
        if (count($typeNode->genericTypes) === 1) {
            $functionArg = $typeNode->genericTypes[0];

            $functionName = $this->typeNodeIdentifier($functionArg);

            if ($functionName === null) {
                return new ErrorType();
            }

            $type = $this->inferer->inferFunction($this->resolveFunctionName($functionName, $nameScope), false)->getType();

            return $type ?? new ErrorType();
        }

        if (count($typeNode->genericTypes) !== 2) {
            return new ErrorType();
        }

        $classArg = $typeNode->genericTypes[0];
        $methodArg = $typeNode->genericTypes[1];

        $classNameArg = $this->typeNodeIdentifier($classArg);
        $methodName = $this->typeNodeIdentifier($methodArg);

        if ($classNameArg === null || $methodName === null) {
            return new ErrorType();
        }

        $className = $this->resolveClassName($classNameArg, $nameScope);

        if ($className === null) {
            return new ErrorType();
        }

        $type = $this->inferer->inferMethod($className, $methodName, false)->getType();

        return $type ?? new ErrorType();
    }

    private function resolveClassName(string $className, NameScope $nameScope): ?string
    {
        if (in_array(strtolower($className), ['self', 'static', '$this'], true)) {
            return $nameScope->getClassNameForTypeAlias() ?? $nameScope->getClassName();
        }

        return $nameScope->resolveStringName($className);
    }

    private function resolveFunctionName(string $functionName, NameScope $nameScope): string
    {
        if ($functionName !== '' && $functionName[0] === '\\') {
            return $functionName;
        }

        if (strpos($functionName, '\\') !== false) {
            return '\\' . ltrim($functionName, '\\');
        }

        $namespace = $nameScope->getNamespace();

        if ($namespace === null) {
            return '\\' . ltrim($functionName, '\\');
        }

        return '\\' . $namespace . '\\' . ltrim($functionName, '\\');
    }

    private function isReturnTypeName(string $name): bool
    {
        $name = ltrim($name, '\\');

        return $name === 'ReturnType'
            || $name === 'Amiut\\PHPStan\\TypeUtilities\\ReturnType';
    }

    private function typeNodeIdentifier(TypeNode $typeNode): ?string
    {
        if ($typeNode instanceof IdentifierTypeNode) {
            return $this->unquoteIdentifier($typeNode->name);
        }

        return $this->unquoteIdentifier((string) $typeNode);
    }

    private function unquoteIdentifier(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (($value[0] === '\'' && substr($value, -1) === '\'')
            || ($value[0] === '"' && substr($value, -1) === '"')) {
            return substr($value, 1, -1);
        }

        return $value;
    }
}
