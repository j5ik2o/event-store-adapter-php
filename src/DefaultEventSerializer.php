<?php

class DefaultEventSerializer implements EventSerializer {
    public function serialize(Event $event): string {
        return json_encode($event);
    }

    public function deserialize(string $data): array {
        return json_decode($data, true);
    }
}

