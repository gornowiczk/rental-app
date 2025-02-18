<?php

namespace App\Repository;

use App\Entity\Car;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Car>
 */
class CarRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Car::class);
    }

    // Jeśli chcesz tylko znaleźć wszystkie samochody
    public function findAllCars()
    {
        return $this->findAll();
    }
    public function findCarsByFilters(array $filters, string $sortBy, string $order)
    {
        $qb = $this->createQueryBuilder('c');

        // Dodawanie filtrów
        if ($filters['brand']) {
            $qb->andWhere('c.brand = :brand')
                ->setParameter('brand', $filters['brand']);
        }

        if ($filters['model']) {
            $qb->andWhere('c.model = :model')
                ->setParameter('model', $filters['model']);
        }

        if ($filters['yearFrom']) {
            $qb->andWhere('c.year >= :yearFrom')
                ->setParameter('yearFrom', $filters['yearFrom']);
        }

        if ($filters['yearTo']) {
            $qb->andWhere('c.year <= :yearTo')
                ->setParameter('yearTo', $filters['yearTo']);
        }

        if ($filters['priceMin']) {
            $qb->andWhere('c.pricePerDay >= :priceMin')
                ->setParameter('priceMin', $filters['priceMin']);
        }

        if ($filters['priceMax']) {
            $qb->andWhere('c.pricePerDay <= :priceMax')
                ->setParameter('priceMax', $filters['priceMax']);
        }

        if ($filters['location']) {
            $qb->andWhere('c.location = :location')
                ->setParameter('location', $filters['location']);
        }

        if ($filters['isAvailable']) {
            $qb->andWhere('c.isAvailable = :isAvailable')
                ->setParameter('isAvailable', $filters['isAvailable']);
        }

        // Sortowanie
        $qb->orderBy('c.' . $sortBy, $order);

        return $qb->getQuery()->getResult();
    }

}