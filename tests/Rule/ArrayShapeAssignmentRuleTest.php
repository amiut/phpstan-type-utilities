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
                "Assigned value type 'a' does not match inferred array shape offset type 1.",
                29,
            ],
            [
                "Assigned value type 123 does not match inferred array shape offset type 'default'.",
                37,
            ],
            [
                "Assigned value type 'yes' does not match inferred array shape offset type false.",
                45,
            ],
        ]);
    }
}
