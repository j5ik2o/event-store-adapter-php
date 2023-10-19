<?php

declare(strict_types=1);

namespace J5ik2o\EventStoreAdapterPhp;

/**
 * Represents an event store.
 */
interface EventStore {
    public function withKeepSnapshot(bool $keepSnapshot): EventStore;

    public function withDeleteTtl(int $deleteTtlInMillSec): EventStore;

    public function withKeepSnapshotCount(int $keepSnapshotCount): EventStore;

    public function withKeyResolver(KeyResolver $keyResolver): EventStore;

    public function withEventSerializer(EventSerializer $eventSerializer): EventStore;

    public function withSnapshotSerializer(SnapshotSerializer $snapshotSerializer): EventStore;

    /**
     * Persists an event only.
     *
     * @param Event $event
     * @param int $version
     * @throws IllegalArgumentException
     * @throws SerializationException
     * @throws OptimisticLockException
     * @throws PersistenceException
     */
    public function persistEvent(Event $event, int $version): void;

    /**
     * Persists an event and a snapshot.
     *
     * @param Event $event
     * @param Aggregate $aggregate
     * @throws SerializationException
     * @throws OptimisticLockException
     * @throws PersistenceException
     */
    public function persistEventAndSnapshot(Event $event, Aggregate $aggregate): void;

    /**
     * Gets the latest snapshot by the aggregate id.
     *
     * @param AggregateId $aggregateId
     * @return Aggregate|null
     * @throws SerializationException
     * @throws PersistenceException
     */
    public function getLatestSnapshotById(AggregateId $aggregateId): ?Aggregate;

    /**
     * Gets the events by the aggregate id and since the sequence number.
     *
     * @param AggregateId $aggregateId
     * @param int $sequenceNumber
     * @return array<Event>
     * @throws SerializationException
     * @throws PersistenceException
     */
    public function getEventsByIdSinceSequenceNumber(AggregateId $aggregateId, int $sequenceNumber): array;
}
