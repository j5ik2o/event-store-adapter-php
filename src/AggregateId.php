<?php declare(strict_types=1);

interface AggregateId {
    public function getTypeName(): string;

    public function getValue(): string;

    public function asString(): string;
}