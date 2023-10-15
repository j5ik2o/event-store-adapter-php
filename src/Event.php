<?php declare(strict_types=1);

namespace J5ik2o\EventStoreAdapterPhp;

interface Event {
    public function getId(): string;

    public function getTypeName(): string;

    public function getAggregateId(): AggregateId;

    public function getSequenceNumber(): int;

    public function isCreated(): bool;

    public function getOccurredAt(): int;
}