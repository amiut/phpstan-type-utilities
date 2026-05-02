<?php

declare(strict_types=1);

namespace Amiut\PHPStan\TypeUtilities\Tests\Fixtures;

/**
 * @phpstan-type MissingMethod \Amiut\PHPStan\TypeUtilities\ReturnType<self, 'missingOptions'>
 * @phpstan-type MissingFunction \Amiut\PHPStan\TypeUtilities\ReturnType<missingFunction>
 */
final class PhpDocFailuresFixture
{
    /**
     * @return array @phpstan-infer-return
     */
    public function dynamicOptions(): array
    {
        $key = 'enabled';

        return [
            $key => true,
        ];
    }
}
