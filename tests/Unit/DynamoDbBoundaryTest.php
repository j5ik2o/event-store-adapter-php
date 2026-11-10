<?php

declare(strict_types=1);

namespace J5ik2o\EventStoreAdapterPhp\Tests;

use Aws\Api\Validator;
use Aws\Command;
use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Exception\DynamoDbException;
use Aws\DynamoDb\Marshaler;
use Aws\Result;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\RejectionException;
use J5ik2o\EventStoreAdapterPhp\Internal\EventStoreAsyncForDynamoDb;
use J5ik2o\EventStoreAdapterPhp\Internal\EventStoreSupport;
use J5ik2o\EventStoreAdapterPhp\OptimisticLockException;
use J5ik2o\EventStoreAdapterPhp\PersistenceException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DynamoDbBoundaryTest extends TestCase {
    private function support(int $ttl = 1000): EventStoreSupport {
        return new EventStoreSupport('journal', 'snapshot', 'journal-aid', 'snapshot-aid', 1, fn ($x) => $x, fn ($x) => $x, deleteTtlInMillSec: $ttl);
    }

    private function client(): BoundaryDynamoDbClient {
        return new BoundaryDynamoDbClient(['service' => 'dynamodb', 'version' => '2012-08-10', 'region' => 'us-east-1', 'credentials' => false]);
    }

    public function testDeleteRequestsContainExactlyTheSelectedKeys(): void {
        $support = $this->support();
        $selected = $support->convertFromResponseToPkeySkeyArray(new Result(['Items' => [
            ['pkey' => ['S' => 'p1'], 'skey' => ['S' => 's1']],
            ['pkey' => ['S' => 'p2'], 'skey' => ['S' => 's2']],
        ]]));
        $requests = $support->generateDeleteSnapshotRequests($selected);
        self::assertSame([
            ['DeleteRequest' => ['Key' => ['pkey' => ['S' => 'p1'], 'skey' => ['S' => 's1']]]],
            ['DeleteRequest' => ['Key' => ['pkey' => ['S' => 'p2'], 'skey' => ['S' => 's2']]]],
        ], $requests);
        $client = $this->client();
        (new Validator())->validate('BatchWriteItem', $client->getApi()->getOperation('BatchWriteItem')->getInput(), ['RequestItems' => ['snapshot' => $requests]]);
        self::assertSame([], $support->generateDeleteSnapshotRequests([]));
        self::assertSame([$requests[1]], $support->generateDeleteSnapshotRequests([7 => $selected[1]]));
    }

    public function testQueryValuesKeepTheMarshalerWireRepresentation(): void {
        $id = new UserAccountId('fixed-id');
        $support = $this->support();
        $marshaler = new Marshaler();
        $queries = [
            $support->generateGetSnapshot($id),
            $support->generateGetEventsRequest($id, -1),
            $support->generateGetEventsRequest($id, PHP_INT_MAX),
            $support->getSnapshotCountRequest($id),
            $support->getLastSnapshotKeysRequest($id, 2),
            $this->support(0)->getLastSnapshotKeysRequest($id, 2),
        ];
        $values = [
            [':aid' => $id->asString(), ':seq_nr' => 0],
            [':aid' => $id->asString(), ':seq_nr' => -1],
            [':aid' => $id->asString(), ':seq_nr' => PHP_INT_MAX],
            [':aid' => $id->asString()],
            [':aid' => $id->asString(), ':seq_nr' => 0, ':ttl' => 0],
            [':aid' => $id->asString(), ':seq_nr' => 0],
        ];
        $client = $this->client();
        foreach ($queries as $i => $query) {
            self::assertSame($marshaler->marshalItem($values[$i]), $query['ExpressionAttributeValues']);
            (new Validator())->validate('Query', $client->getApi()->getOperation('Query')->getInput(), $query);
        }
        self::assertSame('COUNT', $queries[3]['Select']);
        self::assertArrayNotHasKey('FilterExpression', $queries[5]);
    }

    /** @return iterable<string, array{string, mixed}> */
    public static function rejections(): iterable {
        $reasons = [
            'exception' => new \RuntimeException('failure'),
            'error' => new \Error('failure'),
            'string' => 'failure',
            'array' => ['failure' => 42],
            'null' => null,
            'dynamodb' => new DynamoDbException('denied', new Command('TransactWriteItems'), ['code' => 'AccessDeniedException']),
        ];
        foreach (['snapshot', 'events', 'create', 'update'] as $operation) {
            foreach ($reasons as $name => $reason) {
                yield "$operation $name" => [$operation, $reason];
            }
        }
    }

    #[DataProvider('rejections')]
    public function testRejectionPreservesItsReason(string $operation, mixed $reason): void {
        $client = $this->client();
        $client->next = Create::rejectionFor($reason);
        try {
            $this->invoke($client, $operation)->wait();
            self::fail('Expected PersistenceException');
        } catch (PersistenceException $exception) {
            $previous = $exception->getPrevious();
            if ($reason instanceof \Throwable) {
                self::assertSame($reason, $previous);
            } else {
                self::assertInstanceOf(RejectionException::class, $previous);
                self::assertSame($reason, $previous->getReason());
            }
        }
    }

    public function testConditionalWriteFailuresRemainOptimisticLockFailures(): void {
        foreach (['create', 'update'] as $operation) {
            $client = $this->client();
            $client->next = Create::rejectionFor(new DynamoDbException('cancelled', new Command('TransactWriteItems'), [
                'code' => 'TransactionCanceledException', 'message' => 'ConditionalCheckFailed',
            ]));
            try {
                $this->invoke($client, $operation)->wait();
                self::fail('Expected OptimisticLockException');
            } catch (OptimisticLockException $exception) {
                self::assertStringContainsString('ConditionalCheckFailed', $exception->getMessage());
            }
        }
    }

    public function testNormalQueryResults(): void {
        $client = $this->client();
        $client->next = Create::promiseFor(new Result([]));
        self::assertNull($this->invoke($client, 'snapshot')->wait());
        $client->next = Create::promiseFor(new Result(['Items' => []]));
        self::assertSame([], $this->invoke($client, 'events')->wait());
    }

    public function testPopulatedQueryResults(): void {
        $client = $this->client();
        $client->next = Create::promiseFor(new Result(['Items' => [['payload' => ['S' => '{}'], 'version' => ['N' => '5']]]]));
        $snapshot = $this->invoke($client, 'snapshot')->wait();
        self::assertInstanceOf(UserAccount::class, $snapshot);
        self::assertSame(5, $snapshot->getVersion());
        $client->next = Create::promiseFor(new Result(['Items' => [['payload' => ['S' => '{}']]]]));
        $events = $this->invoke($client, 'events')->wait();
        self::assertIsArray($events);
        self::assertCount(1, $events);
        self::assertInstanceOf(UserAccountCreated::class, $events[0]);
    }

    /** @return iterable<string, array{string, mixed}> */
    public static function invalidResults(): iterable {
        foreach (['snapshot', 'events'] as $operation) {
            yield "$operation string" => [$operation, 'invalid success'];
            yield "$operation array" => [$operation, ['Items' => []]];
        }
    }

    #[DataProvider('invalidResults')]
    public function testInvalidQuerySuccessIsRejected(string $operation, mixed $result): void {
        $client = $this->client();
        $client->next = Create::promiseFor($result);
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('DynamoDB query must resolve to Aws\\Result');
        $this->invoke($client, $operation)->wait();
    }

    private function invoke(BoundaryDynamoDbClient $client, string $operation): PromiseInterface {
        $id = new UserAccountId('fixed-id');
        $event = new UserAccountCreated('event-id', $id, 1, 'name', new \DateTimeImmutable('2020-01-01'));
        $store = new EventStoreAsyncForDynamoDb($client, 'journal', 'snapshot', 'journal-aid', 'snapshot-aid', 1, fn ($x) => $event, fn ($x) => new UserAccount($id, 1, 'name', 1));
        return match ($operation) {
            'snapshot' => $store->getLatestSnapshotById($id),
            'events' => $store->getEventsByIdSinceSequenceNumber($id, 1),
            'create' => $store->persistEventAndSnapshot($event, new UserAccount($id, 1, 'name', 1)),
            'update' => $store->persistEvent(new UserAccountRenamed('event-id', $id, 2, 'name', new \DateTimeImmutable('2020-01-01')), 1),
            default => throw new \InvalidArgumentException($operation),
        };
    }
}

final class BoundaryDynamoDbClient extends DynamoDbClient {
    public PromiseInterface $next;

    /** @param array<mixed> $args */
    public function __call($name, array $args): PromiseInterface {
        return $this->next;
    }
}
