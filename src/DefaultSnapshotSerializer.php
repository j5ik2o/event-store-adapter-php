<?php declare(strict_types=1);

namespace J5ik2o\EventStoreAdapterPhp;

final class DefaultSnapshotSerializer implements SnapshotSerializer {

    public function serialize(Aggregate $aggregate): string {
        return json_encode($aggregate, JSON_UNESCAPED_UNICODE);
    }

    public function deserialize(string $data): array {
        return json_decode($data, true);
    }
}