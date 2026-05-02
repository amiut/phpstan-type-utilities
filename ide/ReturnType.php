<?php

declare(strict_types=1);

namespace Amiut\PHPStan\TypeUtilities;

/**
 * IDE-only marker for PHPDoc inferred return references.
 *
 * This class is intentionally outside Composer autoloaded source. Editors can
 * resolve it, while PHPStan still lets the TypeNodeResolverExtension handle
 * the custom type.
 *
 * @template TCallable
 * @template TMethod of string
 * @implements \ArrayAccess<array-key, mixed>
 */
final class ReturnType implements \ArrayAccess
{
    /**
     * @param array-key $offset
     */
    public function offsetExists($offset): bool
    {
        return false;
    }

    /**
     * @param array-key $offset
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return null;
    }

    /**
     * @param array-key $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value): void
    {
    }

    /**
     * @param array-key $offset
     */
    public function offsetUnset($offset): void
    {
    }
}
