<?php

declare(strict_types=1);

namespace J5ik2o\EventStoreAdapterPhp\Tests;

use J5ik2o\EventStoreAdapterPhp\EventStore;

final class UserAccountRepository {
    private EventStore $eventStore;

    public function __construct(EventStore $eventStore) {
        $this->eventStore = $eventStore;
    }

    public function storeEvent(UserAccountEvent $event, int $version): void {
        $this->eventStore->persistEvent($event, $version);
    }

    public function storeEventAndSnapshot(UserAccountEvent $event, UserAccount $userAccount): void {
        $this->eventStore->persistEventAndSnapshot($event, $userAccount);
    }

    /**
     * @throws ReplayException
     */
    public function findById(UserAccountId $id): ?UserAccount {
        /** @var ?UserAccount $latestSnapshot */
        $latestSnapshot = $this->eventStore->getLatestSnapshotById($id);
        if ($latestSnapshot === null) {
            return null;
        }
        /** @var array<UserAccountEvent> $events */
        $events = $this->eventStore->getEventsByIdSinceSequenceNumber($id, $latestSnapshot->getSequenceNumber());
        return UserAccount::replay($events, $latestSnapshot);
    }
}
