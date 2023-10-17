<?php

declare(strict_types=1);

namespace J5ik2o\EventStoreAdapterPhp;

/**
 * This is an interface for representing aggregates.
 */
interface Aggregate extends \JsonSerializable {
    /**
     * Returns the aggregate ID.
     *
     * @return AggregateId
     */
    public function getId(): AggregateId;

    /**
     * Returns the sequence number.
     *
     * @return int
     */
    public function getSequenceNumber(): int;

    /**
     * Returns the version.
     *
     * @return int
     */
    public function getVersion(): int;

    /**
     * Sets the version.
     *
     * @param int $version
     * @return Aggregate
     */
    public function withVersion(int $version): Aggregate;

    public function equals(Aggregate $other): bool;
}
