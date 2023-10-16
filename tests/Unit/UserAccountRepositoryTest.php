<?php

namespace J5ik2o\EventStoreAdapterPhp\Tests\Unit;

use Aws\Sdk;
use J5ik2o\EventStoreAdapterPhp\DefaultEventSerializer;
use J5ik2o\EventStoreAdapterPhp\DefaultKeyResolver;
use J5ik2o\EventStoreAdapterPhp\DefaultSnapshotSerializer;
use J5ik2o\EventStoreAdapterPhp\Internal\EventStoreForDynamoDb;
use J5ik2o\EventStoreAdapterPhp\Tests\AlreadyRenamedException;
use J5ik2o\EventStoreAdapterPhp\Tests\DynamoDbUtils;
use J5ik2o\EventStoreAdapterPhp\Tests\ReplayException;
use J5ik2o\EventStoreAdapterPhp\Tests\UserAccount;
use J5ik2o\EventStoreAdapterPhp\Tests\UserAccountCreated;
use J5ik2o\EventStoreAdapterPhp\Tests\UserAccountId;
use J5ik2o\EventStoreAdapterPhp\Tests\UserAccountRenamed;
use J5ik2o\EventStoreAdapterPhp\Tests\UserAccountRepository;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class UserAccountRepositoryTest extends TestCase {
    public function setUp(): void {
        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            throw new RuntimeException($errstr . " on line " . $errline . " in file " . $errfile);
        });
    }

    public function tearDown(): void {
        restore_error_handler();
    }

    /**
     * @throws ReplayException
     * @throws AlreadyRenamedException
     */
    public function testPersist(): void {
        $aws = new Sdk([
            'endpoint' => 'http://localhost:8000',
            'region' => 'ap-northeast-1',
            'version' => 'latest',
            'credentials' => [
                'key' => 'x',
                'secret' => 'x',
            ],
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

        $userAccountRepository = new UserAccountRepository($eventStore);

        $userAccountId = new UserAccountId();
        // Generates a new aggregate
        $name1 = "test-1";
        [$userAccount1, $created] = UserAccount::create($userAccountId, $name1);
        // Store the snapshot and event at first
        $userAccountRepository->storeEventAndSnapshot($created, $userAccount1);

        // Replay if necessary
        $userAccount2 = $userAccountRepository->findById($userAccountId);
        if ($userAccount2 === null) {
            $this->fail();
        }
        $this->assertEquals(expected: $userAccountId, actual: $userAccount2->getId());
        $this->assertEquals(expected: 1, actual: $userAccount2->getSequenceNumber());
        $this->assertEquals(expected: $name1, actual: $userAccount2->getName());
        $this->assertEquals(expected: 1, actual: $userAccount2->getVersion());

        $this->assertTrue($created->isCreated());
        $this->assertEquals(expected: $userAccountId, actual: $created->getAggregateId());
        $this->assertEquals(expected: 1, actual: $created->getSequenceNumber());
        $this->assertEquals(expected: $name1, actual: $created->getName());

        // Executes business logic
        $name2 = "test-2";
        [$userAccount3, $renamed] = $userAccount2->rename($name2);
        // Store the event only
        $userAccountRepository->storeEvent($renamed, $userAccount3->getVersion());
        // Replay if necessary
        $userAccount4 = $userAccountRepository->findById($userAccountId);
        if ($userAccount4 === null) {
            $this->fail();
        }
        $this->assertEquals(expected: $userAccountId, actual: $userAccount4->getId());
        $this->assertEquals(expected: 2, actual: $userAccount4->getSequenceNumber());
        $this->assertEquals(expected: $name2, actual: $userAccount4->getName());
        $this->assertEquals(expected: 2, actual: $userAccount4->getVersion());

        $this->assertFalse($renamed->isCreated());
        $this->assertEquals(expected: $userAccountId, actual: $renamed->getAggregateId());
        $this->assertEquals(expected: 2, actual: $renamed->getSequenceNumber());
        $this->assertEquals(expected: $name2, actual: $renamed->getName());

    }
}
