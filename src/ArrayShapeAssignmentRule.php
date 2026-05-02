<?php

declare(strict_types=1);

namespace Amiut\PHPStan\TypeUtilities;

use PhpParser\Node;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Assign;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\VerbosityLevel;
use function sprintf;

/**
 * @implements Rule<Assign>
 */
final class ArrayShapeAssignmentRule implements Rule
{
    public function getNodeType(): string
    {
        return Assign::class;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->var instanceof ArrayDimFetch || $node->var->dim === null) {
            return [];
        }

        $arrayType = $scope->getType($node->var->var);
        $constantArrays = $arrayType->getConstantArrays();

        if ($constantArrays === []) {
            return [];
        }

        $offsetType = $scope->getType($node->var->dim);
        $shape = $constantArrays[0];

        if ($shape->hasOffsetValueType($offsetType)->no()) {
            return [];
        }

        $expectedType = $shape->getOffsetValueType($offsetType);
        $assignedType = $scope->getType($node->expr);

        if (!$expectedType->accepts($assignedType, true)->no()) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf(
                'Assigned value type %s does not match inferred array shape offset type %s.',
                $assignedType->describe(VerbosityLevel::precise()),
                $expectedType->describe(VerbosityLevel::precise())
            ))
                ->identifier('arrayTypeInference.assignmentType')
                ->line($node->getLine())
                ->build(),
        ];
    }
}
