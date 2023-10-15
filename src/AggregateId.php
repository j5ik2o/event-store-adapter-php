<?php declare(strict_types=1);

namespace J5ik2o\EventStoreAdapterPhp;

interface AggregateId {
    public function getTypeName(): string;

    public function getValue(): string;

    public function asString(): string;
}