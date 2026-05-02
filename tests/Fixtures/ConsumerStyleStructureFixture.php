<?php

declare(strict_types=1);

namespace Amiut\PHPStan\TypeUtilities\Tests\Fixtures;

/**
 * @phpstan-type DataShape \ReturnType<self, 'default'>
 * @phpstan-type SchemaArray \ReturnType<self, 'schema'>
 */
final class ConsumerStyleStructureFixture
{
    public const CURRENT_VERSION = 1;

    /** @return array @phpstan-infer-return */
    public function default(): array
    {
        return [
            'schema_version' => self::CURRENT_VERSION,
            'groups' => [],
        ];
    }

    /**
     * @param array $data
     * @return array
     * @phpstan-param DataShape $data
     * @phpstan-return DataShape
     */
    public function modifyData(array $data): array
    {
        $default = $this->default();
        \PHPStan\dumpType($default);

        $schema = $this->schema();
        \PHPStan\dumpType($schema);
        $schema['schema_version'] = false;

        \PHPStan\dumpType($data);
        $data['schema_version'] = 'a';

        return $data;
    }

    /** @return array @phpstan-infer-return */
    public function schema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => [
                'schema_version',
                'groups',
            ],
            'properties' => [
                'schema_version' => [
                    'type' => 'integer',
                    'minimum' => self::CURRENT_VERSION,
                    'maximum' => self::CURRENT_VERSION,
                ],
                'groups' => [
                    'type' => 'array',
                    'items' => $this->groupSchema(),
                ],
            ],
        ];
    }

    /** @return array @phpstan-infer-return */
    private function groupSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => [
                'id',
                'label',
            ],
            'properties' => [
                'id' => [
                    'type' => 'string',
                    'minLength' => 1,
                ],
                'label' => [
                    'type' => 'string',
                    'minLength' => 1,
                ],
            ],
        ];
    }
}
