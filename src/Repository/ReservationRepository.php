<?php

namespace App\Repository;

use App\Entity\Reservation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Reservation|null find($id, $lockMode = null, $lockVersion = null)
 * @method Reservation|null findOneBy(array $criteria, array $orderBy = null)
 * @method Reservation[]    findAll()
 * @method Reservation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ReservationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reservation::class);
    }

    public function hasOverlapForCar(
        int $carId,
        \DateTimeInterface $start,
        \DateTimeInterface $end,
        ?int $excludeReservationId = null
    ): bool {
        $qb = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.car = :carId')
            ->andWhere('r.status IN (:activeStatuses)')
            ->andWhere('r.startDate < :end')
            ->andWhere('r.endDate > :start')
            ->setParameter('carId', $carId)
            ->setParameter('activeStatuses', [
                Reservation::STATUS_PENDING,
                Reservation::STATUS_ACCEPTED,
            ])
            ->setParameter('start', $start)
            ->setParameter('end', $end);

        if ($excludeReservationId !== null) {
            $qb->andWhere('r.id != :excludeReservationId')
                ->setParameter('excludeReservationId', $excludeReservationId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }
}