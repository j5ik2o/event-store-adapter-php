<?php declare(strict_types=1);

namespace J5ik2o\EventStoreAdapterPhp;

use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Marshaler;

date_default_timezone_set('UTC');

final class EventStoreAdapterForDynamoDb implements EventStoreAdapter {
    private DynamoDbClient $client;

    private string $journalTableName;

    private string $snapshotTableName;
    private string $journalAidIndexName;
    private string $snapshotAidIndexName;

    private int $shardCount;
    /**
     * @var callable
     */
    private $eventConverter;
    /**
     * @var callable
     */
    private $snapshotConverter;

    private bool $keepSnapshot;

    private int $keepSnapshotCount;

    private int $deleteTtlInMillSec;

    private KeyResolver $keyResolver;
    private EventSerializer $eventSerializer;
    private SnapshotSerializer $snapshotSerializer;
    private Marshaler $marshaler;


    public function __construct(DynamoDbClient     $client,
                                string             $journalTableName,
                                string             $snapshotTableName,
                                string             $journalAidIndexName,
                                string             $snapshotAidIndexName,
                                int                $shardCount,
                                callable           $eventConverter,
                                callable           $snapshotConverter,
                                bool $keepSnapshot,
                                int  $keepSnapshotCount,
                                int  $deleteTtlInMillSec,
                                KeyResolver        $keyResolver,
                                EventSerializer    $eventSerializer,
                                SnapshotSerializer $snapshotSerializer) {
        $this->client = $client;
        $this->marshaler = new Marshaler();
        $this->journalTableName = $journalTableName;
        $this->snapshotTableName = $snapshotTableName;
        $this->journalAidIndexName = $journalAidIndexName;
        $this->snapshotAidIndexName = $snapshotAidIndexName;
        $this->shardCount = $shardCount;
        $this->eventConverter = $eventConverter;
        $this->snapshotConverter = $snapshotConverter;
        $this->keepSnapshot = $keepSnapshot;
        $this->deleteTtlInMillSec = $deleteTtlInMillSec;
        $this->keepSnapshotCount = $keepSnapshotCount;
        $this->keyResolver = $keyResolver;
        $this->eventSerializer = $eventSerializer;
        $this->snapshotSerializer = $snapshotSerializer;
    }

    public function withKeepSnapshot(bool $keepSnapshot): EventStoreAdapter {
        return $this;
    }

    public function withDeleteTtl(int $deleteTtlInMillSec): EventStoreAdapter {
        return $this;
    }

    public function withKeepSnapshotCount(int $keepSnapshotCount): EventStoreAdapter {
        return $this;
    }

    public function withKeyResolver(KeyResolver $keyResolver): EventStoreAdapter {
        return $this;
    }

    private function putSnapshot(Event $event, int $seqNr, Aggregate $aggregate): array {
        $pkey = $this->keyResolver->resolvePartitionKey($event->getAggregateId(), $this->shardCount);
        $skey = $this->keyResolver->resolveSortKey($event->getAggregateId(), $seqNr);
        $payload = $this->snapshotSerializer->serialize($aggregate);
        return [
            'Put' => [
                'TableName' => $this->snapshotTableName,
                'Item' => $this->marshaler->marshalItem([
                    'pkey' => $pkey,
                    'skey' => $skey,
                    'aid' => $event->getAggregateId()->asString(),
                    'seq_nr' => $seqNr,
                    'payload' => $payload,
                    'version' => 1,
                    'ttl' => 0,
                ]),
                'ConditionExpression' => 'attribute_not_exists(pkey) AND attribute_not_exists(skey)'
            ]
        ];
    }

    private function updateSnapshot(Event $event, int $seqNr, int $version, ?Aggregate $aggregate): array {
        $pkey = $this->keyResolver->resolvePartitionKey($event->getAggregateId(), $this->shardCount);
        $skey = $this->keyResolver->resolveSortKey($event->getAggregateId(), $seqNr);
        $update = [
            'Update' => [
                'TableName' => $this->snapshotTableName,
                'UpdateExpression' => 'SET #version=:after_version',
                'Key' => $this->marshaler->marshalItem([
                    'pkey' => $pkey,
                    'skey' => $skey,
                ]),
                'ExpressionAttributeNames' => [
                    '#version' => 'version'
                ],
                'ExpressionAttributeValues' => [
                    ':before_version' => $this->marshaler->marshalValue($version),
                    ':after_version' => $this->marshaler->marshalValue($version + 1),
                ],
                'ConditionExpression' => "#version=:before_version"
            ]
        ];
        if ($aggregate !== null) {
            $payload = $this->snapshotSerializer->serialize($aggregate);
            $update['UpdateExpression'] = 'SET #payload=:payload, #seq_nr=:seq_nr, #version=:after_version';
            $update['ExpressionAttributeNames']['#seq_nr'] = 'seq_nr';
            $update['ExpressionAttributeValues'][':seq_nr'] = $this->marshaler->marshalValue($seqNr);
            $update['ExpressionAttributeNames'][':payload'] = $this->marshaler->marshalValue($payload);
        }
        return $update;
    }

