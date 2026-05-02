<?php

declare(strict_types=1);

namespace Amiut\PHPStan\TypeUtilities\Tests\Fixtures;

/**
 * @return array @phpstan-infer-return
 */
function sharedSchema(): array
{
    return [
        'shared' => true,
    ];
}

/**
 * @phpstan-type ProductSchema \Amiut\PHPStan\TypeUtilities\ReturnType<self, 'schema'>
 * @phpstan-type SharedSchema \Amiut\PHPStan\TypeUtilities\ReturnType<Amiut\PHPStan\TypeUtilities\Tests\Fixtures\sharedSchema>
 */
final class InferFixture
{
    /**
     * @return array @phpstan-infer-return
     */
    public function schema(): array
    {
        return [
            'type' => 'object',
            'required' => ['id', 'name'],
        ];
    }

    /**
     * @return array @phpstan-infer-return
     */
    public function nested(): array
    {
        return [
            'schema' => $this->schema(),
            'shared' => sharedSchema(),
            'version' => 1,
        ];
    }

    /**
     * @param \Amiut\PHPStan\TypeUtilities\ReturnType<self, 'schema'> $data
     */
    public function processSchema(array $data): void
    {
    }

    /**
     * @param ProductSchema $data
     */
    public function processSchemaAlias(array $data): void
    {
    }

    /**
     * @param SharedSchema $data
     */
    public function processSharedAlias(array $data): void
    {
    }
}
