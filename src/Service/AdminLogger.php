<?php

namespace App\Service;

use App\Entity\AdminLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Bundle\SecurityBundle\Security;

class AdminLogger
{
    public function __construct(
        private EntityManagerInterface $em,
        private Security $security,
        private RequestStack $requestStack,
    ) {}

    /**
     * $entity     np. "Reservation", "User", "Car" (albo null)
     * $entityId   np. 123 (albo null)
     * $details    dowolna tablica (albo null)
     */
    public function log(string $action, ?string $entity = null, ?int $entityId = null, ?array $details = null): void
    {
        $actor = $this->security->getUser();
        if (!$actor instanceof User) {
            // brak zalogowanego usera => nie logujemy (np. anon, CLI, itp.)
            return;
        }

        $req = $this->requestStack->getCurrentRequest();

        $log = new AdminLog();
        $log->setAdmin($actor);
        $log->setAction($action);
        $log->setEntity($entity);
        $log->setEntityId($entityId);
        $log->setIp($req?->getClientIp());
        $log->setDetails($details);

        $this->em->persist($log);
        $this->em->flush();
    }
}
