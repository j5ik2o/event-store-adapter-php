<?php

namespace J5ik2o\EventStoreAdapterPhp\Tests;

final class UserAccountCreated implements UserAccountEvent {

    private string $typeName;
    private string $id;

    private UserAccountId $aggregateId;
    private int $sequenceNumber;

    private string $name;

    private int $occurredAt;

    public function __construct(string $id, UserAccountId $aggregateId, int $sequenceNumber, string $name, int $occurredAt) {
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

    public function getOccurredAt(): int {
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
            "occurredAt" => $this->occurredAt,
        ];
    }
}