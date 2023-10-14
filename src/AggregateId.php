<?php

interface AggregateId {
    public function getTypeName(): string;

    public function getValue(): string;

    public function asString(): string;
}