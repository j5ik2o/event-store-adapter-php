<?php

declare(strict_types=1);

namespace J5ik2o\EventStoreAdapterPhp;

interface EventStoreOptions {
    public function withKeepSnapshot(bool $keepSnapshot): object;

    public function withDeleteTtl(int $deleteTtlInMillSec): object;

    public function withKeepSnapshotCount(int $keepSnapshotCount): object;

    public function withKeyResolver(KeyResolver $keyResolver): object;

    public function withEventSerializer(EventSerializer $eventSerializer): object;

    public function withSnapshotSerializer(SnapshotSerializer $snapshotSerializer): object;
}
