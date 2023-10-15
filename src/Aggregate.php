<?php declare(strict_types=1);

namespace J5ik2o\EventStoreAdapterPhp;
interface Aggregate {
    public function getId(): AggregateId;

    public function getSequenceNumber(): int;

    public function getVersion(): int;

    public function withVersion(int $version): Aggregate;
}