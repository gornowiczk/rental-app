<?php

namespace App\Repository;

use App\Entity\Invoice;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Invoice>
 */
final class InvoiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Invoice::class);
    }

    public function findOneByReservationId(int $reservationId): ?Invoice
    {
        return $this->createQueryBuilder('i')
            ->join('i.reservation', 'r')
            ->andWhere('r.id = :rid')
            ->setParameter('rid', $reservationId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
