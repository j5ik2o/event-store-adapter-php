<?php

declare(strict_types=1);

namespace J5ik2o\EventStoreAdapterPhp;

/**
 * This is an interface for serializing and deserializing events.
 */
interface EventSerializer {
    /**
     * Serializes the event.
     *
     * @param Event $event
     * @return string
     */
    public function serialize(Event $event): string;

    /**
     * Deserializes the event.
     *
     * @param string $data
     * @return array<string, object> $map
     */
    public function deserialize(string $data): array;
}
