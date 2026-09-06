<?php

namespace App\EventListener;

use App\Entity\AdminLog;
use App\Security\LoginIpBlocker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Contracts\Cache\CacheInterface;

class LoginBlockListener
{
    public function __construct(
        private LoginIpBlocker $blocker,
        private EntityManagerInterface $em,
        private RequestStack $requestStack,
        private UrlGeneratorInterface $urlGenerator,
        private CacheInterface $cache
    ) {}

    /**
     * Blokuj jeszcze przed weryfikacją hasła (najlepsze miejsce).
     */
    public function onCheckPassport(CheckPassportEvent $event): void
    {
        $req = $this->requestStack->getCurrentRequest();
        if (!$req) {
            return;
        }

        if ($req->attributes->get('_route') !== 'app_login') {
            return;
        }

        $ip = $req->getClientIp();
        if (!$ip) {
            return;
        }

        if ($this->blocker->isBlocked($ip)) {
            // Od razu przerwij logowanie i wróć na login z komunikatem
            $req->getSession()?->getFlashBag()->add('danger', 'Zbyt wiele prób logowania. Spróbuj ponownie za 15 minut.');
            $event->setResponse(new RedirectResponse($this->urlGenerator->generate('app_login')));
        }
    }

    /**
     * Po failed login: zlicz i ewentualnie zablokuj IP.
     */
    public function onLoginFailure(LoginFailureEvent $event): void
    {
        $req = $event->getRequest();
        $ip = $req->getClientIp();
        if (!$ip) {
            return;
        }

        // licznik: 10 minut
        $key = 'login_fail_count:' . $ip;

        $count = $this->cache->get($key, function (\Symfony\Contracts\Cache\ItemInterface $item) {
            $item->expiresAfter(600);
            return 0;
        });

        $count = (int) $count + 1;

        // zapisz z powrotem (prosty sposób)
        $this->cache->delete($key);
        $this->cache->get($key, function (\Symfony\Contracts\Cache\ItemInterface $item) use ($count) {
            $item->expiresAfter(600);
            return $count;
        });

        // Blokada IP po 8 nieudanych próbach logowania.
        if ($count >= 8) {
            $this->blocker->block($ip, 900);

            $log = new AdminLog();
            $log->setAdmin(null);
            $log->setAction('security.ip_blocked');
            $log->setEntity('User');
            $log->setEntityId(null);
            $log->setIp($ip);
            $log->setDetails([
                'reason' => 'too_many_failed_logins',
                'window_seconds' => 600,
                'fails' => $count,
                'blocked_seconds' => 900,
            ]);

            $this->em->persist($log);
            $this->em->flush();
        }
    }
}
