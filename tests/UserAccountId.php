<?php

namespace J5ik2o\EventStoreAdapterPhp\Tests;

use J5ik2o\EventStoreAdapterPhp\AggregateId;

final class UserAccountId implements AggregateId {

    private string $typeName;
    private string $value;

    public function __construct(?string $value = null) {
        $this->typeName = "user-account";
        if ($value === null) {
            $this->value = uniqid('', true);
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

