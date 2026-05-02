<?php

declare(strict_types=1);

namespace Amiut\PHPStan\TypeUtilities;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PHPStan\Analyser\Error;
use PHPStan\Analyser\IgnoreErrorExtension;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassMethodNode;
use PHPStan\Node\InFunctionNode;
use function get_class;
use function preg_match;

final class ArrayTypeInferenceIgnoreExtension implements IgnoreErrorExtension
{
    public function shouldIgnore(Error $error, Node $node, Scope $scope): bool
    {
        $doc = $node->getDocComment();
        $docText = $doc !== null ? $doc->getText() : '';

        if ($error->getIdentifier() !== 'missingType.iterableValue'
            && $error->getIdentifier() !== 'return.unresolvableType') {
            return false;
        }

        $nodeClass = get_class($node);

        if ($nodeClass !== InClassMethodNode::class
            && $nodeClass !== InFunctionNode::class
            && !$node instanceof ClassMethod
            && !$node instanceof Function_) {
            return false;
        }

        return $docText !== '' && preg_match('/@(?:phpstan-return|psalm-return|return)\s+array\b[^\r\n*]*@phpstan-infer-return\b/', $docText) === 1;
    }
}
