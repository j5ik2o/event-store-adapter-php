<?php

namespace J5ik2o\EventStoreAdapterPhp\Internal;

use Aws\DynamoDb\Exception\DynamoDbException;
use Aws\DynamoDb\Marshaler;
use Aws\Result;
use Exception;
use J5ik2o\EventStoreAdapterPhp\Aggregate;
use J5ik2o\EventStoreAdapterPhp\AggregateId;
use J5ik2o\EventStoreAdapterPhp\DefaultEventSerializer;
use J5ik2o\EventStoreAdapterPhp\DefaultKeyResolver;
use J5ik2o\EventStoreAdapterPhp\DefaultSnapshotSerializer;
use J5ik2o\EventStoreAdapterPhp\Event;
use J5ik2o\EventStoreAdapterPhp\EventSerializer;
use J5ik2o\EventStoreAdapterPhp\KeyResolver;
use J5ik2o\EventStoreAdapterPhp\OptimisticLockException;
use J5ik2o\EventStoreAdapterPhp\PersistenceException;
use J5ik2o\EventStoreAdapterPhp\SerializationException;
use J5ik2o\EventStoreAdapterPhp\SnapshotSerializer;
use Throwable;

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
     * Generates a request to put a snapshot.
     *
     * @param Event $event
     * @param int $seqNr
     * @param Aggregate $aggregate
     * @return array{
     *   Put: array{
     *     TableName: string,
     *     Item: array<string, mixed>,
     *     ConditionExpression: string
     *   }
     * }
     * @throws SerializationException
     */
    public function generatePutSnapshotRequest(Event $event, int $seqNr, Aggregate $aggregate): array {
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
     * Generates a request to update a snapshot.
     *
     * @param Event $event
     * @param int $seqNr
     * @param int $version
     * @param Aggregate|null $aggregate
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
     * @throws SerializationException
     */
    public function generateUpdateSnapshotRequest(Event $event, int $seqNr, int $version, ?Aggregate $aggregate): array {
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
     * Generates a request to append a journal.
     *
     * @param Event $event
     * @return array{
     *   Put: array{
     *     TableName: string,
     *     Item: array<string, mixed>,
     *     ConditionExpression: string
     *   }
     * }
     * @throws SerializationException
     */
    public function generatePutJournalRequest(Event $event): array {
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
     * Generates a request to get a snapshot.
     *
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
    public function generateGetSnapshot(AggregateId $aggregateId): array {
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
     * Generates a request to get events.
     *
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
    public function generateGetEventsRequest(AggregateId $aggregateId, int $sequenceNumber): array {
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

    public function getSnapshotCountRequest(AggregateId $aggregateId): array {
        return [
            'TableName' => $this->snapshotTableName,
            'IndexName' => $this->snapshotAidIndexName,
            'KeyConditionExpression' => '#aid = :aid',
            'ExpressionAttributeNames' => [
                '#aid' => 'aid',
            ],
            'ExpressionAttributeValues' => $this->marshaler->marshalItem([
                ':aid' => $aggregateId->asString(),
            ]),
            'Select' => 'COUNT',
        ];
    }

    public function getLastSnapshotKeysRequest(AggregateId $aggregateId, int $limit): array {
        $names = [
            '#aid' => 'aid',
            '#seq_nr' => 'seq_nr',
        ];
        $values = $this->marshaler->marshalItem([
            ':aid' => $aggregateId->asString(),
            ':seq_nr' => 0,
        ]);
        $request = [
            'TableName' => $this->snapshotTableName,
            'IndexName' => $this->snapshotAidIndexName,
            'KeyConditionExpression' => '#aid = :aid AND #seq_nr > :seq_nr',
            'ExpressionAttributeNames' => $names,
            'ExpressionAttributeValues' => $values,
            'ScanIndexForward' => false,
            'Limit' => $limit,
        ];
        if ($this->deleteTtlInMillSec !== 0) {
            $request['FilterExpression'] = '#ttl = :ttl';
            $request['ExpressionAttributeNames']['#ttl'] = 'ttl';
            $request['ExpressionAttributeValues'][':ttl'] = $this->marshaler->marshalValue(0);
        }
        return $request;
    }

    /**
     * Converts an event from a payload.
     *
     * @param string $payload
     * @return Event
     * @throws SerializationException
     */
    public function convertFromPayloadToEvent(string $payload): Event {
        $payloadMap = $this->eventSerializer->deserialize($payload);
        return ($this->eventConverter)($payloadMap);
    }

    /**
     * Converts a snapshot from a payload.
     *
     * @param string $payload
     * @return Aggregate
     * @throws SerializationException
     */
    public function convertToSnapshot(string $payload): Aggregate {
        $payloadMap = $this->snapshotSerializer->deserialize($payload);
        return ($this->snapshotConverter)($payloadMap);
    }

    /**
     * @param Result $response
     * @return array<array{ pkey: string, skey: string }>
     */
    // @phpstan-ignore-next-line
    public function convertFromResponseToPkeySkeyArray(Result $response): array {
        $result = [];
        $items = $response->get('Items') ?? [];
        if (is_iterable($items)) {
            foreach ($items as $item) {
                $pkey = $item['pkey']['S'];
                $skey = $item['skey']['S'];
                $result[] = [
                    'pkey' => (string)$pkey,
                    'skey' => (string)$skey,
                ];
            }
        }
        return $result;
    }

    /**
     * @param Result $response
     * @return array<Event>
     * @throws SerializationException
     */
    // @phpstan-ignore-next-line
    public function convertFromResponseToEvents(Result $response): array {
        $result = [];
        $items = $response->get('Items') ?? [];
        if (is_iterable($items)) {
            foreach ($items as $item) {
                $payload = $item['payload']['S'];
                $result[] = $this->convertFromPayloadToEvent($payload);
            }
        }
        return $result;
    }


    /**
     * @param Result $response
     * @return Aggregate|null
     * @throws SerializationException
     */
    // @phpstan-ignore-next-line
    public function convertFromResponseToSnapshot(Result $response): ?Aggregate {
        if ($response->count() == 0) {
            return null;
        } else {
            if (is_array($response['Items']) && isset($response['Items'][0])) {
                $item = $response['Items'][0];
                $version = $item['version']['N'];
                $payload = $item['payload']['S'];
                $aggregate = $this->convertToSnapshot($payload);
                if ($aggregate instanceof Aggregate) {
                    return $aggregate->withVersion((int)$version);
                }
            }
            throw new SerializationException("Failed to deserialize aggregate");
        }
    }

    /**
     * @param Exception $ex
     * @param Event $event
     * @param int $version
     * @throws OptimisticLockException
     * @throws PersistenceException
     */
    public function handleWriteException(Exception $ex, Event $event, int $version): void {
        if ($ex instanceof DynamoDbException) {
            if ($ex->getAwsErrorCode() === 'TransactionCanceledException') {
                $message = $ex->getAwsErrorMessage() ?? '';
                if (str_contains($message, 'ConditionalCheckFailed')) {
                    throw new OptimisticLockException("Optimistic lock failure while updating event with ID: {$event->getId()}, Version: $version. AWS Error: $message");
                }
            }
        }
        throw new PersistenceException(message: "An error occurred while attempting to update event with ID: {$event->getId()}, Version: $version and its corresponding snapshot.", previous: $ex);
    }

    /**
     * @param AggregateId $aggregateId
     * @param Throwable $ex
     * @return PersistenceException
     */
    public function convertToGetLatestSnapshotException(AggregateId $aggregateId, Throwable $ex): PersistenceException {
        return new PersistenceException(message: "An error occurred while attempting to retrieve the latest snapshot for aggregate with ID: {$aggregateId->getValue()}.", previous: $ex);
    }

    /**
     * @param AggregateId $aggregateId
     * @param int $sequenceNumber
     * @param Throwable $ex
     * @return PersistenceException
     */
    public function convertToGetEventsException(AggregateId $aggregateId, int $sequenceNumber, Throwable $ex): PersistenceException {
        return new PersistenceException(message: "An error occurred while attempting to retrieve events for aggregate with ID: {$aggregateId->getValue()} since sequence number: $sequenceNumber.", previous: $ex);
    }

    public function generateDeleteSnapshotRequests(array $keys): array {
            return [
                'DeleteRequest' => [
                    'Key' => $this->marshaler->marshalItem([
                        'pkey' => $keys['pkey'],
                        'skey' => $keys['skey'],
                    ]),
                ],
            ];
    }
}
