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

    /**
     * @param UserAccountId $id
     * @param string $name
     * @return array
     */
   public static function create(UserAccountId $id, string $name): array {
       $eventId = uniqid("user-account-id-", true);
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
}