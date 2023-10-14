<?php

interface EventStoreAdapter {

    public function withKeepSnapshot(bool $keepSnapshot): EventStoreAdapter;

    public function withDeleteTtl(int $deleteTtlInMillSec): EventStoreAdapter;

    public function withKeepSnapshotCount(int $keepSnapshotCount): EventStoreAdapter;

    public function withKeyResolver(KeyResolver $keyResolver): EventStoreAdapter;

    public function persistEvent(Event $event, int $version);

    public function persistEventAndSnapshot(Event $event, Aggregate $aggregate);


    public function getLatestSnapshotById(AggregateId $aggregateId): ?Aggregate;

    /**
     * @param AggregateId $aggregateId
     * @param int $sequenceNumber
     * @return array<Event>
     */
    public function getEventsByIdSinceSequenceNumber(AggregateId $aggregateId, int $sequenceNumber): array;
}