<?php

namespace J5ik2o\EventStoreAdapterPhp\Tests;

use DateTimeImmutable;
use J5ik2o\EventStoreAdapterPhp\Aggregate;
use J5ik2o\EventStoreAdapterPhp\AggregateId;

final class UserAccount implements Aggregate {
    private UserAccountId $id;
    private int $sequenceNumber;
    private string $name;
    private int $version;

    public function __construct(UserAccountId $id, int $sequenceNumber, string $name, int $version) {
        $this->id = $id;
        $this->sequenceNumber = $sequenceNumber;
        $this->name = $name;
        $this->version = $version;
    }

    public function equals(Aggregate $other): bool {
        if ($other instanceof UserAccount) {
            return $this->id->equals($other->id) && $this->sequenceNumber === $other->sequenceNumber && $this->name === $other->name && $this->version === $other->version;
        } else {
            return false;
        }
    }

    /**
     * @param UserAccountId $id
     * @param string $name
     * @return array{0: UserAccount, 1: UserAccountCreated}
     */
    public static function create(UserAccountId $id, string $name): array {
        $eventId = uniqid("user-account-event-", true);
        $now = new DateTimeImmutable('now');
        $millSec = $now->getTimestamp() * 1000;
        $aggregate = new UserAccount($id, 1, $name, 1);
        $event = new UserAccountCreated($eventId, $id, 1, $name, $millSec);
        return [$aggregate, $event];
    }

    public function getId(): AggregateId {
        return $this->id;
    }

    public function getSequenceNumber(): int {
        return $this->sequenceNumber;
    }

    public function getVersion(): int {
        return $this->version;
    }

    public function withVersion(int $version): Aggregate {
        return new UserAccount($this->id, $this->sequenceNumber, $this->name, $version);
    }

    /**
     * @param string $name
     * @return array{0: UserAccount, 1: UserAccountRenamed}
     * @throws AlreadyRenamedException
     */
    public function rename(string $name): array {
        if ($this->name === $name) throw new AlreadyRenamedException("Failed to rename");
        $eventId = uniqid("user-account-event-", true);
        $now = new DateTimeImmutable('now');
        $millSec = $now->getTimestamp() * 1000;
        $aggregate = new UserAccount($this->id, $this->sequenceNumber, $name, $this->version);
        $event = new UserAccountRenamed($eventId, $this->id, $this->sequenceNumber + 1, $name, $millSec);
        return [$aggregate, $event];
    }

    public function jsonSerialize(): mixed {
        return [
            "id" => $this->id,
            "sequenceNumber" => $this->sequenceNumber,
            "name" => $this->name,
            "version" => $this->version,
        ];
    }
}
