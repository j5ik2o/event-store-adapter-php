<?php

namespace J5ik2o\EventStoreAdapterPhp\Internal;

use Aws\DynamoDb\DynamoDbClient;
use Exception;
use GuzzleHttp\Promise\PromiseInterface;
use J5ik2o\EventStoreAdapterPhp\Aggregate;
use J5ik2o\EventStoreAdapterPhp\AggregateId;
use J5ik2o\EventStoreAdapterPhp\DefaultEventSerializer;
use J5ik2o\EventStoreAdapterPhp\DefaultKeyResolver;
use J5ik2o\EventStoreAdapterPhp\DefaultSnapshotSerializer;
use J5ik2o\EventStoreAdapterPhp\Event;
use J5ik2o\EventStoreAdapterPhp\EventSerializer;
use J5ik2o\EventStoreAdapterPhp\EventStoreAsync;
use J5ik2o\EventStoreAdapterPhp\IllegalArgumentException;
use J5ik2o\EventStoreAdapterPhp\KeyResolver;
use J5ik2o\EventStoreAdapterPhp\SnapshotSerializer;

final class EventStoreAsyncForDynamoDb implements EventStoreAsync {
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


    public function withKeepSnapshot(bool $keepSnapshot): EventStoreAsync {
        return new EventStoreAsyncForDynamoDb(
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

    public function withKeepSnapshotCount(int $keepSnapshotCount): EventStoreAsync {
        return new EventStoreAsyncForDynamoDb(
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
            $this->snapshotSerializer
        );
    }

    public function withDeleteTtl(int $deleteTtlInMillSec): EventStoreAsync {
        return new EventStoreAsyncForDynamoDb(
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

    public function withKeyResolver(KeyResolver $keyResolver): EventStoreAsync {
        return new EventStoreAsyncForDynamoDb(
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

    public function withEventSerializer(EventSerializer $eventSerializer): EventStoreAsync {
        return new EventStoreAsyncForDynamoDb(
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

    public function withSnapshotSerializer(SnapshotSerializer $snapshotSerializer): EventStoreAsync {
        return new EventStoreAsyncForDynamoDb(
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

    private function createEventAndSnapshot(Event $event, Aggregate $aggregate): PromiseInterface {
        $putJournal = $this->eventStoreSupport->putJournal($event);
        $putSnapshot = $this->eventStoreSupport->putSnapshot($event, 0, $aggregate);
        $transactItems = ['TransactItems' => [$putJournal, $putSnapshot]];
        // TODO: keepSnapshot
        return $this->client->transactWriteItemsAsync($transactItems);
    }

    private function updateEventAndSnapshotOpt(Event $event, int $version, ?Aggregate $aggregate): PromiseInterface {
        $putJournal = $this->eventStoreSupport->putJournal($event);
        $updateSnapshot = $this->eventStoreSupport->updateSnapshot($event, 0, $version, $aggregate);
        $transactItems = ['TransactItems' => [$putJournal, $updateSnapshot]];
        return $this->client->transactWriteItemsAsync($transactItems);
    }

    /**
     * @throws IllegalArgumentException
     */
    public function persistEvent(Event $event, int $version): PromiseInterface {
        if ($event->isCreated()) {
            throw new IllegalArgumentException('event is created type');
        }
        return $this->updateEventAndSnapshotOpt($event, $version, null);
    }

    public function persistEventAndSnapshot(Event $event, Aggregate $aggregate): PromiseInterface {
        if ($event->isCreated()) {
            return $this->createEventAndSnapshot($event, $aggregate);
        } else {
            return $this->updateEventAndSnapshotOpt($event, $aggregate->getVersion(), $aggregate);
        }
    }

    public function getLatestSnapshotById(AggregateId $aggregateId): PromiseInterface {
        $request = $this->eventStoreSupport->getLatestSnapshotById($aggregateId);
        $response = $this->client->queryAsync($request);
        return $response->then(function ($response) {
            if ($response->count() == 0) {
                return null;
            } else {
                if (is_array($response['Items']) && isset($response['Items'][0])) {
                    $item = $response['Items'][0];
                    $version = $item['version']['N'];
                    $payload = $item['payload']['S'];
                    $aggregate = $this->eventStoreSupport->convertToSnapshot($payload);
                    if ($aggregate instanceof Aggregate) {
                        return $aggregate->withVersion((int)$version);
                    }
                }
                throw new Exception("Failed to deserialize aggregate");
            }
        });
    }

    public function getEventsByIdSinceSequenceNumber(AggregateId $aggregateId, int $sequenceNumber): PromiseInterface {
        $request = $this->eventStoreSupport->getEventsByIdSinceSequenceNumber($aggregateId, $sequenceNumber);
        $response = $this->client->queryAsync($request);
        return $response->then(function ($response) {
            $result = [];
            if (is_iterable($response['Items'])) {
                foreach ($response['Items'] as $item) {
                    $payload = $item["payload"]["S"];
                    $event = $this->eventStoreSupport->convertToEvent($payload);
                    $result[] = $event;
                }
            }
            return $result;
        });
    }


}
