<?php

declare(strict_types=1);

namespace J5ik2o\EventStoreAdapterPhp\Internal;

use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Exception\DynamoDbException;
use J5ik2o\EventStoreAdapterPhp\Aggregate;
use J5ik2o\EventStoreAdapterPhp\AggregateId;
use J5ik2o\EventStoreAdapterPhp\DefaultEventSerializer;
use J5ik2o\EventStoreAdapterPhp\DefaultKeyResolver;
use J5ik2o\EventStoreAdapterPhp\DefaultSnapshotSerializer;
use J5ik2o\EventStoreAdapterPhp\Event;
use J5ik2o\EventStoreAdapterPhp\EventSerializer;
use J5ik2o\EventStoreAdapterPhp\EventStore;
use J5ik2o\EventStoreAdapterPhp\IllegalArgumentException;
use J5ik2o\EventStoreAdapterPhp\KeyResolver;
use J5ik2o\EventStoreAdapterPhp\OptimisticLockException;
use J5ik2o\EventStoreAdapterPhp\PersistenceException;
use J5ik2o\EventStoreAdapterPhp\SerializationException;
use J5ik2o\EventStoreAdapterPhp\SnapshotSerializer;

final class EventStoreForDynamoDb implements EventStore {
    private readonly DynamoDbClient $client;

    private readonly string $journalTableName;

    private readonly string $snapshotTableName;
    private readonly string $journalAidIndexName;
    private readonly string $snapshotAidIndexName;

    private readonly int $shardCount;
    /**
     * @var callable
     */
    private $eventConverter;
    /**
     * @var callable
     */
    private $snapshotConverter;

    private readonly bool $keepSnapshot;

    private readonly int $keepSnapshotCount;

    private readonly int $deleteTtlInMillSec;

    private readonly KeyResolver $keyResolver;
    private readonly EventSerializer $eventSerializer;
    private readonly SnapshotSerializer $snapshotSerializer;

    private readonly EventStoreSupport $eventStoreSupport;


    public function __construct(
        DynamoDbClient      $client,
        string              $journalTableName,
        string              $snapshotTableName,
        string              $journalAidIndexName,
        string              $snapshotAidIndexName,
        int                 $shardCount,
        callable            $eventConverter,
        callable            $snapshotConverter,
        ?bool               $keepSnapshot = null,
        ?int                $keepSnapshotCount = null,
        ?int                $deleteTtlInMillSec = null,
        ?KeyResolver        $keyResolver = null,
        ?EventSerializer    $eventSerializer = null,
        ?SnapshotSerializer $snapshotSerializer = null
    ) {
        $this->client = $client;
        $this->journalTableName = $journalTableName;
        $this->snapshotTableName = $snapshotTableName;
        $this->journalAidIndexName = $journalAidIndexName;
        $this->snapshotAidIndexName = $snapshotAidIndexName;
        $this->shardCount = $shardCount;
        $this->eventConverter = $eventConverter;
        $this->snapshotConverter = $snapshotConverter;
        if ($keepSnapshot === null) {
            $this->keepSnapshot = false;
        } else {
            $this->keepSnapshot = $keepSnapshot;
        }
        if ($keepSnapshotCount === null) {
            $this->keepSnapshotCount = 0;
        } else {
            $this->keepSnapshotCount = $keepSnapshotCount;
        }
        if ($deleteTtlInMillSec === null) {
            $this->deleteTtlInMillSec = 1000;
        } else {
            $this->deleteTtlInMillSec = $deleteTtlInMillSec;
        }
        if ($keyResolver === null) {
            $this->keyResolver = new DefaultKeyResolver();
        } else {
            $this->keyResolver = $keyResolver;
        }
        if ($eventSerializer === null) {
            $this->eventSerializer = new DefaultEventSerializer();
        } else {
            $this->eventSerializer = $eventSerializer;
        }
        if ($snapshotSerializer === null) {
            $this->snapshotSerializer = new DefaultSnapshotSerializer();
        } else {
            $this->snapshotSerializer = $snapshotSerializer;
        }
        $this->eventStoreSupport = new EventStoreSupport(
            $journalTableName,
            $snapshotTableName,
            $journalAidIndexName,
            $snapshotAidIndexName,
            $shardCount,
            $eventConverter,
            $snapshotConverter,
            $keepSnapshot,
            $keepSnapshotCount,
            $deleteTtlInMillSec,
            $keyResolver,
            $eventSerializer,
            $snapshotSerializer
        );
    }

    public function withKeepSnapshot(bool $keepSnapshot): EventStore {
        return new EventStoreForDynamoDb(
            $this->client,
            $this->journalTableName,
            $this->snapshotTableName,
            $this->journalAidIndexName,
            $this->snapshotAidIndexName,
            $this->shardCount,
            $this->eventConverter,
            $this->snapshotConverter,
            $keepSnapshot,
            $this->keepSnapshotCount,
            $this->deleteTtlInMillSec,
            $this->keyResolver,
            $this->eventSerializer,
            $this->snapshotSerializer
        );
    }

    public function withKeepSnapshotCount(int $keepSnapshotCount): EventStore {
        return new EventStoreForDynamoDb(
            $this->client,
            $this->journalTableName,
            $this->snapshotTableName,
            $this->journalAidIndexName,
            $this->snapshotAidIndexName,
            $this->shardCount,
            $this->eventConverter,
            $this->snapshotConverter,
            $this->keepSnapshot,
            $keepSnapshotCount,
            $this->deleteTtlInMillSec,
            $this->keyResolver,
            $this->eventSerializer,
            $this->snapshotSerializer
        );
    }

