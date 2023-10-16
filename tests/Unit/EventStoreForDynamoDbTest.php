<?php declare(strict_types=1);

namespace J5ik2o\EventStoreAdapterPhp\Tests;

use Aws\Sdk;
use J5ik2o\EventStoreAdapterPhp\DefaultEventSerializer;
use J5ik2o\EventStoreAdapterPhp\DefaultKeyResolver;
use J5ik2o\EventStoreAdapterPhp\DefaultSnapshotSerializer;
use J5ik2o\EventStoreAdapterPhp\EventStoreForDynamoDb;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class EventStoreForDynamoDbTest extends TestCase {
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
        $aws = new Sdk([
            'endpoint' => 'http://localhost:8000',
            'region' => 'ap-northeast-1',
            'version' => 'latest',
        ]);
        $client = $aws->createDynamoDb();

        $journalTableName = 'journal';
        $journalAidIndexName = 'journal-aid-index';
        DynamoDbUtils::createJournalTable($client, $journalTableName, $journalAidIndexName);

        $snapshotTableName = 'snapshot';
        $snapshotAidIndexName = 'snapshot-aid-index';
        DynamoDbUtils::createSnapshotTable($client, $snapshotTableName, $snapshotAidIndexName);

        $shardCount = 32;
        $eventConverter = function ($eventMap) {
            $typeName = $eventMap["typeName"];
            $id = $eventMap["id"];
            $aggregateIdValue = $eventMap["aggregateId"]["value"];
            $aggregateId = new UserAccountId($aggregateIdValue);
            $sequenceNumber = $eventMap["sequenceNumber"];
            $occurredAtTs = $eventMap["occurredAt"];
            $occurredAt = new \DateTimeImmutable("@{$occurredAtTs}");
            if ($typeName === "user-account-created") {
                $name = $eventMap["name"];
                return new UserAccountCreated($id, $aggregateId, $sequenceNumber, $name, $occurredAt);
            } elseif ($typeName === "user-account-renamed") {
                $name = $eventMap["name"];
                return new UserAccountRenamed($id, $aggregateId, $sequenceNumber, $name, $occurredAt);
            } else {
                return null;
            }
        };
        $snapshotConverter = function ($snapshotMap) {
            $idValue = $snapshotMap["id"]["value"];
            $id = new UserAccountId($idValue);
            $sequenceNumber = $snapshotMap["sequenceNumber"];
            $name = $snapshotMap["name"];
            $version = $snapshotMap["version"];
            return new UserAccount($id, $sequenceNumber, $name, $version);
        };
        $keepSnapshot = true;
        $keepSnapshotCount = 5;
        $deleteTtlInMillSec = 1000;
        $keyResolver = new DefaultKeyResolver();
        $eventSerializer = new DefaultEventSerializer();
        $snapshotSerializer = new DefaultSnapshotSerializer();

        $eventStore = new EventStoreForDynamoDb(
            $client,
            $journalTableName,
            $snapshotTableName,
            $journalAidIndexName,
            $snapshotAidIndexName,
            $shardCount,
            $eventConverter,
            $snapshotConverter,
            $keepSnapshot,
            $keepSnapshotCount,
            $deleteTtlInMillSec,
            $keyResolver,
            $eventSerializer,
            $snapshotSerializer
        );

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
