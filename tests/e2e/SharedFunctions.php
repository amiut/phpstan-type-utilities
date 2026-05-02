<?php

declare(strict_types=1);

namespace Amiut\PHPStan\TypeUtilities\Tests\E2E;

/**
 * @return array @phpstan-infer-return
 */
function sharedFlags(): array
{
    return [
        'readonly' => false,
    ];
}
