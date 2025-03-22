<?php

declare(strict_types=1);

namespace J5ik2o\EventStoreAdapterPhp\Tests;

use J5ik2o\EventStoreAdapterPhp\EventStoreFactory;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class EventStoreInMemoryTest extends TestCase {
    public function setUp(): void {
        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            throw new RuntimeException($errstr . " on line " . $errline . " in file " . $errfile);
        });
    }

    public function tearDown(): void {
        restore_error_handler();
    }

    /**
     * @throws \Exception
     */
    public function testPersist(): void {
        $eventStore = EventStoreFactory::createInMemory()->withKeepSnapshot(true);

        $userAccountId = new UserAccountId();
        [$userAccount, $event] = UserAccount::create($userAccountId, "test");
        $eventStore->persistEventAndSnapshot($event, $userAccount);

        $result = $eventStore->getLatestSnapshotById($userAccountId);
        $userAccountResult = null;
        if ($result instanceof UserAccount) {
            $userAccountResult = $result;
        }

        if ($userAccount instanceof UserAccount && $userAccountResult instanceof UserAccount) {
            $this->assertTrue($userAccount->getId()->equals($userAccountResult->getId()));
            $this->assertTrue($userAccount->equals($userAccountResult), "object");
        } else {
            $this->fail();
        }

        [$userAccount2, $event2] = $userAccountResult->rename("test-2");

        $eventStore->persistEvent($event2, $userAccount2->getVersion());

        $snapshot2 = $eventStore->getLatestSnapshotById($userAccountId);
        if ($snapshot2 instanceof UserAccount) {
            /** @var array<UserAccountEvent> $events */
            $events = $eventStore->getEventsByIdSinceSequenceNumber($userAccountId, $snapshot2->getSequenceNumber());
            $aggregate2 = UserAccount::replay($events, $snapshot2);
            $this->assertTrue($aggregate2->getName() === "test-2");
        }
    }


}
