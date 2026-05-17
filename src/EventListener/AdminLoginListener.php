<?php

namespace App\EventListener;

use App\Entity\AdminLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

class AdminLoginListener
{
    public function __construct(
        private EntityManagerInterface $em,
        private RequestStack $requestStack,
    ) {}

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof User) {
            return;
        }

        $roles = $user->getRoles();

        $req = $this->requestStack->getCurrentRequest();
        $ip = $req?->getClientIp();
        $ua = $req?->headers->get('User-Agent');

        $log = new AdminLog();
        $log->setAdmin($user); // zalogowany user
        $log->setAction('auth.login_success');
        $log->setEntity('User');
        $log->setEntityId($user->getId());
        $log->setIp($ip);
        $log->setDetails([
            'email' => $user->getEmail(),
            'roles' => $roles,
            'ua'    => $ua,
        ]);

        $this->em->persist($log);
        $this->em->flush();
    }

    public function onLoginFailure(LoginFailureEvent $event): void
    {
        $req = $event->getRequest();
        $ip  = $req->getClientIp();
        $ua  = $req->headers->get('User-Agent');

        $email =
            (string) ($req->request->get('email')
            ?? $req->request->get('_username')
            ?? $req->request->get('username')
            ?? '');

        $exception = $event->getException();
        $message = $exception ? $exception->getMessageKey() : 'login_failed';

        $log = new AdminLog();
        $log->setAdmin(null); // brak usera przy failure
        $log->setAction('auth.login_failed');
        $log->setEntity('User');
        $log->setEntityId(null);
        $log->setIp($ip);
        $log->setDetails([
            'email' => $email,
            'error' => $message,
            'ua'    => $ua,
        ]);

        $this->em->persist($log);
        $this->em->flush();
    }
}
