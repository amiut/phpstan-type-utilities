<?php

declare(strict_types=1);

namespace Amiut\PHPStan\TypeUtilities\Tests\Fixtures;

/**
 * @phpstan-type DataShape \Amiut\PHPStan\TypeUtilities\ReturnType<self, 'config'>
 */
final class AssignmentFixture
{
    /**
     * @return array @phpstan-infer-return
     */
    public function config(): array
    {
        return [
            'count'   => 1,
            'label'   => 'default',
            'enabled' => false,
        ];
    }

    /**
     * @param DataShape $data
     */
    public function invalidIntegerAssignment(array $data): void
    {
        $data['count'] = 'a';
    }

    /**
     * @param DataShape $data
     */
    public function invalidStringAssignment(array $data): void
    {
        $data['label'] = 123;
    }

    /**
     * @param DataShape $data
     */
    public function invalidBooleanAssignment(array $data): void
    {
        $data['enabled'] = 'yes';
    }

    /**
     * @param DataShape $data
     */
    public function validAssignments(array $data): void
    {
        $data['count']   = 1;
        $data['label']   = 'default';
        $data['enabled'] = false;
    }
}
