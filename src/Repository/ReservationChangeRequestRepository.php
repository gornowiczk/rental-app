<?php

namespace App\Repository;

use App\Entity\Reservation;
use App\Entity\ReservationChangeRequest;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ReservationChangeRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReservationChangeRequest::class);
    }

    public function hasPendingForReservation(Reservation $reservation): bool
    {
        $count = (int) $this->createQueryBuilder('rcr')
            ->select('COUNT(rcr.id)')
            ->andWhere('rcr.reservation = :reservation')
            ->andWhere('rcr.status = :status')
            ->setParameter('reservation', $reservation)
            ->setParameter('status', ReservationChangeRequest::STATUS_PENDING)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    public function findPendingForOwner(int $ownerId): array
    {
        return $this->createQueryBuilder('rcr')
            ->join('rcr.reservation', 'r')
            ->join('r.car', 'c')
            ->join('c.owner', 'o')
            ->andWhere('o.id = :ownerId')
            ->andWhere('rcr.status = :status')
            ->setParameter('ownerId', $ownerId)
            ->setParameter('status', ReservationChangeRequest::STATUS_PENDING)
            ->orderBy('rcr.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findForReservation(Reservation $reservation): array
    {
        return $this->createQueryBuilder('rcr')
            ->andWhere('rcr.reservation = :reservation')
            ->setParameter('reservation', $reservation)
            ->orderBy('rcr.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}