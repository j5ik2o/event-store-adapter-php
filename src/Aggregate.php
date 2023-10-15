<?php declare(strict_types=1);

interface Aggregate {
    public function getId(): AggregateId;

    public function getSequenceNumber(): mixed;

    public function getVersion(): int;

    public function withVersion($version): Aggregate;
}