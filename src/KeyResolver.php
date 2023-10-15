<?php declare(strict_types=1);

namespace J5ik2o\EventStoreAdapterPhp;

interface KeyResolver {
    public function resolvePartitionKey(AggregateId $aggregateId, int $shardCount): string;

    public function resolveSortKey(AggregateId $aggregateId, int $sequenceNumber): string;
}

