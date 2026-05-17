<?php

namespace App\Controller;

use App\Entity\AdminLog;
use App\Entity\Car;
use App\Entity\Reservation;
use App\Entity\User;
use App\Security\LoginIpBlocker;
use App\Service\AdminLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Repository\UserRepository;
use App\Entity\Notification;
use App\Entity\Invoice;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    #[Route('/', name: 'admin_dashboard', methods: ['GET'])]
    public function dashboard(EntityManagerInterface $em): Response
    {
        $usersCount = (int) $em->createQuery('SELECT COUNT(u.id) FROM App\Entity\User u')->getSingleScalarResult();
        $carsCount = (int) $em->createQuery('SELECT COUNT(c.id) FROM App\Entity\Car c')->getSingleScalarResult();
        $reservationsCount = (int) $em->createQuery('SELECT COUNT(r.id) FROM App\Entity\Reservation r')->getSingleScalarResult();

        $pendingReservationsCount = (int) $em->createQueryBuilder()
            ->select('COUNT(r.id)')
            ->from(Reservation::class, 'r')
            ->where('r.status = :status')
            ->setParameter('status', 'pending')
            ->getQuery()
            ->getSingleScalarResult();

        $acceptedReservationsCount = (int) $em->createQueryBuilder()
            ->select('COUNT(r.id)')
            ->from(Reservation::class, 'r')
            ->where('r.status = :status')
            ->setParameter('status', 'accepted')
            ->getQuery()
            ->getSingleScalarResult();

        $completedReservationsCount = (int) $em->createQueryBuilder()
            ->select('COUNT(r.id)')
            ->from(Reservation::class, 'r')
            ->where('r.status = :status')
            ->setParameter('status', 'completed')
            ->getQuery()
            ->getSingleScalarResult();

        $cancelledReservationsCount = (int) $em->createQueryBuilder()
            ->select('COUNT(r.id)')
            ->from(Reservation::class, 'r')
            ->where('r.status = :status')
            ->setParameter('status', 'cancelled')
            ->getQuery()
            ->getSingleScalarResult();

        $completedRevenue = (float) $em->createQueryBuilder()
            ->select('COALESCE(SUM(r.totalPrice), 0)')
            ->from(Reservation::class, 'r')
            ->where('r.status = :status')
            ->setParameter('status', 'completed')
            ->getQuery()
            ->getSingleScalarResult();

        $invoicesCount = (int) $em->createQueryBuilder()
            ->select('COUNT(i.id)')
            ->from(Invoice::class, 'i')
            ->getQuery()
            ->getSingleScalarResult();

        $logsCount = (int) $em->createQueryBuilder()
            ->select('COUNT(l.id)')
            ->from(AdminLog::class, 'l')
            ->getQuery()
            ->getSingleScalarResult();

        $latestReservations = $em->getRepository(Reservation::class)
            ->findBy([], ['id' => 'DESC'], 8);

        $latestCars = $em->getRepository(Car::class)
            ->findBy([], ['id' => 'DESC'], 6);

        return $this->render('admin/dashboard.html.twig', [
            'usersCount' => $usersCount,
            'carsCount' => $carsCount,
            'reservationsCount' => $reservationsCount,
            'pendingReservationsCount' => $pendingReservationsCount,
            'acceptedReservationsCount' => $acceptedReservationsCount,
            'completedReservationsCount' => $completedReservationsCount,
            'cancelledReservationsCount' => $cancelledReservationsCount,
            'completedRevenue' => $completedRevenue,
            'invoicesCount' => $invoicesCount,
            'logsCount' => $logsCount,
            'latestReservations' => $latestReservations,
            'latestCars' => $latestCars,
        ]);
    }

    #[Route('/reports', name: 'admin_reports', methods: ['GET'])]
    public function reports(EntityManagerInterface $em): Response
    {
        $reservationsByMonth = $em->createQueryBuilder()
            ->select("SUBSTRING(r.createdAt, 1, 7) AS month, COUNT(r.id) AS count")
            ->from(Reservation::class, 'r')
            ->groupBy('month')
            ->orderBy('month', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $revenueByMonth = $em->createQueryBuilder()
            ->select("SUBSTRING(r.createdAt, 1, 7) AS month, COALESCE(SUM(r.totalPrice), 0) AS revenue")
            ->from(Reservation::class, 'r')
            ->where('r.status = :status')
            ->setParameter('status', 'completed')
            ->groupBy('month')
            ->orderBy('month', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $reservationsByStatus = $em->createQueryBuilder()
            ->select('r.status AS status, COUNT(r.id) AS count')
            ->from(Reservation::class, 'r')
            ->groupBy('r.status')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getArrayResult();

        $topCars = $em->createQueryBuilder()
            ->select('c.brand AS brand, c.model AS model, c.registrationNumber AS registrationNumber, COUNT(r.id) AS count')
            ->from(Reservation::class, 'r')
            ->join('r.car', 'c')
            ->groupBy('c.id')
            ->orderBy('count', 'DESC')
            ->setMaxResults(8)
            ->getQuery()
            ->getArrayResult();

        $totalRevenue = (float) $em->createQueryBuilder()
            ->select('COALESCE(SUM(r.totalPrice), 0)')
            ->from(Reservation::class, 'r')
            ->where('r.status = :status')
            ->setParameter('status', 'completed')
            ->getQuery()
            ->getSingleScalarResult();

        return $this->render('admin/reports.html.twig', [
            'reservationsByMonth' => $reservationsByMonth,
            'revenueByMonth' => $revenueByMonth,
            'reservationsByStatus' => $reservationsByStatus,
            'topCars' => $topCars,
            'totalRevenue' => $totalRevenue,
        ]);
    }

    #[Route('/reports/export.csv', name: 'admin_reports_export', methods: ['GET'])]
    public function reportsExportCsv(EntityManagerInterface $em, AdminLogger $logger): Response
    {
        $rows = [];

        $rows[] = ['Raport', 'Wartość'];

        $usersCount = (int) $em->createQuery('SELECT COUNT(u.id) FROM App\Entity\User u')->getSingleScalarResult();
        $carsCount = (int) $em->createQuery('SELECT COUNT(c.id) FROM App\Entity\Car c')->getSingleScalarResult();
        $reservationsCount = (int) $em->createQuery('SELECT COUNT(r.id) FROM App\Entity\Reservation r')->getSingleScalarResult();

        $completedRevenue = (float) $em->createQueryBuilder()
            ->select('COALESCE(SUM(r.totalPrice), 0)')
            ->from(Reservation::class, 'r')
            ->where('r.status = :status')
            ->setParameter('status', 'completed')
            ->getQuery()
            ->getSingleScalarResult();

        $rows[] = ['Liczba użytkowników', (string) $usersCount];
        $rows[] = ['Liczba aut', (string) $carsCount];
        $rows[] = ['Liczba rezerwacji', (string) $reservationsCount];
        $rows[] = ['Przychód brutto', number_format($completedRevenue, 2, '.', '') . ' PLN'];

        $rows[] = [];
        $rows[] = ['Status rezerwacji', 'Liczba'];

        $statuses = $em->createQueryBuilder()
            ->select('r.status AS status, COUNT(r.id) AS count')
            ->from(Reservation::class, 'r')
            ->groupBy('r.status')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getArrayResult();

        foreach ($statuses as $row) {
            $rows[] = [
                (string) $row['status'],
                (string) $row['count'],
            ];
        }

        $rows[] = [];
        $rows[] = ['Miesiąc', 'Rezerwacje', 'Przychód brutto'];

        $reservationsByMonth = $em->createQueryBuilder()
            ->select("SUBSTRING(r.createdAt, 1, 7) AS month, COUNT(r.id) AS count")
            ->from(Reservation::class, 'r')
            ->groupBy('month')
            ->orderBy('month', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $revenueRows = $em->createQueryBuilder()
            ->select("SUBSTRING(r.createdAt, 1, 7) AS month, COALESCE(SUM(r.totalPrice), 0) AS revenue")
            ->from(Reservation::class, 'r')
            ->where('r.status = :status')
            ->setParameter('status', 'completed')
            ->groupBy('month')
            ->orderBy('month', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $revenueMap = [];
        foreach ($revenueRows as $row) {
            $revenueMap[$row['month']] = (float) $row['revenue'];
        }

        foreach ($reservationsByMonth as $row) {
            $month = (string) $row['month'];
            $rows[] = [
                $month,
                (string) $row['count'],
                number_format($revenueMap[$month] ?? 0, 2, '.', '') . ' PLN',
            ];
        }

        $rows[] = [];
        $rows[] = ['Auto', 'Rejestracja', 'Liczba rezerwacji'];

        $topCars = $em->createQueryBuilder()
            ->select('c.brand AS brand, c.model AS model, c.registrationNumber AS registrationNumber, COUNT(r.id) AS count')
            ->from(Reservation::class, 'r')
            ->join('r.car', 'c')
            ->groupBy('c.id')
            ->orderBy('count', 'DESC')
            ->setMaxResults(20)
            ->getQuery()
            ->getArrayResult();

        foreach ($topCars as $row) {
            $rows[] = [
                trim((string) $row['brand'] . ' ' . (string) $row['model']),
                (string) $row['registrationNumber'],
                (string) $row['count'],
            ];
        }

        $csv = $this->toCsv($rows);

        $logger->log('reports.export_csv', 'Report', null, [
            'rows' => count($rows),
        ]);

        return new Response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="reports_export.csv"',
        ]);
    }

    #[Route('/reservations', name: 'admin_reservations', methods: ['GET'])]
    public function reservations(Request $request, EntityManagerInterface $em): Response
    {
        $status = trim((string) $request->query->get('status', ''));
        $q = trim((string) $request->query->get('q', ''));

        $qb = $em->getRepository(Reservation::class)->createQueryBuilder('r')
            ->leftJoin('r.user', 'u')->addSelect('u')
            ->leftJoin('r.car', 'c')->addSelect('c')
            ->orderBy('r.id', 'DESC');

        if ($status !== '') {
            $qb->andWhere('r.status = :st')->setParameter('st', $status);
        }

        if ($q !== '') {
            $qb->andWhere('u.email LIKE :q OR c.brand LIKE :q OR c.model LIKE :q OR c.registrationNumber LIKE :q')
                ->setParameter('q', '%' . $q . '%');
        }

        $reservations = $qb->getQuery()->getResult();

        return $this->render('admin/reservations.html.twig', [
            'reservations' => $reservations,
            'filters' => [
                'status' => $status,
                'q' => $q,
            ],
        ]);
    }

    #[Route('/reservations/export.csv', name: 'admin_reservations_export', methods: ['GET'])]
    public function reservationsExportCsv(Request $request, EntityManagerInterface $em, AdminLogger $logger): Response
    {
        $status = trim((string) $request->query->get('status', ''));
        $q = trim((string) $request->query->get('q', ''));

        $qb = $em->getRepository(Reservation::class)->createQueryBuilder('r')
            ->leftJoin('r.user', 'u')->addSelect('u')
            ->leftJoin('r.car', 'c')->addSelect('c')
            ->orderBy('r.id', 'DESC');

        if ($status !== '') {
            $qb->andWhere('r.status = :st')->setParameter('st', $status);
        }

        if ($q !== '') {
            $qb->andWhere('u.email LIKE :q OR c.brand LIKE :q OR c.model LIKE :q OR c.registrationNumber LIKE :q')
                ->setParameter('q', '%' . $q . '%');
        }

        $reservations = $qb->setMaxResults(5000)->getQuery()->getResult();

        $rows = [];
        $rows[] = [
            'id', 'status', 'created_at',
            'user_email',
            'car', 'car_reg',
            'start_date', 'end_date',
            'phone', 'rental_location',
            'wants_invoice', 'invoice_name', 'invoice_address', 'invoice_nip',
        ];

        foreach ($reservations as $r) {
            $rows[] = [
                (string) $r->getId(),
                (string) $r->getStatus(),
                $r->getCreatedAt()->format('Y-m-d H:i:s'),
                $r->getUser() ? $r->getUser()->getEmail() : '',
                $r->getCar() ? ($r->getCar()->getBrand() . ' ' . $r->getCar()->getModel()) : '',
                $r->getCar() ? $r->getCar()->getRegistrationNumber() : '',
                $r->getStartDate()->format('Y-m-d H:i:s'),
                $r->getEndDate()->format('Y-m-d H:i:s'),
                (string) ($r->getPhoneNumber() ?? ''),
                (string) ($r->getRentalLocation() ?? ''),
                $r->wantsInvoice() ? '1' : '0',
                (string) ($r->getInvoiceName() ?? ''),
                (string) ($r->getInvoiceAddress() ?? ''),
                (string) ($r->getInvoiceNip() ?? ''),
            ];
        }

        $csv = $this->toCsv($rows);

        $logger->log('reservations.export_csv', 'Reservation', null, [
            'filters' => ['q' => $q, 'status' => $status],
            'count' => count($reservations),
        ]);

        return new Response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="reservations_export.csv"',
        ]);
    }

    #[Route('/reservations/bulk', name: 'admin_reservations_bulk', methods: ['POST'])]
    public function reservationsBulk(Request $request, EntityManagerInterface $em, AdminLogger $logger): Response
    {
        if (!$this->isCsrfTokenValid('admin_reservations_bulk', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Błędny token.');
        }

        $action = (string) $request->request->get('action', '');
        $ids = $request->request->all('ids');

        if (!is_array($ids) || count($ids) === 0) {
            $this->addFlash('warning', 'Nie wybrano żadnych rezerwacji.');
            return $this->redirectToRoute('admin_reservations');
        }

        $ids = array_values(array_filter(array_map('intval', $ids), fn ($v) => $v > 0));
        if (!$ids) {
            $this->addFlash('warning', 'Nieprawidłowe ID rezerwacji.');
            return $this->redirectToRoute('admin_reservations');
        }

        $allowed = ['accept', 'reject', 'complete', 'delete'];
        if (!in_array($action, $allowed, true)) {
            $this->addFlash('danger', 'Nieprawidłowa akcja.');
            return $this->redirectToRoute('admin_reservations');
        }

        if ($action === 'delete' && !$this->isGranted('ROLE_SUPER_ADMIN')) {
            $this->addFlash('danger', 'Brak uprawnień do usuwania rezerwacji.');
            return $this->redirectToRoute('admin_reservations');
        }

        $reservations = $em->getRepository(Reservation::class)->createQueryBuilder('r')
            ->where('r.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        if (!$reservations) {
            $this->addFlash('warning', 'Nie znaleziono rezerwacji.');
            return $this->redirectToRoute('admin_reservations');
        }

        $changed = 0;
        $blockedByInvoice = 0;

        foreach ($reservations as $r) {
            if (!$r instanceof Reservation) {
                continue;
            }

            if ($action === 'accept') {
                $r->setStatus('accepted');
                $changed++;
                continue;
            }

            if ($action === 'reject') {
                $r->setStatus('rejected');
                $changed++;
                continue;
            }

            if ($action === 'complete') {
                $r->setStatus('completed');
                $changed++;
                continue;
            }

            if ($action === 'delete') {
                $invoice = $em->getRepository(Invoice::class)->findOneBy([
                    'reservation' => $r,
                ]);

                if ($invoice) {
                    $blockedByInvoice++;

                    $logger->log('reservation.delete_blocked_invoice', 'Reservation', $r->getId(), [
                        'reason' => 'invoice_exists',
                        'reservationId' => $r->getId(),
                    ]);

                    continue;
                }

                $notifications = $em->getRepository(Notification::class)->findBy([
                    'reservation' => $r,
                ]);

                foreach ($notifications as $notification) {
                    $em->remove($notification);
                }

                $logger->log('reservation.delete', 'Reservation', $r->getId(), [
                    'reservationId' => $r->getId(),
                    'status' => $r->getStatus(),
                ]);

                $r->setWantsInvoice(false);
                $r->setInvoiceName(null);
                $r->setInvoiceAddress(null);
                $r->setInvoiceNip(null);
                $r->setInvoiceNumber(null);

                $em->remove($r);
                $changed++;
            }
        }

        $em->flush();

        $logger->log('reservation.bulk.' . $action, 'Reservation', null, [
            'ids' => $ids,
            'changed' => $changed,
            'blockedByInvoice' => $blockedByInvoice,
        ]);

        if ($action === 'delete' && $blockedByInvoice > 0) {
            $this->addFlash('warning', 'Niektóre rezerwacje nie zostały usunięte, ponieważ mają przypisaną fakturę.');
        }

        $msg = match ($action) {
            'accept' => "Zaakceptowano: {$changed}",
            'reject' => "Odrzucono: {$changed}",
            'complete' => "Zakończono: {$changed}",
            'delete' => "Usunięto: {$changed}",
            default => "Wykonano: {$changed}",
        };

        $this->addFlash('success', $msg);
        return $this->redirectToRoute('admin_reservations');
    }

    #[Route('/reservations/{id}', name: 'admin_reservation_show', methods: ['GET'])]
    public function reservationShow(Reservation $reservation): Response
    {
        return $this->render('admin/reservation_show.html.twig', [
            'r' => $reservation,
        ]);
    }

    #[Route('/reservations/{id}/status', name: 'admin_reservation_set_status', methods: ['POST'])]
    public function reservationSetStatus(Request $request, Reservation $reservation, EntityManagerInterface $em, AdminLogger $logger): Response
    {
        if (!$this->isCsrfTokenValid('admin_reservation_set_status_' . $reservation->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Błędny token.');
        }

        $status = (string) $request->request->get('status', '');
        $allowed = ['pending', 'accepted', 'rejected', 'cancelled', 'completed'];

        if (!in_array($status, $allowed, true)) {
            $this->addFlash('danger', 'Nieprawidłowy status.');
            return $this->redirectToRoute('admin_reservation_show', ['id' => $reservation->getId()]);
        }

        $reservation->setStatus($status);
        $em->flush();

        $logger->log('reservation.set_status', 'Reservation', $reservation->getId(), ['status' => $status]);

        $this->addFlash('success', 'Status zaktualizowany.');
        return $this->redirectToRoute('admin_reservation_show', ['id' => $reservation->getId()]);
    }

    #[Route('/reservation/accept/{id}', name: 'admin_accept_reservation', methods: ['POST'])]
    public function acceptReservation(Request $request, Reservation $reservation, EntityManagerInterface $em, AdminLogger $logger): Response
    {
        if (!$this->isCsrfTokenValid('admin_accept_reservation_' . $reservation->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Błędny token.');
        }

        $reservation->setStatus('accepted');
        $em->flush();

        $logger->log('reservation.accept', 'Reservation', $reservation->getId(), ['status' => 'accepted']);

        $this->addFlash('success', 'Rezerwacja zaakceptowana.');
        return $this->redirectToRoute('admin_reservations');
    }

    #[Route('/reservation/reject/{id}', name: 'admin_reject_reservation', methods: ['POST'])]
    public function rejectReservation(Request $request, Reservation $reservation, EntityManagerInterface $em, AdminLogger $logger): Response
    {
        if (!$this->isCsrfTokenValid('admin_reject_reservation_' . $reservation->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Błędny token.');
        }

        $reservation->setStatus('rejected');
        $em->flush();

        $logger->log('reservation.reject', 'Reservation', $reservation->getId(), ['status' => 'rejected']);

        $this->addFlash('warning', 'Rezerwacja odrzucona.');
        return $this->redirectToRoute('admin_reservations');
    }

    #[Route('/reservation/delete/{id}', name: 'admin_delete_reservation', methods: ['POST'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function deleteReservation(
        Request $request,
        Reservation $reservation,
        EntityManagerInterface $em,
        AdminLogger $logger
    ): Response {
        if (!$this->isCsrfTokenValid('admin_delete_reservation_' . $reservation->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Błędny token.');
        }

        $invoice = $em->getRepository(Invoice::class)->findOneBy([
            'reservation' => $reservation,
        ]);

        if ($invoice) {
            $logger->log('reservation.delete_blocked_invoice', 'Reservation', $reservation->getId(), [
                'reason' => 'invoice_exists',
                'reservationId' => $reservation->getId(),
            ]);

            $this->addFlash('warning', 'Nie można usunąć rezerwacji, ponieważ ma przypisaną fakturę.');
            return $this->redirectToRoute('admin_reservation_show', ['id' => $reservation->getId()]);
        }

        $notifications = $em->getRepository(Notification::class)->findBy([
            'reservation' => $reservation,
        ]);

        foreach ($notifications as $notification) {
            $em->remove($notification);
        }

        $reservationId = $reservation->getId();

        $logger->log('reservation.delete', 'Reservation', $reservationId, [
            'reservationId' => $reservationId,
            'status' => $reservation->getStatus(),
        ]);

        $reservation->setWantsInvoice(false);
        $reservation->setInvoiceName(null);
        $reservation->setInvoiceAddress(null);
        $reservation->setInvoiceNip(null);
        $reservation->setInvoiceNumber(null);

        $em->remove($reservation);
        $em->flush();

        $this->addFlash('success', 'Rezerwacja została usunięta.');
        return $this->redirectToRoute('admin_reservations');
    }

    #[Route('/cars', name: 'admin_cars', methods: ['GET'])]
    public function cars(Request $request, EntityManagerInterface $em): Response
    {
        $q = trim((string) $request->query->get('q', ''));
        $city = trim((string) $request->query->get('city', ''));
        $state = trim((string) $request->query->get('state', ''));

        $qb = $em->getRepository(Car::class)->createQueryBuilder('c')
            ->leftJoin('c.owner', 'o')->addSelect('o')
            ->orderBy('c.id', 'DESC');

        if ($q !== '') {
            $qb->andWhere('c.brand LIKE :q OR c.model LIKE :q OR c.registrationNumber LIKE :q OR o.email LIKE :q')
                ->setParameter('q', '%' . $q . '%');
        }

        if ($city !== '') {
            $qb->andWhere('c.location = :city')->setParameter('city', $city);
        }

        $now = new \DateTimeImmutable();

        if ($state === 'rentable') {
            $qb->andWhere('c.isAvailable = true')
                ->andWhere('c.pausedUntil IS NULL OR c.pausedUntil <= :now')
                ->setParameter('now', $now);
        } elseif ($state === 'paused') {
            $qb->andWhere('c.pausedUntil IS NOT NULL AND c.pausedUntil > :now')
                ->setParameter('now', $now);
        } elseif ($state === 'off') {
            $qb->andWhere('c.isAvailable = false');
        }

        $cars = $qb->getQuery()->getResult();

        $cities = $em->createQueryBuilder()
            ->select('DISTINCT c2.location')
            ->from(Car::class, 'c2')
            ->where('c2.location IS NOT NULL AND c2.location <> :empty')
            ->setParameter('empty', '')
            ->orderBy('c2.location', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();

        return $this->render('admin/cars.html.twig', [
            'cars' => $cars,
            'filters' => [
                'q' => $q,
                'city' => $city,
                'state' => $state,
            ],
            'cities' => $cities,
            'now' => $now,
        ]);
    }

    #[Route('/car/{id}/toggle', name: 'admin_car_toggle', methods: ['POST'])]
    public function carToggle(Request $request, Car $car, EntityManagerInterface $em, AdminLogger $logger): Response
    {
        if (!$this->isCsrfTokenValid('admin_car_toggle_' . $car->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Błędny token.');
        }

        $car->setIsAvailable(!$car->isAvailable());
        $em->flush();

        $logger->log('car.toggle', 'Car', $car->getId(), ['isAvailable' => $car->isAvailable()]);

        $this->addFlash('success', 'Zmieniono dostępność auta.');
        return $this->redirectToRoute('admin_cars');
    }

    #[Route('/car/{id}/pause', name: 'admin_car_pause', methods: ['POST'])]
    public function carPause(Request $request, Car $car, EntityManagerInterface $em, AdminLogger $logger): Response
    {
        if (!$this->isCsrfTokenValid('admin_car_pause_' . $car->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Błędny token.');
        }

        $date = trim((string) $request->request->get('pausedUntil', ''));
        if ($date === '') {
            $this->addFlash('warning', 'Podaj datę wstrzymania.');
            return $this->redirectToRoute('admin_cars');
        }

        try {
            $dt = new \DateTimeImmutable($date . ' 23:59:59');
        } catch (\Throwable $e) {
            $this->addFlash('danger', 'Nieprawidłowa data.');
            return $this->redirectToRoute('admin_cars');
        }

        $car->setPausedUntil(\DateTime::createFromImmutable($dt));
        $em->flush();

        $logger->log('car.pause', 'Car', $car->getId(), [
            'pausedUntil' => $car->getPausedUntil()?->format('Y-m-d H:i:s'),
        ]);

        $this->addFlash('success', 'Auto wstrzymane.');
        return $this->redirectToRoute('admin_cars');
    }

    #[Route('/car/{id}/unpause', name: 'admin_car_unpause', methods: ['POST'])]
    public function carUnpause(Request $request, Car $car, EntityManagerInterface $em, AdminLogger $logger): Response
    {
        if (!$this->isCsrfTokenValid('admin_car_unpause_' . $car->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Błędny token.');
        }

        $car->setPausedUntil(null);
        $em->flush();

        $logger->log('car.unpause', 'Car', $car->getId());

        $this->addFlash('success', 'Auto wznowione.');
        return $this->redirectToRoute('admin_cars');
    }

    #[Route('/users', name: 'admin_users', methods: ['GET'])]
    public function users(Request $request, EntityManagerInterface $em): Response
    {
        $q = trim((string) $request->query->get('q', ''));
        $role = trim((string) $request->query->get('role', ''));
        $blocked = trim((string) $request->query->get('blocked', ''));

        $qb = $em->getRepository(User::class)->createQueryBuilder('u')
            ->orderBy('u.id', 'DESC');

        if ($q !== '') {
            $qb->andWhere('u.email LIKE :q OR u.firstName LIKE :q OR u.lastName LIKE :q')
                ->setParameter('q', '%' . $q . '%');
        }

        if ($role !== '') {
            $qb->andWhere('u.roles LIKE :r')->setParameter('r', '%' . $role . '%');
        }

        if ($blocked === '1') {
            $qb->andWhere('u.isBlocked = true');
        } elseif ($blocked === '0') {
            $qb->andWhere('u.isBlocked = false');
        }

        $users = $qb->getQuery()->getResult();

        return $this->render('admin/users.html.twig', [
            'users' => $users,
            'filters' => [
                'q' => $q,
                'role' => $role,
                'blocked' => $blocked,
            ],
        ]);
    }

    #[Route('/users/{id}', name: 'admin_user_show', methods: ['GET'])]
    public function userShow(
        \App\Entity\User $user,
        \App\Repository\ReservationRepository $reservationRepo,
        \App\Repository\CarRepository $carRepo
    ): Response {
        $userReservations = $reservationRepo->findBy(
            ['user' => $user],
            ['createdAt' => 'DESC']
        );

        $ownedCars = $carRepo->findBy(
            ['owner' => $user],
            ['id' => 'DESC']
        );

        return $this->render('admin/user_show.html.twig', [
            'u' => $user,
            'userReservations' => $userReservations,
            'ownedCars' => $ownedCars,
        ]);
    }

    #[Route('/user/block/{id}', name: 'admin_block_user', methods: ['POST'])]
    public function blockUser(Request $request, User $user, EntityManagerInterface $em, AdminLogger $logger): Response
    {
        if (!$this->isCsrfTokenValid('admin_block_user_' . $user->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Błędny token.');
        }

        if (in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true) && !$this->isGranted('ROLE_SUPER_ADMIN')) {
            $this->addFlash('danger', 'Nie możesz blokować konta SUPER_ADMIN.');
            return $this->redirectToRoute('admin_users');
        }

        if ($this->getUser() instanceof User && $user->getId() === $this->getUser()->getId()) {
            $this->addFlash('danger', 'Nie możesz zablokować swojego konta.');
            return $this->redirectToRoute('admin_users');
        }

        $reason = trim((string) $request->request->get('reason', ''));
        if ($reason === '') {
            $reason = 'Naruszenie regulaminu / decyzja administracji.';
        }

        $user->setIsBlocked(true);
        $user->setBlockedReason($reason);
        $user->setBlockedAt(new \DateTimeImmutable());

        $em->flush();

        $logger->log('user.block', 'User', $user->getId(), ['reason' => $reason]);

        $this->addFlash('success', 'Użytkownik zablokowany.');
        return $this->redirectToRoute('admin_users');
    }

    #[Route('/user/unblock/{id}', name: 'admin_unblock_user', methods: ['POST'])]
    public function unblockUser(Request $request, User $user, EntityManagerInterface $em, AdminLogger $logger): Response
    {
        if (!$this->isCsrfTokenValid('admin_unblock_user_' . $user->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Błędny token.');
        }

        $user->setIsBlocked(false);
        $user->setBlockedReason(null);
        $user->setBlockedAt(null);

        $em->flush();

        $logger->log('user.unblock', 'User', $user->getId());

        $this->addFlash('success', 'Użytkownik odblokowany.');
        return $this->redirectToRoute('admin_users');
    }

    #[Route('/user/toggle-admin/{id}', name: 'admin_toggle_admin', methods: ['POST'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function toggleAdmin(Request $request, User $user, EntityManagerInterface $em, AdminLogger $logger): Response
    {
        if (!$this->isCsrfTokenValid('admin_toggle_admin_' . $user->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Błędny token.');
        }

        if (in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true)) {
            $this->addFlash('warning', 'Nie można zmieniać uprawnień SUPER_ADMIN.');
            return $this->redirectToRoute('admin_users');
        }

        $roles = $user->getRoles();
        $wasAdmin = in_array('ROLE_ADMIN', $roles, true);

        if ($wasAdmin) {
            $roles = array_values(array_diff($roles, ['ROLE_ADMIN', 'ROLE_MANAGER']));
            $this->addFlash('success', 'Odebrano uprawnienia admina.');
        } else {
            $roles[] = 'ROLE_ADMIN';
            $this->addFlash('success', 'Nadano uprawnienia admina.');
        }

        $user->setRoles(array_values(array_unique($roles)));
        $em->flush();

        $logger->log('user.toggle_admin', 'User', $user->getId(), [
            'from' => $wasAdmin ? 'ROLE_ADMIN' : 'no_admin',
            'to' => $wasAdmin ? 'no_admin' : 'ROLE_ADMIN',
            'roles' => $user->getRoles(),
        ]);

        return $this->redirectToRoute('admin_users');
    }

    #[Route('/user/toggle-manager/{id}', name: 'admin_toggle_manager', methods: ['POST'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function toggleManager(Request $request, User $user, EntityManagerInterface $em, AdminLogger $logger): Response
    {
        if (!$this->isCsrfTokenValid('admin_toggle_manager_' . $user->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Błędny token.');
        }

        if (in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true)) {
            $this->addFlash('warning', 'Nie można zmieniać uprawnień SUPER_ADMIN.');
            return $this->redirectToRoute('admin_users');
        }

        $roles = $user->getRoles();
        $wasManager = in_array('ROLE_MANAGER', $roles, true);

        if ($wasManager) {
            $roles = array_values(array_diff($roles, ['ROLE_MANAGER']));
            $this->addFlash('success', 'Odebrano rolę MANAGER.');
        } else {
            $roles[] = 'ROLE_ADMIN';
            $roles[] = 'ROLE_MANAGER';
            $this->addFlash('success', 'Nadano rolę MANAGER.');
        }

        $user->setRoles(array_values(array_unique($roles)));
        $em->flush();

        $logger->log('user.toggle_manager', 'User', $user->getId(), [
            'from' => $wasManager ? 'ROLE_MANAGER' : 'no_manager',
            'to' => $wasManager ? 'no_manager' : 'ROLE_MANAGER',
            'roles' => $user->getRoles(),
        ]);

        return $this->redirectToRoute('admin_users');
    }

    #[Route('/admins', name: 'admin_admins', methods: ['GET', 'POST'])]
    public function admins(
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        if ($request->isMethod('POST') && $request->request->get('_action') === 'create') {
            $token = (string) $request->request->get('_token');
            if (!$this->isCsrfTokenValid('admin_create_admin', $token)) {
                $this->addFlash('danger', 'Błędny token CSRF.');
                return $this->redirectToRoute('admin_admins');
            }

            $email = trim((string) $request->request->get('email', ''));
            $password = (string) $request->request->get('password', '');

            if ($email === '' || $password === '') {
                $this->addFlash('danger', 'Email i hasło są wymagane.');
                return $this->redirectToRoute('admin_admins');
            }

            $existing = $userRepository->findOneBy(['email' => $email]);
            if ($existing) {
                $this->addFlash('warning', 'Użytkownik o takim emailu już istnieje.');
                return $this->redirectToRoute('admin_admins');
            }

            $user = new User();
            $user->setEmail($email);
            $user->setPassword($passwordHasher->hashPassword($user, $password));

            $roles = ['ROLE_USER', 'ROLE_ADMIN'];

            if ($request->request->getBoolean('ROLE_MANAGER')) {
                $roles[] = 'ROLE_MANAGER';
            }

            if ($request->request->getBoolean('ROLE_FLEET_ADMIN')) {
                $roles[] = 'ROLE_FLEET_ADMIN';
            }

            $user->setRoles(array_values(array_unique($roles)));

            $em->persist($user);
            $em->flush();

            $this->addFlash('success', 'Nowe konto administratora zostało utworzone.');
            return $this->redirectToRoute('admin_admins');
        }

        $admins = $userRepository->createQueryBuilder('u')
            ->andWhere('u.roles LIKE :adminRole OR u.roles LIKE :managerRole OR u.roles LIKE :superRole OR u.roles LIKE :fleetRole')
            ->setParameter('adminRole', '%ROLE_ADMIN%')
            ->setParameter('managerRole', '%ROLE_MANAGER%')
            ->setParameter('superRole', '%ROLE_SUPER_ADMIN%')
            ->setParameter('fleetRole', '%ROLE_FLEET_ADMIN%')
            ->orderBy('u.id', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('admin/admins.html.twig', [
            'admins' => $admins,
        ]);
    }

    #[Route('/admins/{id}/update-roles', name: 'admin_update_admin_roles', methods: ['POST'])]
    public function updateAdminRoles(
        Request $request,
        User $user,
        EntityManagerInterface $em
    ): Response {
        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('admin_update_roles_' . $user->getId(), $token)) {
            $this->addFlash('danger', 'Błędny token CSRF.');
            return $this->redirectToRoute('admin_admins');
        }

        $roles = $user->getRoles();

        // zostaw zawsze podstawowe role admina
        $baseRoles = ['ROLE_USER', 'ROLE_ADMIN'];

        // nie ruszaj SUPER_ADMIN tym formularzem
        if (in_array('ROLE_SUPER_ADMIN', $roles, true)) {
            $this->addFlash('warning', 'Nie można zmieniać ról SUPER_ADMIN z tego poziomu.');
            return $this->redirectToRoute('admin_admins');
        }

        $newRoles = $baseRoles;

        if ($request->request->getBoolean('ROLE_MANAGER')) {
            $newRoles[] = 'ROLE_MANAGER';
        }

        if ($request->request->getBoolean('ROLE_FLEET_ADMIN')) {
            $newRoles[] = 'ROLE_FLEET_ADMIN';
        }

        $user->setRoles(array_values(array_unique($newRoles)));
        $em->flush();

        $this->addFlash('success', 'Role administratora zostały zaktualizowane.');
        return $this->redirectToRoute('admin_admins');
    }

    #[Route('/admins/{id}/delete', name: 'admin_delete_admin', methods: ['POST'])]
    public function deleteAdmin(
        Request $request,
        User $user,
        EntityManagerInterface $em
    ): Response {
        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('admin_delete_admin_' . $user->getId(), $token)) {
            $this->addFlash('danger', 'Błędny token CSRF.');
            return $this->redirectToRoute('admin_admins');
        }

        if (in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true)) {
            $this->addFlash('warning', 'Nie można usunąć konta SUPER_ADMIN z tego poziomu.');
            return $this->redirectToRoute('admin_admins');
        }

        $em->remove($user);
        $em->flush();

        $this->addFlash('success', 'Konto administratora zostało usunięte.');
        return $this->redirectToRoute('admin_admins');
    }

    #[Route('/user/delete/{id}', name: 'admin_delete_user', methods: ['POST'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function deleteUser(Request $request, User $user, EntityManagerInterface $em, AdminLogger $logger): Response
    {
        if (!$this->isCsrfTokenValid('admin_delete_user_' . $user->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Błędny token.');
        }

        if ($this->getUser() instanceof User && $user->getId() === $this->getUser()->getId()) {
            $this->addFlash('danger', 'Nie możesz usunąć swojego konta.');
            return $this->redirectToRoute('admin_users');
        }

        if (in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true)) {
            $this->addFlash('danger', 'Nie można usunąć konta SUPER_ADMIN.');
            return $this->redirectToRoute('admin_users');
        }

        $id = $user->getId();
        $email = $user->getEmail();

        $em->remove($user);
        $em->flush();

        $logger->log('user.delete', 'User', $id, ['email' => $email]);

        $this->addFlash('success', 'Użytkownik usunięty.');
        return $this->redirectToRoute('admin_users');
    }

    #[Route('/user/{id}/password', name: 'admin_user_password', methods: ['GET', 'POST'])]
    public function userPassword(
        Request $request,
        User $user,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        AdminLogger $logger
    ): Response {
        if (in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true) && !$this->isGranted('ROLE_SUPER_ADMIN')) {
            throw $this->createAccessDeniedException('Brak uprawnień do zmiany hasła SUPER_ADMIN.');
        }

        if ($this->getUser() instanceof User && $user->getId() === $this->getUser()->getId()) {
            $this->addFlash('warning', 'Zmień swoje hasło w profilu, nie w panelu admina.');
            return $this->redirectToRoute('admin_users');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('admin_user_password_' . $user->getId(), (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Błędny token.');
            }

            $plain = (string) $request->request->get('password', '');
            $plain2 = (string) $request->request->get('password2', '');

            if (mb_strlen($plain) < 8) {
                $this->addFlash('danger', 'Hasło musi mieć minimum 8 znaków.');
                return $this->redirectToRoute('admin_user_password', ['id' => $user->getId()]);
            }

            if ($plain !== $plain2) {
                $this->addFlash('danger', 'Hasła nie są takie same.');
                return $this->redirectToRoute('admin_user_password', ['id' => $user->getId()]);
            }

            $user->setPassword($hasher->hashPassword($user, $plain));
            $em->flush();

            $logger->log('user.reset_password', 'User', $user->getId(), [
                'email' => $user->getEmail(),
            ]);

            $this->addFlash('success', 'Hasło zostało zresetowane.');
            return $this->redirectToRoute('admin_users', ['q' => $user->getEmail()]);
        }

        return $this->render('admin/user_password.html.twig', [
            'u' => $user,
        ]);
    }

    #[Route('/user/force-password/{id}', name: 'admin_force_password', methods: ['POST'])]
    public function forcePassword(
        Request $request,
        User $user,
        EntityManagerInterface $em,
        AdminLogger $logger
    ): Response {
        if (!$this->isCsrfTokenValid('admin_force_password_' . $user->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Błędny token.');
        }

        if (in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true) && !$this->isGranted('ROLE_SUPER_ADMIN')) {
            throw $this->createAccessDeniedException('Brak uprawnień.');
        }

        if ($this->getUser() instanceof User && $user->getId() === $this->getUser()->getId()) {
            $this->addFlash('warning', 'Nie możesz wymusić zmiany hasła na sobie.');
            return $this->redirectToRoute('admin_users');
        }

        $user->setForcePasswordChange(true);
        $em->flush();

        $logger->log('user.force_password_change', 'User', $user->getId(), [
            'email' => $user->getEmail(),
        ]);

        $this->addFlash('success', 'Wymuszono zmianę hasła dla: ' . $user->getEmail());
        return $this->redirectToRoute('admin_users', ['q' => $user->getEmail()]);
    }

    #[Route('/logs', name: 'admin_logs', methods: ['GET'])]
    public function logs(Request $request, EntityManagerInterface $em): Response
    {
        $q = trim((string) $request->query->get('q', ''));
        $action = trim((string) $request->query->get('action', ''));
        $from = trim((string) $request->query->get('from', ''));
        $to = trim((string) $request->query->get('to', ''));

        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = 50;

        $qb = $em->getRepository(AdminLog::class)->createQueryBuilder('l')
            ->leftJoin('l.admin', 'a')->addSelect('a')
            ->orderBy('l.id', 'DESC');

        if ($action !== '') {
            $qb->andWhere('l.action = :act')->setParameter('act', $action);
        }

        if ($q !== '') {
            $qb->andWhere('l.action LIKE :q OR l.entity LIKE :q OR a.email LIKE :q OR l.ip LIKE :q')
                ->setParameter('q', '%' . $q . '%');
        }

        if ($from !== '') {
            try {
                $fromDt = new \DateTimeImmutable($from . ' 00:00:00');
                $qb->andWhere('l.createdAt >= :from')->setParameter('from', $fromDt);
            } catch (\Throwable $e) {
            }
        }

        if ($to !== '') {
            try {
                $toDt = new \DateTimeImmutable($to . ' 23:59:59');
                $qb->andWhere('l.createdAt <= :to')->setParameter('to', $toDt);
            } catch (\Throwable $e) {
            }
        }

        $countQb = clone $qb;
        $total = (int) $countQb
            ->select('COUNT(l.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;

        $logs = $qb
            ->setFirstResult($offset)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        $actions = $em->createQueryBuilder()
            ->select('DISTINCT l2.action')
            ->from(AdminLog::class, 'l2')
            ->orderBy('l2.action', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();

        return $this->render('admin/logs.html.twig', [
            'logs' => $logs,
            'actions' => $actions,
            'filters' => [
                'q' => $q,
                'action' => $action,
                'from' => $from,
                'to' => $to,
            ],
            'pagination' => [
                'page' => $page,
                'pages' => $pages,
                'perPage' => $perPage,
                'total' => $total,
            ],
        ]);
    }

    #[Route('/logs/export.csv', name: 'admin_logs_export', methods: ['GET'])]
    public function logsExportCsv(Request $request, EntityManagerInterface $em, AdminLogger $logger): Response
    {
        $q = trim((string) $request->query->get('q', ''));
        $action = trim((string) $request->query->get('action', ''));
        $from = trim((string) $request->query->get('from', ''));
        $to = trim((string) $request->query->get('to', ''));

        $qb = $em->getRepository(AdminLog::class)->createQueryBuilder('l')
            ->leftJoin('l.admin', 'a')->addSelect('a')
            ->orderBy('l.id', 'DESC');

        if ($action !== '') {
            $qb->andWhere('l.action = :ac')->setParameter('ac', $action);
        }

        if ($q !== '') {
            $qb->andWhere('l.action LIKE :q OR l.entity LIKE :q OR l.ip LIKE :q OR a.email LIKE :q')
                ->setParameter('q', '%' . $q . '%');
        }

        if ($from !== '') {
            try {
                $fromDt = new \DateTimeImmutable($from . ' 00:00:00');
                $qb->andWhere('l.createdAt >= :from')->setParameter('from', $fromDt);
            } catch (\Throwable $e) {
            }
        }

        if ($to !== '') {
            try {
                $toDt = new \DateTimeImmutable($to . ' 23:59:59');
                $qb->andWhere('l.createdAt <= :to')->setParameter('to', $toDt);
            } catch (\Throwable $e) {
            }
        }

        $logs = $qb->setMaxResults(5000)->getQuery()->getResult();

        $rows = [];
        $rows[] = ['id', 'created_at', 'action', 'actor_email', 'entity', 'entity_id', 'ip', 'details_json'];

        foreach ($logs as $l) {
            $rows[] = [
                (string) $l->getId(),
                $l->getCreatedAt()->format('Y-m-d H:i:s'),
                (string) $l->getAction(),
                $l->getAdmin() ? $l->getAdmin()->getEmail() : '',
                (string) ($l->getEntity() ?? ''),
                (string) ($l->getEntityId() ?? ''),
                (string) ($l->getIp() ?? ''),
                json_encode($l->getDetails(), JSON_UNESCAPED_UNICODE),
            ];
        }

        $csv = $this->toCsv($rows);

        $logger->log('logs.export_csv', 'AdminLog', null, [
            'filters' => ['q' => $q, 'action' => $action, 'from' => $from, 'to' => $to],
            'count' => count($logs),
        ]);

        return new Response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="admin_logs_export.csv"',
        ]);
    }

    #[Route('/security/ips', name: 'admin_security_ips', methods: ['GET'])]
    public function securityIps(Request $request, EntityManagerInterface $em, LoginIpBlocker $blocker): Response
    {
        $q = trim((string) $request->query->get('q', ''));
        $onlyBlocked = trim((string) $request->query->get('onlyBlocked', ''));

        $cut = new \DateTimeImmutable('-7 days');

        $qb = $em->createQueryBuilder()
            ->select('l')
            ->from(AdminLog::class, 'l')
            ->where('l.action = :act')
            ->andWhere('l.createdAt >= :cut')
            ->andWhere('l.ip IS NOT NULL')
            ->setParameter('act', 'security.ip_blocked')
            ->setParameter('cut', $cut)
            ->orderBy('l.id', 'DESC')
            ->setMaxResults(500);

        if ($q !== '') {
            $qb->andWhere('l.ip LIKE :q')->setParameter('q', '%' . $q . '%');
        }

        $logs = $qb->getQuery()->getResult();

        $map = [];
        foreach ($logs as $log) {
            if (!$log instanceof AdminLog) {
                continue;
            }

            $ip = $log->getIp();
            if (!$ip) {
                continue;
            }

            if (!isset($map[$ip])) {
                $map[$ip] = [
                    'ip' => $ip,
                    'lastAt' => $log->getCreatedAt(),
                    'details' => $log->getDetails(),
                    'isBlocked' => $blocker->isBlocked($ip),
                ];
            }
        }

        $ips = array_values($map);

        if ($onlyBlocked === '1') {
            $ips = array_values(array_filter($ips, fn ($row) => !empty($row['isBlocked'])));
        }

        usort($ips, function ($a, $b) {
            $ab = (int) ($b['isBlocked'] ?? false) <=> (int) ($a['isBlocked'] ?? false);
            if ($ab !== 0) {
                return $ab;
            }

            $ta = $a['lastAt']?->getTimestamp() ?? 0;
            $tb = $b['lastAt']?->getTimestamp() ?? 0;
            return $tb <=> $ta;
        });

        return $this->render('admin/security_ips.html.twig', [
            'ips' => $ips,
            'filters' => [
                'q' => $q,
                'onlyBlocked' => $onlyBlocked,
            ],
        ]);
    }

    #[Route('/security/ip/unblock', name: 'admin_security_ip_unblock', methods: ['POST'])]
    public function securityIpUnblock(
        Request $request,
        LoginIpBlocker $blocker,
        AdminLogger $logger
    ): Response {
        $ip = trim((string) $request->request->get('ip', ''));

        if ($ip === '') {
            $this->addFlash('danger', 'Brak IP.');
            return $this->redirectToRoute('admin_security_ips');
        }

        if (!$this->isCsrfTokenValid('admin_unblock_ip_' . $ip, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Błędny token.');
        }

        $blocker->unblock($ip);

        $logger->log('security.ip_unblocked', 'Security', null, ['ip' => $ip]);

        $this->addFlash('success', 'Odblokowano IP: ' . $ip);
        return $this->redirectToRoute('admin_security_ips');
    }

    #[Route('/health', name: 'admin_health', methods: ['GET'])]
    public function health(EntityManagerInterface $em): Response
    {
        $dbOk = false;
        $dbInfo = [
            'driver' => '',
            'serverVersion' => '',
            'database' => '',
            'tables' => null,
            'error' => null,
        ];

        try {
            $conn = $em->getConnection();
            $params = $conn->getParams();

            $dbInfo['driver'] = (string) ($params['driver'] ?? '');
            $dbInfo['database'] = (string) ($params['dbname'] ?? '');
            $dbInfo['serverVersion'] = (string) $conn->fetchOne('SELECT VERSION()');

            if ($dbInfo['database'] !== '') {
                $dbInfo['tables'] = (int) $conn->fetchOne(
                    'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ?',
                    [$dbInfo['database']]
                );
            }

            $dbOk = true;
        } catch (\Throwable $e) {
            $dbOk = false;
            $dbInfo['error'] = $e->getMessage();
        }

        $projectDir = $this->getParameter('kernel.project_dir');
        $cacheDir = $this->getParameter('kernel.cache_dir');
        $logDir = $this->getParameter('kernel.logs_dir');
        $uploadsDir = $this->getParameter('upload_directory');
        $publicDir = $projectDir . '/public';

        $diskFree = @disk_free_space($projectDir);
        $diskTotal = @disk_total_space($projectDir);

        $disk = [
            'free' => is_numeric($diskFree) ? (int) $diskFree : null,
            'total' => is_numeric($diskTotal) ? (int) $diskTotal : null,
        ];

        return $this->render('admin/health.html.twig', [
            'phpVersion' => PHP_VERSION,
            'symfonyEnv' => (string) $this->getParameter('kernel.environment'),
            'symfonyDebug' => (bool) $this->getParameter('kernel.debug'),
            'dbOk' => $dbOk,
            'dbInfo' => $dbInfo,
            'paths' => [
                'project' => $projectDir,
                'public' => $publicDir,
                'cache' => $cacheDir,
                'logs' => $logDir,
                'uploads' => $uploadsDir,
            ],
            'writable' => [
                'cache' => is_writable($cacheDir),
                'logs' => is_writable($logDir),
                'uploads' => is_writable($uploadsDir),
                'public' => is_writable($publicDir),
            ],
            'disk' => $disk,
        ]);
    }

    private function toCsv(array $rows): string
    {
        $fh = fopen('php://temp', 'r+');
        fwrite($fh, "\xEF\xBB\xBF");

        foreach ($rows as $row) {
            fputcsv($fh, $row, ';');
        }

        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);

        return (string) $csv;
    }
}