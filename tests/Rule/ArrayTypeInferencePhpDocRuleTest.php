<?php

declare(strict_types=1);

namespace Amiut\PHPStan\TypeUtilities\Tests\Rule;

use Amiut\PHPStan\TypeUtilities\ArrayTypeInferencePhpDocRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<ArrayTypeInferencePhpDocRule>
 */
final class ArrayTypeInferencePhpDocRuleTest extends RuleTestCase
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
        return self::getContainer()->getByType(ArrayTypeInferencePhpDocRule::class);
    }

    public function testReportsFailedInferenceAndUnresolvedReturnTypes(): void
    {
        $this->analyse([__DIR__ . '/../fixtures/PhpDocFailuresFixture.php'], [
            [
                'ReturnType<missingFunction> could not be resolved: Function \Amiut\PHPStan\TypeUtilities\Tests\Fixtures\missingFunction() was not found.',
                11,
            ],
            [
                'ReturnType<self, \'missingSchema\'> could not be resolved: Method Amiut\PHPStan\TypeUtilities\Tests\Fixtures\PhpDocFailuresFixture::missingSchema() was not found.',
                11,
            ],
            [
                'Method Amiut\PHPStan\TypeUtilities\Tests\Fixtures\PhpDocFailuresFixture::dynamicSchema() return type is marked @phpstan-infer-return, but the static array type could not be inferred: Method Amiut\PHPStan\TypeUtilities\Tests\Fixtures\PhpDocFailuresFixture::dynamicSchema() return type could not be inferred from static array expressions.',
                16,
            ],
        ]);
    }
}
