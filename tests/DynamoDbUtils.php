<?php

namespace J5ik2o\EventStoreAdapterPhp\Tests;

use Aws\DynamoDb\DynamoDbClient;

final class DynamoDbUtils {
    /**
     * @param DynamoDbClient $client
     * @param string $journalTableName
     * @param string $journalAidIndexName
     * @return void
     */
    public static function createJournalTable(DynamoDbClient $client, string $journalTableName, string $journalAidIndexName): void {
        $response = $client->listTables();
        if (is_iterable($response['TableNames'])) {
            foreach ($response['TableNames'] as $element) {
                if ($element === $journalTableName) {
                    $client->deleteTable([
                        'TableName' => $journalTableName,
                    ]);
                }
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
     * @param DynamoDbClient $client
     * @param string $snapshotTableName
     * @param string $snapshotAidIndexName
     * @return void
     */
    public static function createSnapshotTable(DynamoDbClient $client, string $snapshotTableName, string $snapshotAidIndexName): void {
        $response = $client->listTables();
        if (is_iterable($response['TableNames'])) {
            foreach ($response['TableNames'] as $element) {
                if ($element === $snapshotTableName) {
                    $client->deleteTable([
                        'TableName' => $snapshotTableName,
                    ]);
                }
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