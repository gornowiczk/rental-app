<?php

namespace App\Controller;

use App\Repository\CarRepository;
use App\Repository\ReservationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/owner')]
#[IsGranted('ROLE_USER')]
final class OwnerController extends AbstractController
{
    #[Route('/dashboard', name: 'app_owner_dashboard', methods: ['GET'])]
    public function dashboard(
        ReservationRepository $reservationRepository,
        CarRepository $carRepository
    ): Response {
        $user = $this->getUser();

        if (!$user) {
            throw $this->createAccessDeniedException('Brak dostępu.');
        }

        $cars = $carRepository->findBy(
            ['owner' => $user],
            ['id' => 'DESC']
        );

        $recentReservations = $reservationRepository->createQueryBuilder('r')
            ->join('r.car', 'c')
            ->andWhere('c.owner = :owner')
            ->setParameter('owner', $user)
            ->orderBy('r.id', 'DESC')
            ->setMaxResults(8)
            ->getQuery()
            ->getResult();

        $allReservations = $reservationRepository->createQueryBuilder('r')
            ->join('r.car', 'c')
            ->andWhere('c.owner = :owner')
            ->setParameter('owner', $user)
            ->getQuery()
            ->getResult();

        $pendingCount = 0;
        $acceptedCount = 0;
        $completedCount = 0;

        foreach ($allReservations as $reservation) {
            $status = $reservation->getStatus();

            if ($status === 'pending') {
                $pendingCount++;
            } elseif ($status === 'accepted') {
                $acceptedCount++;
            } elseif ($status === 'completed') {
                $completedCount++;
            }
        }

        $invoiceExistsMap = [];
        foreach ($recentReservations as $reservation) {
            $invoiceExistsMap[$reservation->getId()] = false;
        }

        $today = new \DateTimeImmutable('today');
        $todayTs = $today->getTimestamp();
        $sixtyDaysTs = $today->modify('+60 days')->getTimestamp();
        $serviceThresholdTs = $today->modify('-180 days')->getTimestamp();

        $upcomingDeadlines = [];

        foreach ($cars as $car) {
            $items = [];

            if ($car->getInspectionValidUntil()) {
                $inspectionTs = \DateTimeImmutable::createFromInterface($car->getInspectionValidUntil())
                    ->setTime(0, 0)
                    ->getTimestamp();

                if ($inspectionTs <= $sixtyDaysTs) {
                    $items[] = [
                        'type' => 'inspection',
                        'label' => 'Przegląd techniczny',
                        'date' => \DateTimeImmutable::createFromInterface($car->getInspectionValidUntil()),
                        'days_left' => (int) floor(($inspectionTs - $todayTs) / 86400),
                        'is_overdue' => $inspectionTs < $todayTs,
                    ];
                }
            }

            if ($car->getInsuranceValidUntil()) {
                $insuranceTs = \DateTimeImmutable::createFromInterface($car->getInsuranceValidUntil())
                    ->setTime(0, 0)
                    ->getTimestamp();

                if ($insuranceTs <= $sixtyDaysTs) {
                    $items[] = [
                        'type' => 'insurance',
                        'label' => 'Ubezpieczenie',
                        'date' => \DateTimeImmutable::createFromInterface($car->getInsuranceValidUntil()),
                        'days_left' => (int) floor(($insuranceTs - $todayTs) / 86400),
                        'is_overdue' => $insuranceTs < $todayTs,
                    ];
                }
            }

            if ($car->getLastServiceDate()) {
                $serviceTs = \DateTimeImmutable::createFromInterface($car->getLastServiceDate())
                    ->setTime(0, 0)
                    ->getTimestamp();

                if ($serviceTs <= $serviceThresholdTs) {
                    $items[] = [
                        'type' => 'service',
                        'label' => 'Kontrola serwisowa',
                        'date' => \DateTimeImmutable::createFromInterface($car->getLastServiceDate()),
                        'days_left' => (int) floor(($serviceTs - $todayTs) / 86400),
                        'is_overdue' => true,
                    ];
                }
            }

            if (!empty($items)) {
                usort($items, static function (array $a, array $b): int {
                    return $a['date'] <=> $b['date'];
                });

                $upcomingDeadlines[] = [
                    'car' => $car,
                    'items' => $items,
                ];
            }
        }

        usort($upcomingDeadlines, static function (array $a, array $b): int {
            return $a['items'][0]['date'] <=> $b['items'][0]['date'];
        });

        return $this->render('owner/dashboard.html.twig', [
            'cars' => $cars,
            'carsCount' => count($cars),
            'pendingCount' => $pendingCount,
            'acceptedCount' => $acceptedCount,
            'completedCount' => $completedCount,
            'recentReservations' => $recentReservations,
            'invoiceExistsMap' => $invoiceExistsMap,
            'upcomingDeadlines' => $upcomingDeadlines,
        ]);
    }
}