<?php

declare(strict_types=1);

namespace J5ik2o\EventStoreAdapterPhp\Tests;

use Aws\Sdk;
use J5ik2o\EventStoreAdapterPhp\DefaultEventSerializer;
use J5ik2o\EventStoreAdapterPhp\DefaultKeyResolver;
use J5ik2o\EventStoreAdapterPhp\DefaultSnapshotSerializer;
use J5ik2o\EventStoreAdapterPhp\EventStoreAdapterForDynamoDb;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class EventStoreAdapterForDynamoDbTest extends TestCase {
    public function setUp(): void {
        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            throw new RuntimeException($errstr . " on line " . $errline . " in file " . $errfile);
        });
    }

    public function tearDown(): void {
        restore_error_handler();
    }

    public function testPersist(): void {
        $aws = new Sdk([
            'endpoint' => 'http://localhost:8000',
            'region' => 'ap-northeast-1',
            'version' => 'latest',
        ]);
        $client = $aws->createDynamoDb();

        $journalTableName = 'journal';
        $journalAidIndexName = 'journal-aid-index';
        $this->createJournalTable($client, $journalTableName, $journalAidIndexName);

        $snapshotTableName = 'snapshot';
        $snapshotAidIndexName = 'snapshot-aid-index';
        $this->createSnapshotTable($client, $snapshotTableName, $snapshotAidIndexName);

        $shardCount = 32;
        $eventConverter = function ($eventMap) {
            $typeName = $eventMap["typeName"];
            $id = $eventMap["id"];
            $aggregateId = $eventMap["aggregateId"];
            $sequenceNumber = $eventMap["sequenceNumber"];
            $occurredAt = $eventMap["occurredAt"];
            if ($typeName === "user-account-created") {
                $name = $eventMap["name"];
                return new UserAccountCreated($id, $aggregateId, $sequenceNumber, $name, $occurredAt);
            } elseif ($typeName === "user-account-named") {
                $name = $eventMap["name"];
                return new UserAccountRenamed($id, $aggregateId, $sequenceNumber, $name, $occurredAt);
            } else {
                return null;
            }
        };
        $snapshotConverter = function ($eventMap) {
            $idValue = $eventMap["id"]["value"];
            $id = new UserAccountId($idValue);
            $sequenceNumber = $eventMap["sequenceNumber"];
            $name = $eventMap["name"];
            $version = $eventMap["version"];
            return new UserAccount($id, $sequenceNumber, $name, $version);
        };
        $keepSnapshot = true;
        $keepSnapshotCount = 5;
        $deleteTtlInMillSec = 1000;
        $keyResolver = new DefaultKeyResolver();
        $eventSerializer = new DefaultEventSerializer();
        $snapshotSerializer = new DefaultSnapshotSerializer();

        $eventStoreAdapter = new EventStoreAdapterForDynamoDb(
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
        $eventStoreAdapter->persistEventAndSnapshot($event, $userAccount);

        $result = $eventStoreAdapter->getLatestSnapshotById($userAccountId);
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
    }

    /**
     * @param \Aws\DynamoDb\DynamoDbClient $client
     * @param string $journalTableName
     * @param string $journalAidIndexName
     * @return void
     */
    public function createJournalTable(\Aws\DynamoDb\DynamoDbClient $client, string $journalTableName, string $journalAidIndexName): void {
        $response = $client->listTables();
        foreach ($response['TableNames'] as $element) {
            if ($element === $journalTableName) {
                $client->deleteTable([
                    'TableName' => $journalTableName,
                ]);
            }
        }

        $client->createTable([
            'TableName' => $journalTableName,
            'AttributeDefinitions' => [
                [
                    'AttributeName' => 'pkey',
                    'AttributeType' => 'S',
                ],
                [
                    'AttributeName' => 'skey',
                    'AttributeType' => 'S',
                ],
                [
                    'AttributeName' => 'aid',
                    'AttributeType' => 'S',
                ],
                [
                    'AttributeName' => 'seq_nr',
                    'AttributeType' => 'N',
                ],
            ],
            'KeySchema' => [
                [
                    'AttributeName' => 'pkey',
                    'KeyType' => 'HASH',
                ],
                [
                    'AttributeName' => 'skey',
                    'KeyType' => 'RANGE',
                ],
            ],
            'GlobalSecondaryIndexes' => [
                [
                    'IndexName' => $journalAidIndexName,
                    'KeySchema' => [
                        [
                            'AttributeName' => 'aid',
                            'KeyType' => 'HASH',
                        ],
                        [
                            'AttributeName' => 'seq_nr',
                            'KeyType' => 'RANGE',
                        ],
                    ],
                    'Projection' => [
                        'ProjectionType' => 'ALL',
                    ],
                    'ProvisionedThroughput' => [
                        'ReadCapacityUnits' => 10,
                        'WriteCapacityUnits' => 10,
                    ],
                ],
            ],
            'ProvisionedThroughput' => [
                'ReadCapacityUnits' => 10,
                'WriteCapacityUnits' => 10,
            ],
        ]);
    }

    /**
     * @param \Aws\DynamoDb\DynamoDbClient $client
     * @param string $snapshotTableName
     * @param string $snapshotAidIndexName
     * @return void
     */
    public function createSnapshotTable(\Aws\DynamoDb\DynamoDbClient $client, string $snapshotTableName, string $snapshotAidIndexName): void {
        $response = $client->listTables();
        foreach ($response['TableNames'] as $element) {
            if ($element === $snapshotTableName) {
                $client->deleteTable([
                    'TableName' => $snapshotTableName,
                ]);
            }
        }
        $client->createTable([
            'TableName' => $snapshotTableName,
            'AttributeDefinitions' => [
                [
                    'AttributeName' => 'pkey',
                    'AttributeType' => 'S',
                ],
                [
                    'AttributeName' => 'skey',
                    'AttributeType' => 'S',
                ],
                [
                    'AttributeName' => 'aid',
                    'AttributeType' => 'S',
                ],
                [
                    'AttributeName' => 'seq_nr',
                    'AttributeType' => 'N',
                ],
            ],
            'KeySchema' => [
                [
                    'AttributeName' => 'pkey',
                    'KeyType' => 'HASH',
                ],
                [
                    'AttributeName' => 'skey',
                    'KeyType' => 'RANGE',
                ],
            ],
            'GlobalSecondaryIndexes' => [
                [
                    'IndexName' => $snapshotAidIndexName,
                    'KeySchema' => [
                        [
                            'AttributeName' => 'aid',
                            'KeyType' => 'HASH',
                        ],
                        [
                            'AttributeName' => 'seq_nr',
                            'KeyType' => 'RANGE',
                        ],
                    ],
                    'Projection' => [
                        'ProjectionType' => 'ALL',
                    ],
                    'ProvisionedThroughput' => [
                        'ReadCapacityUnits' => 10,
                        'WriteCapacityUnits' => 10,
                    ],
                ],
            ],
            'ProvisionedThroughput' => ['ReadCapacityUnits' => 10, 'WriteCapacityUnits' => 10],
        ]);
    }
}
