<?php

namespace J5ik2o\EventStoreAdapterPhp;

final class DefaultKeyResolver implements KeyResolver {
    public function __construct() {}

    public function resolvePartitionKey(AggregateId $aggregateId, int $shardCount): string {
        $remainder = abs(crc32($aggregateId->getValue())) % $shardCount;
        return sprintf("%s-%d", $aggregateId->getTypeName(), $remainder);
    }

    public function resolveSortKey(AggregateId $aggregateId, int $sequenceNumber): string {
        return sprintf("%s-%s-%d", $aggregateId->getTypeName(), $aggregateId->getValue(), $sequenceNumber);
    }
}