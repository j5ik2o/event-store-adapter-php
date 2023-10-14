<?php

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