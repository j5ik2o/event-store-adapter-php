<?php

namespace J5ik2o\EventStoreAdapterPhp\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;

class UserAccountRepositoryTest extends TestCase {
    public function setUp(): void {
        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            throw new RuntimeException($errstr . " on line " . $errline . " in file " . $errfile);
        });
    }

    public function tearDown(): void {
        restore_error_handler();
    }

    public function testPersist(): void {
        $this->assertTrue(true);
    }
}