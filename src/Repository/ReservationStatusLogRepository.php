<?php

namespace App\Repository;

use App\Entity\ReservationStatusLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class ReservationStatusLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReservationStatusLog::class);
    }

    /**
     * @return ReservationStatusLog[]
     */
    public function findForReservation(int $reservationId): array
    {
        return $this->createQueryBuilder('l')
            ->join('l.reservation', 'r')
            ->andWhere('r.id = :id')
            ->setParameter('id', $reservationId)
            ->orderBy('l.changedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
