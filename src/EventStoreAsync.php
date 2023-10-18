<?php

declare(strict_types=1);

namespace J5ik2o\EventStoreAdapterPhp;

use GuzzleHttp\Promise\PromiseInterface;

interface EventStoreAsync {
    public function withKeepSnapshot(bool $keepSnapshot): EventStoreAsync;

    public function withDeleteTtl(int $deleteTtlInMillSec): EventStoreAsync;

    public function withKeepSnapshotCount(int $keepSnapshotCount): EventStoreAsync;

    public function withKeyResolver(KeyResolver $keyResolver): EventStoreAsync;

    public function withEventSerializer(EventSerializer $eventSerializer): EventStoreAsync;

    public function withSnapshotSerializer(SnapshotSerializer $snapshotSerializer): EventStoreAsync;

    /**
     * Persists an event only.
     *
     * @param Event $event
     * @param int $version
     * @return PromiseInterface
     * @throws IllegalArgumentException
     * @throws SerializationException
     */
    public function persistEvent(Event $event, int $version): PromiseInterface;

    /**
     * Persists an event and a snapshot.
     *
     * @param Event $event
     * @param Aggregate $aggregate
     * @return PromiseInterface
     * @throws SerializationException
     */
    public function persistEventAndSnapshot(Event $event, Aggregate $aggregate): PromiseInterface;

    /**
     * Gets the latest snapshot by the aggregate id.
     *
     * @param AggregateId $aggregateId
     * @return PromiseInterface
     */
    public function getLatestSnapshotById(AggregateId $aggregateId): PromiseInterface;

    /**
     * Gets the events by the aggregate id and since the sequence number.
     *
     * @param AggregateId $aggregateId
     * @param int $sequenceNumber
     * @return PromiseInterface
     */
    public function getEventsByIdSinceSequenceNumber(AggregateId $aggregateId, int $sequenceNumber): PromiseInterface;
}
