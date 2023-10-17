<?php

declare(strict_types=1);

namespace J5ik2o\EventStoreAdapterPhp;

/**
 * This is an interface for representing aggregate IDs.
 */
interface AggregateId extends \JsonSerializable {
    /**
     * Returns the type name.
     *
     * @return string
     */
    public function getTypeName(): string;

    /**
     * Returns the value.
     *
     * @return string
     */
    public function getValue(): string;

    /**
     * Returns the string representation.
     *
     * @return string
     */
    public function asString(): string;

    /**
     * @param AggregateId $other
     * @return bool
     */
    public function equals(AggregateId $other): bool;
}
