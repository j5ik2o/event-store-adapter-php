<?php

declare(strict_types=1);

namespace J5ik2o\EventStoreAdapterPhp;

interface EventSerializer {
    /**
     * @param Event $event
     * @return string
     */
    public function serialize(Event $event): string;

    /**
     * @param string $data
     * @return array<string, object> $map
     */
    public function deserialize(string $data): array;
}
