<?php

declare(strict_types=1);

namespace Amiut\PHPStan\TypeUtilities;

use PHPStan\Type\Type;

final class InferredReturnTypeCache
{
    /**
     * @var array<string, Type>
     */
    private $cache = [];

    public function store(string $key, Type $type): void
    {
        $this->cache[$key] = $type;
    }

    public function get(string $key): ?Type
    {
        return $this->cache[$key] ?? null;
    }
}
