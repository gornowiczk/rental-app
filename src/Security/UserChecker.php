<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if ($user->isBlocked()) {
            $reason = trim((string) $user->getBlockedReason());
            $msg = $reason !== '' ? 'Konto zablokowane: ' . $reason : 'Konto zablokowane.';
            throw new CustomUserMessageAccountStatusException($msg);
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
        // nic
    }
}
