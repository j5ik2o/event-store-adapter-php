<?php

namespace J5ik2o\EventStoreAdapterPhp\Tests;

use Throwable;
use Exception;

class AlreadyRenamedException extends Exception {
    public function __construct($message = "", $code = 0, Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}