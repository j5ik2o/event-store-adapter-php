<?php

declare(strict_types=1);

namespace J5ik2o\EventStoreAdapterPhp\Tests;

use DateTimeImmutable;

final class UserAccountCreated implements UserAccountEvent {
    private readonly string $typeName;
    private readonly string $id;

    private readonly UserAccountId $aggregateId;
    private readonly int $sequenceNumber;

    private readonly string $name;

    private readonly DateTimeImmutable $occurredAt;

    public function __construct(string $id, UserAccountId $aggregateId, int $sequenceNumber, string $name, DateTimeImmutable $occurredAt) {
        $this->typeName = "user-account-created";
        $this->id = $id;
        $this->aggregateId = $aggregateId;
        $this->sequenceNumber = $sequenceNumber;
        $this->name = $name;
        $this->occurredAt = $occurredAt;
    }

    public function getId(): string {
        return $this->id;
    }

    public function getTypeName(): string {
        return $this->typeName;
    }

    public function getAggregateId(): UserAccountId {
        return $this->aggregateId;
    }

    public function getSequenceNumber(): int {
        return $this->sequenceNumber;
    }

    public function getName(): string {
        return $this->name;
    }

    public function getOccurredAt(): DateTimeImmutable {
        return $this->occurredAt;
    }

    public function isCreated(): bool {
        return true;
    }

    public function jsonSerialize(): mixed {
        return [
            "typeName" => $this->typeName,
            "id" => $this->id,
            "aggregateId" => $this->aggregateId,
            "sequenceNumber" => $this->sequenceNumber,
            "name" => $this->name,
            "occurredAt" => $this->occurredAt->getTimestamp() * 1000,
        ];
    }
}
