<?php

namespace J5ik2o\EventStoreAdapterPhp\Internal;

use Aws\DynamoDb\Marshaler;
use J5ik2o\EventStoreAdapterPhp\Aggregate;
use J5ik2o\EventStoreAdapterPhp\AggregateId;
use J5ik2o\EventStoreAdapterPhp\DefaultEventSerializer;
use J5ik2o\EventStoreAdapterPhp\DefaultKeyResolver;
use J5ik2o\EventStoreAdapterPhp\DefaultSnapshotSerializer;
use J5ik2o\EventStoreAdapterPhp\Event;
use J5ik2o\EventStoreAdapterPhp\EventSerializer;
use J5ik2o\EventStoreAdapterPhp\KeyResolver;
use J5ik2o\EventStoreAdapterPhp\SerializationException;
use J5ik2o\EventStoreAdapterPhp\SnapshotSerializer;

final class EventStoreSupport {
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

    // @phpstan-ignore-next-line
    private readonly bool $keepSnapshot;

    // @phpstan-ignore-next-line
    private readonly int $keepSnapshotCount;

    // @phpstan-ignore-next-line
    private readonly int $deleteTtlInMillSec;

    private readonly KeyResolver $keyResolver;
    private readonly EventSerializer $eventSerializer;
    private readonly SnapshotSerializer $snapshotSerializer;
    private readonly Marshaler $marshaler;

    public function __construct(
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
        $this->marshaler = new Marshaler();
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
    }

    /**
     * @return array{
     *   Put: array{
     *     TableName: string,
     *     Item: array<string, mixed>,
     *     ConditionExpression: string
     *   }
     * }
     */
    public function putSnapshot(Event $event, int $seqNr, Aggregate $aggregate): array {
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
                'ConditionExpression' => 'attribute_not_exists(pkey) AND attribute_not_exists(skey)',
            ],
        ];
    }


    /**
     * @return array{
     *   Update: array{
     *     TableName: string,
     *     UpdateExpression: string,
     *     Key: array<string, mixed>,
     *     ExpressionAttributeNames: array<string, string>,
     *     ExpressionAttributeValues: array<string, mixed>,
     *     ConditionExpression: string
     *   }
     * }
     */
    public function updateSnapshot(Event $event, int $seqNr, int $version, ?Aggregate $aggregate): array {
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
                    '#version' => 'version',
                ],
                'ExpressionAttributeValues' => [
                    ':before_version' => $this->marshaler->marshalValue($version),
                    ':after_version' => $this->marshaler->marshalValue($version + 1),
                ],
                'ConditionExpression' => "#version=:before_version",
            ],
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


    /**
     * @param Event $event
     * @return array{
     *   Put: array{
     *     TableName: string,
     *     Item: array<string, mixed>,
     *     ConditionExpression: string
     *   }
     * }
     */
    public function putJournal(Event $event): array {
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
                    'occurred_at' => $event->getOccurredAt()->getTimestamp() * 1000,
                ]),
                'ConditionExpression' => 'attribute_not_exists(pkey) AND attribute_not_exists(skey)',
            ],
        ];
    }

    /**
     * @param AggregateId $aggregateId
     * @return array{
     *  TableName: string,
     *  IndexName: string,
     *  KeyConditionExpression: string,
     *  ExpressionAttributeNames: array<string, string>,
     *  ExpressionAttributeValues: array<string, mixed>,
     *  Limit: int
     * }
     */
    public function getLatestSnapshotById(AggregateId $aggregateId): array {
        return [
            'TableName' => $this->snapshotTableName,
            'IndexName' => $this->snapshotAidIndexName,
            'KeyConditionExpression' => '#aid = :aid AND #seq_nr = :seq_nr',
            'ExpressionAttributeNames' => [
                '#aid' => 'aid',
                '#seq_nr' => 'seq_nr',
            ],
            'ExpressionAttributeValues' => $this->marshaler->marshalItem([
                ':aid' => $aggregateId->asString(),
                ':seq_nr' => 0,
            ]),
            'Limit' => 1,
        ];
    }

    /**
     * @param AggregateId $aggregateId
     * @param int $sequenceNumber
     * @return array{
     *  TableName: string,
     *  IndexName: string,
     *  KeyConditionExpression: string,
     *  ExpressionAttributeNames: array<string, string>,
     *  ExpressionAttributeValues: array<string, mixed>,
     * }
     */
    public function getEventsByIdSinceSequenceNumber(AggregateId $aggregateId, int $sequenceNumber): array {
        return [
            'TableName' => $this->journalTableName,
            'IndexName' => $this->journalAidIndexName,
            'KeyConditionExpression' => '#aid = :aid AND #seq_nr >= :seq_nr',
            'ExpressionAttributeNames' => [
                '#aid' => 'aid',
                '#seq_nr' => 'seq_nr',
            ],
            'ExpressionAttributeValues' => $this->marshaler->marshalItem([
                ':aid' => $aggregateId->asString(),
                ':seq_nr' => $sequenceNumber,
            ]),
        ];
    }

    /**
     * @throws SerializationException
     */
    public function convertToEvent(string $payload): Event {
        $payloadMap = $this->eventSerializer->deserialize($payload);
        return ($this->eventConverter)($payloadMap);
    }

    /**
     * @throws SerializationException
     */
    public function convertToSnapshot(string $payload): Aggregate {
        $payloadMap = $this->snapshotSerializer->deserialize($payload);
        return ($this->snapshotConverter)($payloadMap);
    }

}