    public function withDeleteTtl(int $deleteTtlInMillSec): EventStore {
        return new EventStoreForDynamoDb(
            $this->client,
            $this->journalTableName,
            $this->snapshotTableName,
            $this->journalAidIndexName,
            $this->snapshotAidIndexName,
            $this->shardCount,
            $this->eventConverter,
            $this->snapshotConverter,
            $this->keepSnapshot,
            $this->keepSnapshotCount,
            $deleteTtlInMillSec,
            $this->keyResolver,
            $this->eventSerializer,
            $this->snapshotSerializer
        );
    }


    public function withKeyResolver(KeyResolver $keyResolver): EventStore {
        return new EventStoreForDynamoDb(
            $this->client,
            $this->journalTableName,
            $this->snapshotTableName,
            $this->journalAidIndexName,
            $this->snapshotAidIndexName,
            $this->shardCount,
            $this->eventConverter,
            $this->snapshotConverter,
            $this->keepSnapshot,
            $this->keepSnapshotCount,
            $this->deleteTtlInMillSec,
            $keyResolver,
            $this->eventSerializer,
            $this->snapshotSerializer
        );
    }

    public function withEventSerializer(EventSerializer $eventSerializer): EventStore {
        return new EventStoreForDynamoDb(
            $this->client,
            $this->journalTableName,
            $this->snapshotTableName,
            $this->journalAidIndexName,
            $this->snapshotAidIndexName,
            $this->shardCount,
            $this->eventConverter,
            $this->snapshotConverter,
            $this->keepSnapshot,
            $this->keepSnapshotCount,
            $this->deleteTtlInMillSec,
            $this->keyResolver,
            $eventSerializer,
            $this->snapshotSerializer
        );
    }

    public function withSnapshotSerializer(SnapshotSerializer $snapshotSerializer): EventStore {
        return new EventStoreForDynamoDb(
            $this->client,
            $this->journalTableName,
            $this->snapshotTableName,
            $this->journalAidIndexName,
            $this->snapshotAidIndexName,
            $this->shardCount,
            $this->eventConverter,
            $this->snapshotConverter,
            $this->keepSnapshot,
            $this->keepSnapshotCount,
            $this->deleteTtlInMillSec,
            $this->keyResolver,
            $this->eventSerializer,
            $snapshotSerializer
        );
    }

    /**
     * @throws SerializationException
     * @throws OptimisticLockException
     * @throws PersistenceException
     */
    private function createEventAndSnapshot(Event $event, Aggregate $aggregate): void {
        $putJournal = $this->eventStoreSupport->generatePutJournalRequest($event);
        $putSnapshot = $this->eventStoreSupport->generatePutSnapshotRequest($event, 0, $aggregate);
        $transactItems = ['TransactItems' => [$putJournal, $putSnapshot]];
        // TODO: keepSnapshot
        try {
            $this->client->transactWriteItems($transactItems);
        } catch (DynamoDbException $ex) {
            $this->eventStoreSupport->handleWriteException($ex, $event, $aggregate->getVersion());
        }
    }

    /**
     * @throws SerializationException
     * @throws OptimisticLockException
     * @throws PersistenceException
     */
    private function updateEventAndSnapshotOpt(Event $event, int $version, ?Aggregate $aggregate): void {
        $putJournal = $this->eventStoreSupport->generatePutJournalRequest($event);
        $updateSnapshot = $this->eventStoreSupport->generateUpdateSnapshotRequest($event, 0, $version, $aggregate);
        $transactItems = ['TransactItems' => [$putJournal, $updateSnapshot]];
        try {
            $this->client->transactWriteItems($transactItems);
        } catch (DynamoDbException $ex) {
            $this->eventStoreSupport->handleWriteException($ex, $event, $version);
        }
    }


    /**
     * @throws IllegalArgumentException
     * @throws SerializationException
     * @throws OptimisticLockException
     * @throws PersistenceException
     */
    public function persistEvent(Event $event, int $version): void {
        if ($event->isCreated()) {
            throw new IllegalArgumentException('event is created type');
        }
        $this->updateEventAndSnapshotOpt($event, $version, null);
        // TODO: tryPurgeExcessSnapshots
    }

    /**
     * @throws SerializationException
     * @throws OptimisticLockException
     * @throws PersistenceException
     */
    public function persistEventAndSnapshot(Event $event, Aggregate $aggregate): void {
        if ($event->isCreated()) {
            $this->createEventAndSnapshot($event, $aggregate);
        } else {
            $this->updateEventAndSnapshotOpt($event, $aggregate->getVersion(), $aggregate);
            // TODO: tryPurgeExcessSnapshots
        }
    }

    /**
     * @param AggregateId $aggregateId
     * @return Aggregate|null
     * @throws SerializationException
     * @throws PersistenceException
     */
    public function getLatestSnapshotById(AggregateId $aggregateId): ?Aggregate {
        try {
            $request = $this->eventStoreSupport->generateGetSnapshot($aggregateId);
            $response = $this->client->query($request);
            return $this->eventStoreSupport->convertFromResponseToSnapshot($response);
        } catch (DynamoDbException $ex) {
            throw $this->eventStoreSupport->convertToGetLatestSnapshotException($aggregateId, $ex);
        }
    }

    /**
     * @param AggregateId $aggregateId
     * @param int $sequenceNumber
     * @return array<Event>
     * @throws SerializationException
     * @throws PersistenceException
     */
    public function getEventsByIdSinceSequenceNumber(AggregateId $aggregateId, int $sequenceNumber): array {
        try {
            $request = $this->eventStoreSupport->generateGetEventsRequest($aggregateId, $sequenceNumber);
            $response = $this->client->query($request);
            return $this->eventStoreSupport->convertFromResponseToEvents($response);
        } catch (DynamoDbException $ex) {
            throw $this->eventStoreSupport->convertToGetEventsException($aggregateId, $sequenceNumber, $ex);
        }
    }




}
