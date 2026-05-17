<?php

namespace App\Controller;

use App\Entity\Car;
use App\Entity\Reservation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/owner')]
final class OwnerDashboardController extends AbstractController
{
    #[IsGranted('ROLE_USER')]
    #[Route('/dashboard', name: 'app_owner_dashboard', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        $user = $this->getUser();

        $cars = $em->getRepository(Car::class)->findBy(
            ['owner' => $user],
            ['id' => 'DESC']
        );

        $carsCount = count($cars);
        $carIds = array_map(static fn (Car $car) => $car->getId(), $cars);

        $pendingCount = 0;
        $acceptedCount = 0;
        $completedCount = 0;
        $recentReservations = [];

        if ($carIds) {
            $pendingCount = (int) $em->createQueryBuilder()
                ->select('COUNT(r.id)')
                ->from(Reservation::class, 'r')
                ->join('r.car', 'c')
                ->andWhere('c.owner = :owner')
                ->andWhere('r.status = :status')
                ->setParameter('owner', $user)
                ->setParameter('status', Reservation::STATUS_PENDING)
                ->getQuery()
                ->getSingleScalarResult();

            $acceptedCount = (int) $em->createQueryBuilder()
                ->select('COUNT(r.id)')
                ->from(Reservation::class, 'r')
                ->join('r.car', 'c')
                ->andWhere('c.owner = :owner')
                ->andWhere('r.status = :status')
                ->setParameter('owner', $user)
                ->setParameter('status', Reservation::STATUS_ACCEPTED)
                ->getQuery()
                ->getSingleScalarResult();

            $completedCount = (int) $em->createQueryBuilder()
                ->select('COUNT(r.id)')
                ->from(Reservation::class, 'r')
                ->join('r.car', 'c')
                ->andWhere('c.owner = :owner')
                ->andWhere('r.status = :status')
                ->setParameter('owner', $user)
                ->setParameter('status', Reservation::STATUS_COMPLETED)
                ->getQuery()
                ->getSingleScalarResult();

            $recentReservations = $em->createQueryBuilder()
                ->select('r', 'c', 'u')
                ->from(Reservation::class, 'r')
                ->join('r.car', 'c')
                ->join('r.user', 'u')
                ->andWhere('c.owner = :owner')
                ->setParameter('owner', $user)
                ->orderBy('r.createdAt', 'DESC')
                ->setMaxResults(5)
                ->getQuery()
                ->getResult();
        }

        return $this->render('owner/dashboard.html.twig', [
            'cars' => $cars,
            'carsCount' => $carsCount,
            'pendingCount' => $pendingCount,
            'acceptedCount' => $acceptedCount,
            'completedCount' => $completedCount,
            'recentReservations' => $recentReservations,
        ]);
    }
}