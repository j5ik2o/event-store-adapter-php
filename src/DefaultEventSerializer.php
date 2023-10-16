<?php

declare(strict_types=1);

namespace J5ik2o\EventStoreAdapterPhp;

final class DefaultEventSerializer implements EventSerializer {
    /**
     * @throws SerializationException
     */
    public function serialize(Event $event): string {
        $result = json_encode($event, JSON_UNESCAPED_UNICODE);
        if (!$result) {
            throw new SerializationException("Failed to serialize aggregate");
        }
        return $result;
    }

    /**
     * @throws SerializationException
     */
    public function deserialize(string $data): array {
        $result = json_decode($data, true);
        if (!is_array($result)) {
            throw new SerializationException("Failed to deserialize aggregate");
        }
        return $result;
    }
}
