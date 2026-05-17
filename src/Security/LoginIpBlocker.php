<?php

namespace App\Security;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class LoginIpBlocker
{
    public function __construct(
        private CacheInterface $cache
    ) {}

    private function key(string $ip): string
    {
        return 'login_ip_block:' . $ip;
    }

    public function isBlocked(?string $ip): bool
    {
        if (!$ip) {
            return false;
        }
        return (bool) $this->cache->get($this->key($ip), fn() => false);
    }

    public function block(?string $ip, int $ttlSeconds = 900): void
    {
        if (!$ip) {
            return;
        }

        $this->cache->get($this->key($ip), function (ItemInterface $item) use ($ttlSeconds) {
            $item->expiresAfter($ttlSeconds);
            return true;
        });
    }

    public function unblock(?string $ip): void
    {
        if (!$ip) {
            return;
        }
        $this->cache->delete($this->key($ip));
    }
}
