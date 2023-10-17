<?php

declare(strict_types=1);

namespace J5ik2o\EventStoreAdapterPhp;

/**
 * This is an interface for serializing and deserializing snapshots.
 */
interface SnapshotSerializer {
    /**
     * Serializes the snapshot.
     *
     * @param Aggregate $aggregate
     * @return string
     */
    public function serialize(Aggregate $aggregate): string;

    /**
     * Deserializes the snapshot.
     *
     * @param string $data
     * @return array<string, object>
     */
    public function deserialize(string $data): array;
}
