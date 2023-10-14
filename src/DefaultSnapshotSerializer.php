<?php

class DefaultSnapshotSerializer implements SnapshotSerializer {

    public function serialize(Aggregate $aggregate): string {
        return json_encode($aggregate);
    }

    public function deserialize(string $data): array {
        return json_decode($data, true);
    }
}