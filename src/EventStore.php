<?php

declare(strict_types=1);

namespace J5ik2o\EventStoreAdapterPhp;

interface EventStore extends EventStoreOptions {
    public function persistEvent(Event $event, int $version): void;

    public function persistEventAndSnapshot(Event $event, Aggregate $aggregate): void;

    public function getLatestSnapshotById(AggregateId $aggregateId): ?Aggregate;

    /**
     * @param AggregateId $aggregateId
     * @param int $sequenceNumber
     * @return array<Event>
     */
    public function getEventsByIdSinceSequenceNumber(AggregateId $aggregateId, int $sequenceNumber): array;
}
