<?php

declare(strict_types=1);

namespace J5ik2o\EventStoreAdapterPhp\Tests;

use J5ik2o\EventStoreAdapterPhp\Aggregate;
use J5ik2o\EventStoreAdapterPhp\AggregateId;

final class UserAccount implements Aggregate {
    private readonly UserAccountId $id;
    private readonly int $sequenceNumber;
    private readonly string $name;
    private readonly int $version;

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
        $aggregate = new UserAccount($id, 1, $name, 1);
        $event = UserAccountEventFactory::ofCreated($id, $name);
        return [$aggregate, $event];
    }

    /**
     * @param array<UserAccountEvent> $events
     * @param UserAccount $snapshot
     * @return UserAccount
     * @throws ReplayException
     */
    public static function replay(array $events, UserAccount $snapshot): UserAccount {
        $aggregate = $snapshot;
        foreach ($events as $event) {
            $aggregate = $aggregate->applyEvent($event);
        }
        return $aggregate;
    }

    /**
     * @throws ReplayException
     */
    public function applyEvent(UserAccountEvent $event): UserAccount {
        if ($event instanceof UserAccountRenamed) {
            try {
                [$aggregate,] = $this->rename($event->getName());
            } catch (AlreadyRenamedException $e) {
                throw new ReplayException(message: "Failed to replay", code: 0, previous: $e);
            }
            return $aggregate;
        } else {
            return $this;
        }
    }

    public function getId(): AggregateId {
        return $this->id;
    }

    public function getSequenceNumber(): int {
        return $this->sequenceNumber;
    }

    public function getName(): string {
        return $this->name;
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
        if ($this->name === $name) {
            throw new AlreadyRenamedException("Failed to rename");
        }
        $aggregate = new UserAccount($this->id, $this->sequenceNumber + 1, $name, $this->version);
        $event = UserAccountEventFactory::ofRenamed($this->id, $this->sequenceNumber + 1, $name);
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
