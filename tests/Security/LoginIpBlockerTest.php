<?php

namespace App\Tests;

use App\Security\LoginIpBlocker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class LoginIpBlockerTest extends TestCase
{
    public function testIpIsNotBlockedInitially(): void
    {
        $cache = new ArrayAdapter();
        $blocker = new LoginIpBlocker($cache);

        self::assertFalse(
            $blocker->isBlocked('192.168.1.10')
        );
    }

    public function testIpCanBeBlocked(): void
    {
        $cache = new ArrayAdapter();
        $blocker = new LoginIpBlocker($cache);

        $blocker->block(
            '192.168.1.20',
            900
        );

        self::assertTrue(
            $blocker->isBlocked('192.168.1.20')
        );
    }

    public function testIpCanBeUnblocked(): void
    {
        $cache = new ArrayAdapter();
        $blocker = new LoginIpBlocker($cache);

        $blocker->block(
            '192.168.1.30',
            900
        );

        self::assertTrue(
            $blocker->isBlocked('192.168.1.30')
        );

        $blocker->unblock(
            '192.168.1.30'
        );

        self::assertFalse(
            $blocker->isBlocked('192.168.1.30')
        );
    }

    public function testNullIpIsNeverBlocked(): void
    {
        $cache = new ArrayAdapter();
        $blocker = new LoginIpBlocker($cache);

        $blocker->block(null);

        self::assertFalse(
            $blocker->isBlocked(null)
        );
    }
}

