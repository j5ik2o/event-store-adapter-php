# event-store-adapter-php

[![CI](https://github.com/j5ik2o/event-store-adapter-php/actions/workflows/ci.yml/badge.svg)](https://github.com/j5ik2o/event-store-adapter-js/actions/workflows/ci.yml)
[![Renovate](https://img.shields.io/badge/renovate-enabled-brightgreen.svg)](https://renovatebot.com)
[![License](https://img.shields.io/badge/License-MIT-blue.svg)](https://opensource.org/licenses/MIT)
[![](https://tokei.rs/b1/github/j5ik2o/event-store-adapter-php)](https://github.com/XAMPPRocky/tokei)

This library is designed to turn DynamoDB into an Event Store for CQRS/Event Sourcing.

The Status is WIP.

## Usage

You can easily implement an Event Sourcing-enabled repository using EventStore.

```php
final class UserAccountRepository {
  private readonly EventStore $eventStore;

  public function __construct(EventStore $eventStore) {
    $this->eventStore = $eventStore;
  }

  public function storeEvent(UserAccountEvent $event, int $version): void {
    $this->eventStore->persistEvent($event, $version);
  }

  public function storeEventAndSnapshot(UserAccountEvent $event, UserAccount $userAccount): void {
    $this->eventStore->persistEventAndSnapshot($event, $userAccount);
  }

  public function findById(UserAccountId $id): ?UserAccount {
    $latestSnapshot = $this->eventStore->getLatestSnapshotById($id);
    if ($latestSnapshot === null) {
      return null;
    }
    $events = $this->eventStore->getEventsByIdSinceSequenceNumber($id, $latestSnapshot->getSequenceNumber());
    return UserAccount::replay($events, $latestSnapshot);
  }
}
```

The following is an example of the repository usage.

```php
$eventStore = new EventStoreFactory->create(
  $client,
  $journalTableName,
  $snapshotTableName,
  $journalAidIndexName,
  $snapshotAidIndexName,
  $shardCount,
  $eventConverter,
  $snapshotConverter,
);

$userAccountRepository = new UserAccountRepository($eventStore);

$userAccountId = new UserAccountId();

// Generates a new aggregate
[$userAccount1, $created] = UserAccount::create($userAccountId, "test-1");
// Store the snapshot and event at first
$userAccountRepository->storeEventAndSnapshot($created, $userAccount1);

// Replay if necessary
$userAccount2 = $userAccountRepository->findById($userAccountId);
// Executes business logic
[$userAccount3, $renamed] = $userAccount2->rename("test-2");
// Store the event only
$userAccountRepository->storeEvent($renamed, $userAccount3->getVersion());
```

## Table Specifications

See [docs/DATABASE_SCHEMA.md](docs/DATABASE_SCHEMA.md).

## CQRS/Event Sourcing Example

TODO

## License.

MIT License. See [LICENSE](LICENSE) for details.

## Other language implementations

- [for Java](https://github.com/j5ik2o/event-store-adapter-java)
- [for Scala](https://github.com/j5ik2o/event-store-adapter-scala)
- [for Kotlin](https://github.com/j5ik2o/event-store-adapter-kotlin)
- [for Rust](https://github.com/j5ik2o/event-store-adapter-rs)
- [for Go](https://github.com/j5ik2o/event-store-adapter-go)
- [for JavaScript/TypeScript](https://github.com/j5ik2o/event-store-adapter-js)
- [for .NET](https://github.com/j5ik2o/event-store-adapter-dotnet)
- [for PHP](https://github.com/j5ik2o/event-store-adapter-php)
