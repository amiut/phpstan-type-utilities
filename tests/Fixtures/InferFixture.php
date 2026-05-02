<?php

declare(strict_types=1);

namespace Amiut\PHPStan\TypeUtilities\Tests\Fixtures;

/**
 * @return array @phpstan-infer-return
 */
function sharedOptions(): array
{
    return [
        'shared' => true,
    ];
}

function sharedLimit(): int
{
    return 100;
}

/**
 * @return array{enabled: bool, limit: int}
 */
function documentedOptions(): array
{
    return [
        'enabled' => true,
        'limit' => 100,
    ];
}

final class InferValue
{
}

/**
 * @phpstan-type OptionsShape \Amiut\PHPStan\TypeUtilities\ReturnType<self, 'options'>
 * @phpstan-type SharedOptionsShape \Amiut\PHPStan\TypeUtilities\ReturnType<Amiut\PHPStan\TypeUtilities\Tests\Fixtures\sharedOptions>
 */
final class InferFixture
{
    /**
     * @return array @phpstan-infer-return
     */
    public function options(): array
    {
        return [
            'enabled' => true,
            'limit' => 100,
            'labels' => ['default', 'featured'],
        ];
    }

    /**
     * @return array @phpstan-infer-return
     */
    public function nested(): array
    {
        return [
            'options' => $this->options(),
            'shared' => sharedOptions(),
            'version' => 1,
        ];
    }

    /**
     * @param \Amiut\PHPStan\TypeUtilities\ReturnType<self, 'options'> $data
     */
    public function processOptions(array $data): void
    {
    }

    /**
     * @param OptionsShape $data
     */
    public function processOptionsAlias(array $data): void
    {
    }

    /**
     * @param SharedOptionsShape $data
     */
    public function processSharedAlias(array $data): void
    {
    }

    public function count(): int
    {
        return 5;
    }

    public function value(): InferValue
    {
        return new InferValue();
    }

    /**
     * @return array{name: string, count: int}
     */
    public function documentedShape(): array
    {
        return [
            'name' => 'default',
            'count' => 5,
        ];
    }
}
