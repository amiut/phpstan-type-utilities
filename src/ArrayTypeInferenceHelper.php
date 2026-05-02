<?php

declare(strict_types=1);

namespace Amiut\PHPStan\TypeUtilities;

use PHPStan\Type\MixedType;
use PHPStan\Type\Type;
use PHPStan\Type\UnionType;

final class ArrayTypeInferenceHelper
{
    public function isPlainArrayType(Type $type): bool
    {
        $arrays = $type->getArrays();

        if ($arrays === []) {
            return false;
        }

        return $type->getConstantArrays() === []
            && $this->containsMixed($arrays[0]->getIterableValueType());
    }

    public function hasUsefulArrayValueType(Type $type): bool
    {
        if ($type->getConstantArrays() !== []) {
            return true;
        }

        $arrays = $type->getArrays();

        if ($arrays !== []) {
            return ! $this->containsMixed($arrays[0]->getIterableValueType());
        }

        if ($type instanceof UnionType) {
            foreach ($type->getTypes() as $inner) {
                if (! $this->hasUsefulArrayValueType($inner)) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }

    private function containsMixed(Type $type): bool
    {
        if ($type instanceof MixedType) {
            return true;
        }

        if ($type instanceof UnionType) {
            foreach ($type->getTypes() as $inner) {
                if ($this->containsMixed($inner)) {
                    return true;
                }
            }
        }

        return false;
    }
}