    private function putJournal(Event $event): array {
        $pkey = $this->keyResolver->resolvePartitionKey($event->getAggregateId(), $this->shardCount);
        $skey = $this->keyResolver->resolveSortKey($event->getAggregateId(), $event->getSequenceNumber());
        $payload = $this->eventSerializer->serialize($event);
        return [
            'Put' => [
                'TableName' => $this->journalTableName,
                'Item' => $this->marshaler->marshalItem([
                    'pkey' => $pkey,
                    'skey' => $skey,
                    'aid' => $event->getAggregateId()->asString(),
                    'seq_nr' => $event->getSequenceNumber(),
                    'payload' => $payload,
                    'occurred_at' => $event->getOccurredAt(),
                ]),
                'ConditionExpression' => 'attribute_not_exists(pkey) AND attribute_not_exists(skey)'
            ]
        ];
    }

    private function createEventAndSnapshot(Event $event, Aggregate $aggregate): void {
        $putJournal = $this->putJournal($event);
        $putSnapshot = $this->putSnapshot($event, 0, $aggregate);
        $transactItems = ['TransactItems' => [$putJournal, $putSnapshot]];
        // TODO: keepSnapshot
        $this->client->transactWriteItems($transactItems);
    }

    private function updateEventAndSnapshotOpt(Event $event, int $version, ?Aggregate $aggregate): void {
        $putJournal = $this->putJournal($event);
        $updateSnapshot = $this->updateSnapshot($event, 0, $version, $aggregate);
        $transactItems = ['TransactItems' => [$putJournal, $updateSnapshot]];
        $this->client->transactWriteItems($transactItems);
    }


    public function persistEvent(Event $event, int $version): void {
        if ($event->isCreated()) throw new IllegalArgumentException('event is created type');
        $this->updateEventAndSnapshotOpt($event, $version, null);
        // TODO: tryPurgeExcessSnapshots
    }

    public function persistEventAndSnapshot(Event $event, Aggregate $aggregate): void {
        if ($event->isCreated()) {
            $this->createEventAndSnapshot($event, $aggregate);
        } else {
            $this->updateEventAndSnapshotOpt($event, $aggregate->getVersion(), $aggregate);
            // TODO: tryPurgeExcessSnapshots
        }
    }

    /**
     * @throws Exception
     */
    public function getLatestSnapshotById(AggregateId $aggregateId): ?Aggregate {
        $request = [
            'TableName' => $this->snapshotTableName,
            'IndexName' => $this->snapshotAidIndexName,
            'KeyConditionExpression' => '#aid = :aid AND #seq_nr = :seq_nr',
            'ExpressionAttributeNames' => [
                '#aid' => 'aid',
                '#seq_nr' => 'seq_nr'
            ],
            'ExpressionAttributeValues' => $this->marshaler->marshalItem([
                ':aid' => $aggregateId->asString(),
                ':seq_nr' => 0
            ]),
            'Limit' => 1,
        ];
        $response = $this->client->query($request);
        if ($response->count() == 0) {
            return null;
        } else {
            $item = $response['Items'][0];
            $version = $item['version']['N'];
            $payload = $item['payload']['B'];
            $aggregateMap = $this->snapshotSerializer->deserialize($payload);
            $aggregate = ($this->snapshotConverter)($aggregateMap);
            if ($aggregate instanceof Aggregate) {
                return $aggregate->withVersion($version);
            } else {
                throw new Exception("Aggregate インターフェースを実装していないオブジェクトです。");
            }
        }
    }

    public function getEventsByIdSinceSequenceNumber(AggregateId $aggregateId, int $sequenceNumber): array {
        $request = [
            'TableName' => $this->journalTableName,
            'IndexName' => $this->journalAidIndexName,
            'KeyConditionExpression' => '#aid = :aid AND #seq_nr >= :seq_nr',
            'ExpressionAttributeNames' => [
                '#aid' => 'aid',
                '#seq_nr' => 'seq_nr'
            ],
            'ExpressionAttributeValues' => $this->marshaler->marshalItem([
                ':aid' => $aggregateId->asString(),
                ':seq_nr' => $sequenceNumber
            ]),
        ];
        $response = $this->client->query($request);
        $result = [];
        foreach ($response['Items'] as $item) {
            $payload = $item['payload']['B'];
            $payloadMap = $this->eventSerializer->deserialize($payload);
            $event = ($this->eventConverter)($payloadMap);
            $result[] = $event;
        }
        return $result;
    }
}

