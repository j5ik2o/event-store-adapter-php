<?php declare(strict_types=1);

namespace J5ik2o\EventStoreAdapterPhp\Tests;

use Ulid\Ulid;
use J5ik2o\EventStoreAdapterPhp\AggregateId;

final class UserAccountId implements AggregateId {

    private readonly string $typeName;
    private readonly string $value;

    public function __construct(?string $value = null) {
        $this->typeName = "user-account";
        if ($value === null) {
            $this->value = (string)Ulid::generate();
        } else {
            $this->value = $value;
        }
    }

    public function getTypeName(): string {
        return $this->typeName;
    }

    public function getValue(): string {
        return $this->value;
    }

    public function asString(): string {
        return sprintf("%s-%s", $this->typeName, $this->value);
    }

    public function jsonSerialize(): mixed {
        return [
            "typeName" => $this->typeName,
            "value" => $this->value,
        ];
    }

    public function equals(AggregateId $other): bool {
        if ($other instanceof UserAccountId) {
            return $this->value === $other->value;
        } else {
            return false;
        }
    }
}

