<?php

namespace J5ik2o\EventStoreAdapterPhp\Tests;

use J5ik2o\EventStoreAdapterPhp\Event;

interface UserAccountEvent extends Event {
    public function getAggregateId(): UserAccountId;
}

