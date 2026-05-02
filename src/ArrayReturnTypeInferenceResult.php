<?php

declare(strict_types=1);

namespace Amiut\PHPStan\TypeUtilities;

use PHPStan\Type\Type;

final class ArrayReturnTypeInferenceResult
{
    /**
     * @var Type|null
     */
    private $type;

    /**
     * @var string|null
     */
    private $failureReason;

    private function __construct(?Type $type, ?string $failureReason)
    {
        $this->type = $type;
        $this->failureReason = $failureReason;
    }

    public static function success(Type $type): self
    {
        return new self($type, null);
    }

    public static function failure(string $reason): self
    {
        return new self(null, $reason);
    }

    public function getType(): ?Type
    {
        return $this->type;
    }

    public function getFailureReason(): ?string
    {
        return $this->failureReason;
    }
}
