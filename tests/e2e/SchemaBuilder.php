<?php

declare(strict_types=1);

namespace Amiut\PHPStan\TypeUtilities\Tests\E2E;

/**
 * @phpstan-type ItemSchema \ReturnType<self, 'itemSchema'>
 * @phpstan-type GroupSchema \ReturnType<self, 'groupSchema'>
 * @phpstan-type SharedDefaults \ReturnType<\Amiut\PHPStan\TypeUtilities\Tests\E2E\sharedDefaults>
 */
class SchemaBuilder
{
    /**
     * @return array @phpstan-infer-return
     */
    public function groupSchema(): array
    {
        return [
            'type' => 'object',
            'defaults' => sharedDefaults(),
            'required' => [
                'id',
                'key',
                'label',
                'visible',
                'items',
            ],
            'properties' => [
                'id' => [
                    'type' => 'string',
                    'minLength' => 1,
                    'pattern' => '^[a-z0-9_-]+$',
                ],
                'key' => [
                    'type' => 'string',
                    'minLength' => 1,
                    'pattern' => '^[a-z0-9_-]+$',
                ],
                'label' => [
                    'type' => 'string',
                    'minLength' => 1,
                ],
                'description' => [
                    'type' => 'string',
                    'default' => '',
                ],
                'visible' => [
                    'type' => 'boolean',
                    'default' => true,
                ],
                'items' => [
                    'type' => 'array',
                    'items' => $this->itemSchema(),
                ],
            ],
        ];
    }

    /**
     * @return array @phpstan-infer-return
     */
    public function itemSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => [
                'type',
                'term_id',
                'key',
            ],
            'properties' => [
                'type' => [
                    'type' => 'string',
                    'enum' => [
                        'specification',
                    ],
                ],
                'term_id' => [
                    'type' => 'integer',
                    'minimum' => 1,
                ],
                'key' => [
                    'type' => 'string',
                    'minLength' => 1,
                    'pattern' => '^[a-z0-9_-]+$',
                ],
            ],
        ];
    }

    // Class-level @phpstan-type alias — valid key access, no error expected
    /**
     * @param ItemSchema $item
     */
    public function readItemType(array $item): string
    {
        return $item['type'];
    }

    // Inline \ReturnType<self, 'method'> in @param — valid key access, no error expected
    /**
     * @param \ReturnType<self, 'groupSchema'> $group
     */
    public function readGroupId(array $group): string
    {
        return $group['id'];
    }

    /**
     * @param SharedDefaults $defaults
     */
    public function readSharedDefaults(array $defaults): bool
    {
        return $defaults['additionalProperties'];
    }
}
