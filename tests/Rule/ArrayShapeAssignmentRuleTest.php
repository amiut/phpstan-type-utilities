<?php

declare(strict_types=1);

namespace Amiut\PHPStan\TypeUtilities\Tests\Rule;

use Amiut\PHPStan\TypeUtilities\ArrayShapeAssignmentRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<ArrayShapeAssignmentRule>
 */
final class ArrayShapeAssignmentRuleTest extends RuleTestCase
{
    /**
     * @return list<string>
     */
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/../../extension.neon'];
    }

    protected function getRule(): Rule
    {
        return self::getContainer()->getByType(ArrayShapeAssignmentRule::class);
    }

    public function testReportsAssignmentsThatViolateInferredShapeOffsets(): void
    {
        $this->analyse([__DIR__ . '/../Fixtures/AssignmentFixture.php'], [
            [
                "Assigned value type 'a' does not match inferred array shape offset type int.",
                29,
            ],
            [
                "Assigned value type 123 does not match inferred array shape offset type string.",
                37,
            ],
            [
                "Assigned value type 'yes' does not match inferred array shape offset type bool.",
                45,
            ],
        ]);
    }

    public function testReturnTypeCanConsumeInferredFunctionReturnTypeForAssignments(): void
    {
        $this->analyse([__DIR__ . '/../Fixtures/FunctionReturnTypeCompositionFixture.php'], [
            [
                "Assigned value type 'yes' does not match inferred array shape offset type bool.",
                39,
            ],
            [
                "Assigned value type 'three' does not match inferred array shape offset type int.",
                47,
            ],
            [
                "Assigned value type false does not match inferred array shape offset type string.",
                55,
            ],
        ]);
    }
}
