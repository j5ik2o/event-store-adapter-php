<?php

declare(strict_types=1);

namespace J5ik2o\EventStoreAdapterPhp;

/**
 * This is an interface for resolving partition keys and sort keys from aggregate IDs.
 */
interface KeyResolver {
    /**
     * Resolves the partition key from the aggregate id.
     *
     * @param AggregateId $aggregateId
     * @param int $shardCount
     * @return string
     */
    public function resolvePartitionKey(AggregateId $aggregateId, int $shardCount): string;

    /**
     * Resolves the sort key from the aggregate id and sequence number.
     *
     * @param AggregateId $aggregateId
     * @param int $sequenceNumber
     * @return string
     */
    public function resolveSortKey(AggregateId $aggregateId, int $sequenceNumber): string;
}
