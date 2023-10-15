<?php declare(strict_types=1);

namespace J5ik2o\EventStoreAdapterPhp;

use Throwable;
use Exception;

class IllegalArgumentException extends Exception {
    public function __construct($message = "", $code = 0, Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}