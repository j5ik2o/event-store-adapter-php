<?php

declare(strict_types=1);

namespace J5ik2o\EventStoreAdapterPhp;

use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\PromiseInterface;

interface EventStoreAsync extends EventStoreOptions {
    /**
     * Persist an event to the event store.
     *
     * @param Event $event
     * @param int $version
     * @return Promise<void>
     */
    public function persistEvent(Event $event, int $version): Promise;

    /**
     * Persist an event to the event store and create a snapshot.
     *
     * @param Event $event
     * @param Aggregate $aggregate
     * @return Promise
     */
    public function persistEventAndSnapshot(Event $event, Aggregate $aggregate): Promise;

    /**
     * Gets the latest snapshot for an aggregate.
     *
     * @param AggregateId $aggregateId
     * @return Promise<?Aggregate>
     */
    public function getLatestSnapshotById(AggregateId $aggregateId): PromiseInterface;

    /**
     * Gets all events for an aggregate.
     *
     * @param AggregateId $aggregateId
     * @param int $sequenceNumber
     * @return Promise<array<Event>>
     */
    public function getEventsByIdSinceSequenceNumber(AggregateId $aggregateId, int $sequenceNumber): PromiseInterface;
}
