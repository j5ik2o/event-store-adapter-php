<?php

declare(strict_types=1);

namespace J5ik2o\EventStoreAdapterPhp;

use Aws\DynamoDb\DynamoDbClient;
use J5ik2o\EventStoreAdapterPhp\Internal\EventStoreAsyncForDynamoDb;
use J5ik2o\EventStoreAdapterPhp\Internal\EventStoreForDynamoDb;
use J5ik2o\EventStoreAdapterPhp\Internal\EventStoreAsyncInMemory;
use J5ik2o\EventStoreAdapterPhp\Internal\EventStoreInMemory;

final class EventStoreFactory {
    public static function createInMemory(): EventStore {
        return new EventStoreInMemory();
    }

    public static function createInMemoryAsync(): EventStoreAsync {
        return new EventStoreAsyncInMemory();
    }

    public static function create(
        DynamoDbClient $client,
        string         $journalTableName,
        string         $snapshotTableName,
        string         $journalAidIndexName,
        string         $snapshotAidIndexName,
        int            $shardCount,
        callable       $eventConverter,
        callable       $snapshotConverter
    ): EventStore {
        return new EventStoreForDynamoDb(
            $client,
            $journalTableName,
            $snapshotTableName,
            $journalAidIndexName,
            $snapshotAidIndexName,
            $shardCount,
            $eventConverter,
            $snapshotConverter
        );
    }

    public static function createAsync(
        DynamoDbClient $client,
        string         $journalTableName,
        string         $snapshotTableName,
        string         $journalAidIndexName,
        string         $snapshotAidIndexName,
        int            $shardCount,
        callable       $eventConverter,
        callable       $snapshotConverter
    ): EventStoreAsync {
        return new EventStoreAsyncForDynamoDb(
            $client,
            $journalTableName,
            $snapshotTableName,
            $journalAidIndexName,
            $snapshotAidIndexName,
            $shardCount,
            $eventConverter,
            $snapshotConverter
        );
    }
}
