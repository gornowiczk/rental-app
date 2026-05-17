<?php

namespace App\EventSubscriber;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class UserActivitySubscriber implements EventSubscriberInterface
{
    public function __construct(
        private Security $security,
        private EntityManagerInterface $em
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 0],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        // throttle: zapis max raz na 60 sekund
        $uow = $this->em->getUnitOfWork();
        $orig = $uow->getOriginalEntityData($user);
        $prev = $orig['lastSeenAt'] ?? null;

        $now = new \DateTimeImmutable();

        if ($prev instanceof \DateTimeInterface) {
            $diff = $now->getTimestamp() - $prev->getTimestamp();
            if ($diff < 60) {
                return;
            }
        }

        $user->setLastSeenAt($now);
        $this->em->flush();
    }
}
