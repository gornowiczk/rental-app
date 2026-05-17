<?php

namespace App\Repository;

use App\Entity\Document;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class DocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Document::class);
    }

    public function latestFor(User $user, int $limit = 10): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.user = :u')->setParameter('u', $user)
            ->orderBy('d.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()->getResult();
    }
}
