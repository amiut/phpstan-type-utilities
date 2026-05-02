<?php

declare(strict_types=1);

namespace Amiut\PHPStan\TypeUtilities\Tests\E2E;

/**
 * @phpstan-type ItemConfig \ReturnType<self, 'itemConfig'>
 * @phpstan-type GroupConfig \ReturnType<self, 'groupConfig'>
 * @phpstan-type SharedFlags \ReturnType<\Amiut\PHPStan\TypeUtilities\Tests\E2E\sharedFlags>
 */
class ConfigBuilder
{
    /**
     * @return array @phpstan-infer-return
     */
    public function groupConfig(): array
    {
        return [
            'id' => 'primary',
            'key' => 'main',
            'label' => 'Main',
            'visible' => true,
            'flags' => sharedFlags(),
            'items' => [
                'id' => [
                    'type' => 'text',
                    'label' => 'Identifier',
                ],
                'key' => [
                    'type' => 'text',
                    'label' => 'Key',
                ],
                'status' => [
                    'type' => 'select',
                    'options' => $this->itemConfig(),
                ],
            ],
        ];
    }

    /**
     * @return array @phpstan-infer-return
     */
    public function itemConfig(): array
    {
        return [
            'draft' => [
                'label' => 'Draft',
                'enabled' => true,
            ],
            'published' => [
                'label' => 'Published',
                'enabled' => true,
            ],
        ];
    }

    /**
     * @param ItemConfig $item
     */
    public function readItemLabel(array $item): string
    {
        return $item['draft']['label'];
    }

    /**
     * @param \ReturnType<self, 'groupConfig'> $group
     */
    public function readGroupId(array $group): string
    {
        return $group['id'];
    }

    /**
     * @param SharedFlags $flags
     */
    public function readSharedFlags(array $flags): bool
    {
        return $flags['readonly'];
    }

    public function count(): int
    {
        return 5;
    }

    /**
     * @phpstan-param \ReturnType<self, 'count'> $count
     */
    public function readCount(int $count): int
    {
        return $count;
    }

    public function readSharedLabel(): string
    {
        return sharedLabel('ready');
    }
}
