<?php

declare(strict_types=1);

namespace J5ik2o\EventStoreAdapterPhp;

use DateTimeImmutable;

/**
 * This is an interface for representing events.
 */
interface Event extends \JsonSerializable {
    /**
     * Returns the ID.
     *
     * @return string
     */
    public function getId(): string;

    /**
     * Returns the type name.
     *
     * @return string
     */
    public function getTypeName(): string;

    /**
     * Returns the aggregate ID.
     *
     * @return AggregateId
     */
    public function getAggregateId(): AggregateId;

    /**
     * Returns the sequence number.
     *
     * @return int
     */
    public function getSequenceNumber(): int;

    /**
     * Determines whether it is a generated event.
     *
     * @return bool
     */
    public function isCreated(): bool;

    /**
     * Returns the occurred at.
     *
     * @return DateTimeImmutable
     */
    public function getOccurredAt(): DateTimeImmutable;
}
