<?php

interface KeyResolver {
    public function resolvePartitionKey(AggregateId $aggregateId, int $shardCount): string;

    public function resolveSortKey(AggregateId $aggregateId, int $sequenceNumber): string;
}

