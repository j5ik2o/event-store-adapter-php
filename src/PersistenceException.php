<?php

namespace J5ik2o\EventStoreAdapterPhp;

use Exception;
use Throwable;

final class PersistenceException extends Exception {
    public function __construct(string $message = "", int $code = 0, Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}
