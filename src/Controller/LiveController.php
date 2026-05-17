<?php

namespace App\Controller;

use App\Entity\Notification;
use App\Entity\Reservation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Entity\Car;

final class LiveController extends AbstractController
{
    #[IsGranted('ROLE_USER')]
    #[Route('/live/check', name: 'app_live_check', methods: ['GET'])]
    public function check(EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();

        $notificationCount = (int) $em->getRepository(Notification::class)
            ->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->andWhere('n.user = :user')
            ->andWhere('n.isRead = false')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        $myReservationsVersion = $em->getRepository(Reservation::class)
            ->createQueryBuilder('r')
            ->select('r.id, r.status')
            ->andWhere('r.user = :user')
            ->setParameter('user', $user)
            ->orderBy('r.id', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $ownerReservationsVersion = $em->getRepository(Reservation::class)
            ->createQueryBuilder('r')
            ->select('r.id, r.status')
            ->join('r.car', 'c')
            ->andWhere('c.owner = :user')
            ->setParameter('user', $user)
            ->orderBy('r.id', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $carsVersion = $em->getRepository(Car::class)
            ->createQueryBuilder('c')
            ->select('c.id, c.pricePerDay, c.isAvailable, c.pausedUntil')
            ->orderBy('c.id', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $version = json_encode([
            'notifications' => $notificationCount,
            'myReservations' => $myReservationsVersion,
            'ownerReservations' => $ownerReservationsVersion,
            'cars' => $carsVersion,
        ], JSON_THROW_ON_ERROR);

        return new JsonResponse([
            'version' => md5($version),
        ]);
    }
}