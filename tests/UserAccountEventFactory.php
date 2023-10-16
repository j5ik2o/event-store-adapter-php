<?php declare(strict_types=1);

namespace J5ik2o\EventStoreAdapterPhp\Tests;

use Ulid\Ulid;

final class UserAccountEventFactory {
    public static function ofCreated(UserAccountId $id, string $name): UserAccountCreated{
        $eventId = "user-account-event-" . Ulid::generate();
        return new UserAccountCreated($eventId, $id, 1, $name, new \DateTimeImmutable('now'));
    }

    public static function ofRenamed(UserAccountId $id, int $sequenceNumber, string $name): UserAccountRenamed {
        $eventId = "user-account-event-" . Ulid::generate();
        return new UserAccountRenamed($eventId, $id, $sequenceNumber, $name, new \DateTimeImmutable('now'));
    }
}