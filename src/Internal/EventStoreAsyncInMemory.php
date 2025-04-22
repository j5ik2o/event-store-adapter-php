<?php

namespace J5ik2o\EventStoreAdapterPhp\Internal;

use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use J5ik2o\EventStoreAdapterPhp\Aggregate;
use J5ik2o\EventStoreAdapterPhp\AggregateId;
use J5ik2o\EventStoreAdapterPhp\Event;
use J5ik2o\EventStoreAdapterPhp\EventSerializer;
use J5ik2o\EventStoreAdapterPhp\EventStoreAsync;
use J5ik2o\EventStoreAdapterPhp\KeyResolver;
use J5ik2o\EventStoreAdapterPhp\SnapshotSerializer;
use J5ik2o\EventStoreAdapterPhp\OptimisticLockException;

final class EventStoreAsyncInMemory implements EventStoreAsync {
    private const INITIAL_VERSION = 1;
    /** @var array<string, array<Event>> */
    private array $events;

    /** @var array<string, Aggregate> */
    private array $snapshots;

    public function __construct() {
        $this->events = [];
        $this->snapshots = [];
    }

    public function withKeepSnapshot(bool $keepSnapshot): EventStoreAsync {
        return $this;
    }

    public function withDeleteTtl(int $deleteTtlInMillSec): EventStoreAsync {
        return $this;
    }

    public function withKeepSnapshotCount(int $keepSnapshotCount): EventStoreAsync {
        return $this;
    }

    public function withKeyResolver(KeyResolver $keyResolver): EventStoreAsync {
        return $this;
    }

    public function withEventSerializer(EventSerializer $eventSerializer): EventStoreAsync {
        return $this;
    }

    public function withSnapshotSerializer(SnapshotSerializer $snapshotSerializer): EventStoreAsync {
        return $this;
    }

    public function persistEvent(Event $event, int $version): PromiseInterface {
        $promise = new FulfilledPromise([$event, $version]);
        return $promise->then(function ($arg) {
            /** @var Event $event */
            /** @var int $version */
            [$event, $version] = $arg;
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
        });
    }

    public function persistEventAndSnapshot(Event $event, Aggregate $aggregate): PromiseInterface {
        $promise = new FulfilledPromise([$event, $aggregate]);
        return $promise->then(function ($arg) {
            /** @var Event $event */
            /** @var Aggregate $aggregate */
            [$event, $aggregate] = $arg;
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
            return null;
        });
    }

    public function getLatestSnapshotById(AggregateId $aggregateId): PromiseInterface {
        $promise = new FulfilledPromise($aggregateId);
        return $promise->then(function (AggregateId $aggregateId) {
            $id = $aggregateId->asString();
            if (isset($this->snapshots[$id])) {
                return $this->snapshots[$id];
            }
            return null;
        });
    }

    public function getEventsByIdSinceSequenceNumber(AggregateId $aggregateId, int $sequenceNumber): PromiseInterface {
        $promise = new FulfilledPromise([$aggregateId, $sequenceNumber]);
        return $promise->then(function ($arg) {
            /** @var AggregateId $aggregateId */
            /** @var int $sequenceNumber */
            [$aggregateId, $sequenceNumber] = $arg;
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
        });
    }
}
