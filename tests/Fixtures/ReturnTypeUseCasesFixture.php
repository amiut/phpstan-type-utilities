<?php

declare(strict_types=1);

namespace Amiut\PHPStan\TypeUtilities\Tests\Fixtures;

/**
 * @return array @phpstan-infer-return
 */
function isolatedInferredOptions(): array
{
    return [
        'enabled' => true,
        'limit' => 10,
        'label' => 'Default',
    ];
}

function isolatedNativeCount(): int
{
    return 10;
}

/**
 * @return array{enabled: bool, limit: int, label: string}
 */
function isolatedDocumentedOptions(): array
{
    return [
        'enabled' => true,
        'limit' => 10,
        'label' => 'Default',
    ];
}

final class IsolatedValue
{
}

final class ReturnTypeUseCasesFixture
{
    /**
     * @return array @phpstan-infer-return
     */
    public function inferredOptions(): array
    {
        return [
            'enabled' => true,
            'limit' => 10,
            'label' => 'Default',
        ];
    }

    public function nativeCount(): int
    {
        return 10;
    }

    public function nativeValue(): IsolatedValue
    {
        return new IsolatedValue();
    }

    /**
     * @return array{enabled: bool, limit: int, label: string}
     */
    public function documentedOptions(): array
    {
        return [
            'enabled' => true,
            'limit' => 10,
            'label' => 'Default',
        ];
    }
}
