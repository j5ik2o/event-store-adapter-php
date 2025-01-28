<?php

namespace J5ik2o\EventStoreAdapterPhp\Internal;

use J5ik2o\EventStoreAdapterPhp\Aggregate;
use J5ik2o\EventStoreAdapterPhp\AggregateId;
use J5ik2o\EventStoreAdapterPhp\Event;
use J5ik2o\EventStoreAdapterPhp\EventSerializer;
use J5ik2o\EventStoreAdapterPhp\EventStore;
use J5ik2o\EventStoreAdapterPhp\KeyResolver;
use J5ik2o\EventStoreAdapterPhp\OptimisticLockException;
use J5ik2o\EventStoreAdapterPhp\SnapshotSerializer;

final class EventStoreInMemory implements EventStore {
    private const INITIAL_VERSION = 1;

    /** @var array<string, array<Event>> */
    private array $events;

    /** @var array<string, Aggregate> */
    private array $snapshots;

    public function __construct() {
        $this->events = [];
        $this->snapshots = [];
    }

    public function withDeleteTtl(int $deleteTtlInMillSec): EventStore {
        return $this;
    }

    public function withKeepSnapshotCount(int $keepSnapshotCount): EventStore {
        return $this;
    }

    public function withKeyResolver(KeyResolver $keyResolver): EventStore {
        return $this;
    }

    public function withEventSerializer(EventSerializer $eventSerializer): EventStore {
        return $this;
    }

    public function withSnapshotSerializer(SnapshotSerializer $snapshotSerializer): EventStore {
        return $this;
    }

    public function persistEvent(Event $event, int $version): void {
        if ($event->isCreated()) {
            throw new \RuntimeException('event is created');
        }

        $aggregateId = $event->getAggregateId()->asString();

        if (!isset($this->snapshots[$aggregateId]) || $this->snapshots[$aggregateId]->getVersion() !== $version) {
            throw new OptimisticLockException(
                'Transaction write was canceled due to conditional check failure'
            );
        }

        $newVersion = $this->snapshots[$aggregateId]->getVersion() + 1;
        $this->events[$aggregateId][] = $event;

        $snapshot = $this->snapshots[$aggregateId];
        $snapshot = $snapshot->withVersion($newVersion);
        $this->snapshots[$aggregateId] = $snapshot;
    }

    public function persistEventAndSnapshot(Event $event, Aggregate $aggregate): void {
        $aggregateId = $event->getAggregateId()->asString();
        $newVersion = self::INITIAL_VERSION;

        if (!$event->isCreated() && isset($this->snapshots[$aggregateId])) {
            if ($this->snapshots[$aggregateId]->getVersion() !== $aggregate->getVersion()) {
                throw new OptimisticLockException(
                    'Transaction write was canceled due to conditional check failure'
                );
            }
            $newVersion = $this->snapshots[$aggregateId]->getVersion() + 1;
        }

        $this->events[$aggregateId][] = $event;
        $this->snapshots[$aggregateId] = $aggregate->withVersion($newVersion);
    }

    public function getLatestSnapshotById(AggregateId $aggregateId): ?Aggregate {
        $id = $aggregateId->asString();
        if (isset($this->snapshots[$id])) {
            return $this->snapshots[$id];
        }
        return null;
    }

    public function getEventsByIdSinceSequenceNumber(AggregateId $aggregateId, int $sequenceNumber): array {
        $result = [];
        $id = $aggregateId->asString();

        if (!isset($this->events[$id])) {
            return $result;
        }

        foreach ($this->events[$id] as $event) {
            if ($event->getSequenceNumber() >= $sequenceNumber) {
                $result[] = $event;
            }
        }
        return $result;
    }
}
