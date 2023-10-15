<?php declare(strict_types=1);

namespace J5ik2o\EventStoreAdapterPhp;

interface SnapshotSerializer {
    /**
     * @param Aggregate $aggregate
     * @return string
     */
    public function serialize(Aggregate $aggregate): string;

    /**
     * @param string $data
     * @return array<string, object>
     */
    public function deserialize(string $data): array;
}