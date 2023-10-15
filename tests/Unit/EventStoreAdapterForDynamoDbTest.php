<?php declare(strict_types=1);

use Aws\Sdk;
use PHPUnit\Framework\TestCase;

final class EventStoreAdapterForDynamoDbTest extends TestCase {
    public function testPersist() {
        $aws = new Sdk([
            'endpoint' => 'http://localhost:8000',
            'region' => 'ap-northeast-1',
            'version' => 'latest'
        ]);
        $client = $aws->createDynamoDb();
        $journalTableName = 'journal';
        $journalAidIndexName = 'journal-aid-index';

        $this->createJournalTable($client, $journalTableName, $journalAidIndexName);

        $snapshotTableName = 'snapshot';
        $snapshotAidIndexName = 'snapshot-aid-index';
        $this->createSnapshotTable($client, $snapshotTableName, $snapshotAidIndexName);


//        $shardCount = '';
//        $eventConverter = '';
//        $snapshotConverter = '';
//        $keepSnapshot = '';
//        $keepSnapshotCount = 5;
//        $deleteTtlInMillSec = '';
//        $keyResolver = new DefaultKeyResolver();
//        $eventSerializer = new DefaultEventSerializer();
//        $snapshotSerializer = new DefaultSnapshotSerializer();
//
//        $eventStoreAdapter = new EventStoreAdapterForDynamoDb(
//            $client,
//            $journalTableName,
//            $snapshotTableName,
//            $journalAidIndexName,
//            $snapshotAidIndexName,
//            $shardCount,
//            $eventConverter,
//            $snapshotConverter,
//            $keepSnapshot,
//            $keepSnapshotCount,
//            $deleteTtlInMillSec,
//            $keyResolver,
//            $eventSerializer,
//            $snapshotSerializer
//        );

    }

    /**
     * @param \Aws\DynamoDb\DynamoDbClient $client
     * @param string $journalTableName
     * @param string $journalAidIndexName
     * @return void
     */
    public function createJournalTable(\Aws\DynamoDb\DynamoDbClient $client, string $journalTableName, string $journalAidIndexName): void {
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
                ]
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
                        ]
                    ],
                    'Projection' => [
                        'ProjectionType' => 'ALL'
                    ],
                    'ProvisionedThroughput' => [
                        'ReadCapacityUnits' => 10,
                        'WriteCapacityUnits' => 10
                    ],
                ]
            ],
            'ProvisionedThroughput' => [
                'ReadCapacityUnits' => 10,
                'WriteCapacityUnits' => 10
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
                ]
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
                        ]
                    ],
                    'Projection' => [
                        'ProjectionType' => 'ALL'
                    ],
                    'ProvisionedThroughput' => [
                        'ReadCapacityUnits' => 10,
                        'WriteCapacityUnits' => 10
                    ],
                ]
            ],
            'ProvisionedThroughput' => ['ReadCapacityUnits' => 10, 'WriteCapacityUnits' => 10],
        ]);
    }
}