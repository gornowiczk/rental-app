<?php

namespace App\Controller;

use App\Entity\Car;
use App\Entity\Invoice;
use App\Entity\Reservation;
use App\Entity\ReservationChangeRequest;
use App\Repository\InvoiceRepository;
use App\Repository\ReservationChangeRequestRepository;
use App\Repository\ReservationRepository;
use App\Repository\ReservationStatusLogRepository;
use App\Service\NotificationService;
use App\Service\ReservationStatusLogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\JsonResponse;


#[Route('/reservations')]
final class ReservationController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CsrfTokenManagerInterface $csrf,
        private readonly NotificationService $notifier,
        private readonly ReservationStatusLogService $statusLog,
        private readonly ReservationRepository $reservationRepository,
    ) {
    }

    #[IsGranted('ROLE_USER')]
    #[Route('', name: 'app_reservations', methods: ['GET'])]
    public function index(
        ReservationRepository $repo,
        EntityManagerInterface $em
    ): Response {
        $user = $this->getUser();

        $myReservations = $repo->findBy(['user' => $user], ['startDate' => 'DESC']);

        $carReservations = $repo->createQueryBuilder('r')
            ->join('r.car', 'c')
            ->andWhere('c.owner = :owner')
            ->setParameter('owner', $user)
            ->orderBy('r.startDate', 'DESC')
            ->getQuery()
            ->getResult();

        $invoiceExistsMap = [];
        $canCancelMap = [];
        $canChangeMap = [];

        $now = new \DateTimeImmutable();

        foreach ($myReservations as $reservation) {
            $rid = (int) $reservation->getId();

            $canCancelMap[$rid] = $this->canCancelByRenter($reservation, $now);
            $canChangeMap[$rid] = $this->canChangeByRenter($reservation, $now);
            $invoiceExistsMap[$rid] = false;
        }

        $ids = array_map(static fn (Reservation $r) => (int) $r->getId(), $myReservations);
        $ids = array_values(array_filter($ids));

        if ($ids) {
            $rows = $em->createQueryBuilder()
                ->select('IDENTITY(i.reservation) AS rid')
                ->from(Invoice::class, 'i')
                ->andWhere('IDENTITY(i.reservation) IN (:ids)')
                ->setParameter('ids', $ids)
                ->getQuery()
                ->getScalarResult();

            foreach ($rows as $row) {
                $rid = (int) ($row['rid'] ?? 0);
                if ($rid > 0) {
                    $invoiceExistsMap[$rid] = true;
                }
            }
        }

        return $this->render('reservations/index.html.twig', [
            'myReservations'   => $myReservations,
            'carReservations'  => $carReservations,
            'invoiceExistsMap' => $invoiceExistsMap,
            'canCancelMap'     => $canCancelMap,
            'canChangeMap'     => $canChangeMap,
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/cars/{id}/confirm', name: 'app_confirm_reservation', methods: ['GET', 'POST'])]
    public function confirmReservation(Car $car, Request $request): Response
    {
        $user = $this->getUser();

        if (
            $car->getOwner()
            && $user
            && $car->getOwner()->getId() === $user->getId()
        ) {
            $this->addFlash('danger', 'Nie możesz zarezerwować własnego auta.');
            return $this->redirectToRoute('app_car_details', ['id' => $car->getId()]);
        }

        $startRaw = $request->query->get('start') ?? $request->request->get('start');
        $endRaw   = $request->query->get('end') ?? $request->request->get('end');

        if (!$startRaw || !$endRaw) {
            $this->addFlash('danger', 'Brak dat rezerwacji.');
            return $this->redirectToRoute('app_car_details', ['id' => $car->getId()]);
        }

        if ($request->isMethod('POST') && $request->request->has('reserve_token')) {
            $token = (string) $request->request->get('reserve_token');

            if (!$this->csrf->isTokenValid(new CsrfToken('reserve_confirm_' . $car->getId(), $token))) {
                $this->addFlash('danger', 'Błędny token.');
                return $this->redirectToRoute('app_car_details', ['id' => $car->getId()]);
            }
        }

        try {
            $start = self::parseYmdOrFail($startRaw)->setTime(0, 0);
            $end   = self::parseYmdOrFail($endRaw)->setTime(0, 0);
        } catch (\Throwable) {
            $this->addFlash('danger', 'Nieprawidłowe daty.');
            return $this->redirectToRoute('app_car_details', ['id' => $car->getId()]);
        }

        $today = new \DateTimeImmutable('today');

        if ($start < $today) {
            $this->addFlash('danger', 'Nie można zarezerwować terminu w przeszłości.');
            return $this->redirectToRoute('app_car_details', ['id' => $car->getId()]);
        }

        if ($end <= $start) {
            $this->addFlash('danger', 'Data zakończenia musi być późniejsza niż data rozpoczęcia.');
            return $this->redirectToRoute('app_car_details', ['id' => $car->getId()]);
        }

        if (!$car->isAvailable()) {
            $this->addFlash('danger', 'To auto jest obecnie wyłączone z wynajmu.');
            return $this->redirectToRoute('app_car_details', ['id' => $car->getId()]);
        }

        $pausedUntil = method_exists($car, 'getPausedUntil') ? $car->getPausedUntil() : null;

        if ($pausedUntil instanceof \DateTimeInterface) {
            $pausedUntilDate = \DateTimeImmutable::createFromInterface($pausedUntil)->setTime(23, 59, 59);

            if ($start <= $pausedUntilDate) {
                $this->addFlash(
                    'danger',
                    'To auto jest czasowo wstrzymane do ' . $pausedUntilDate->format('Y-m-d') . '. Wybierz termin po tej dacie.'
                );

                return $this->redirectToRoute('app_car_details', ['id' => $car->getId()]);
            }
        }

        if ($this->reservationRepository->hasOverlapForCar($car->getId(), $start, $end)) {
            $this->addFlash('danger', 'Termin jest zajęty.');
            return $this->redirectToRoute('app_car_details', ['id' => $car->getId()]);
        }

        $reservation = new Reservation();
        $reservation->setCar($car);
        $reservation->setUser($user);
        $reservation->setStartDate($start);
        $reservation->setEndDate($end);
        $reservation->setStatus(Reservation::STATUS_PENDING);
        $reservation->setPricePerDay($car->getPricePerDay());

        $this->prefillReservationFromUser($reservation, $user);

        $form = $this->createForm(\App\Form\ReservationConfirmType::class, $reservation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($reservation->wantsInvoice()) {
                $name = trim((string) $reservation->getInvoiceName());
                $street = trim((string) $reservation->getInvoiceStreet());
                $postalCode = trim((string) $reservation->getInvoicePostalCode());
                $city = trim((string) $reservation->getInvoiceCity());

                if ($name === '' || $street === '' || $postalCode === '' || $city === '') {
                    $this->addFlash('danger', 'Uzupełnij dane do faktury: nabywcę, ulicę, kod pocztowy i miasto.');

                    return $this->render('reservations/confirm.html.twig', [
                        'car' => $car,
                        'form' => $form->createView(),
                        'startDate' => $start->format('Y-m-d'),
                        'endDate' => $end->format('Y-m-d'),
                    ]);
                }

                $reservation->setInvoiceAddress(
                    trim($street . ', ' . $postalCode . ' ' . $city)
                );
            } else {
                $reservation->setInvoiceName(null);
                $reservation->setInvoiceAddress(null);
                $reservation->setInvoiceStreet(null);
                $reservation->setInvoicePostalCode(null);
                $reservation->setInvoiceCity(null);
                $reservation->setInvoiceNip(null);
            }

            if ($this->reservationRepository->hasOverlapForCar($car->getId(), $start, $end)) {
                $this->addFlash('danger', 'Termin został właśnie zajęty. Wybierz inny zakres dat.');
                return $this->redirectToRoute('app_car_details', ['id' => $car->getId()]);
            }

            $this->em->persist($reservation);

            $this->statusLog->log(
                $reservation,
                $user,
                null,
                Reservation::STATUS_PENDING,
                $request->getClientIp(),
                (string) $request->headers->get('User-Agent'),
                'Reservation created'
            );

            $this->em->flush();
            $this->notifier->reservationCreated($reservation);

            $this->addFlash('success', 'Rezerwacja została złożona.');
            return $this->redirectToRoute('app_reservations');
        }

        return $this->render('reservations/confirm.html.twig', [
            'car' => $car,
            'form' => $form->createView(),
            'startDate' => $start->format('Y-m-d'),
            'endDate' => $end->format('Y-m-d'),
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/{id}/accept', name: 'app_reservation_accept', methods: ['POST'])]
    public function acceptReservation(Request $request, Reservation $reservation): Response
    {
        if ($reservation->getCar()->getOwner() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isValidToken('reservation_accept_' . $reservation->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Błędny token.');
            return $this->redirectToRoute('app_reservations');
        }

        if (!$reservation->isPending()) {
            $this->addFlash('warning', 'Nie można zaakceptować: rezerwacja nie jest w statusie oczekującym.');
            return $this->redirectToRoute('app_reservations');
        }

        if ($this->reservationRepository->hasOverlapForCar(
            $reservation->getCar()->getId(),
            $reservation->getStartDate(),
            $reservation->getEndDate(),
            $reservation->getId()
        )) {
            $this->addFlash('danger', 'Nie można zaakceptować: termin jest już zajęty.');
            return $this->redirectToRoute('app_reservations');
        }

        $old = $reservation->getStatus();
        $reservation->setStatus(Reservation::STATUS_ACCEPTED);

        $this->statusLog->log(
            $reservation,
            $this->getUser(),
            $old,
            Reservation::STATUS_ACCEPTED,
            $request->getClientIp(),
            (string) $request->headers->get('User-Agent'),
            'Accepted by owner'
        );

        $this->em->flush();
        $this->notifier->reservationAccepted($reservation);

        $this->addFlash('success', 'Rezerwacja zaakceptowana.');
        return $this->redirectToRoute('app_reservations');
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/{id}/reject', name: 'app_reservation_reject', methods: ['POST'])]
    public function rejectReservation(Request $request, Reservation $reservation): Response
    {
        if ($reservation->getCar()->getOwner() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isValidToken('reservation_reject_' . $reservation->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Błędny token.');
            return $this->redirectToRoute('app_reservations');
        }

        if (!$reservation->isPending()) {
            $this->addFlash('warning', 'Nie można odrzucić: rezerwacja nie jest w statusie oczekującym.');
            return $this->redirectToRoute('app_reservations');
        }

        $old = $reservation->getStatus();
        $reservation->setStatus(Reservation::STATUS_REJECTED);

        $this->statusLog->log(
            $reservation,
            $this->getUser(),
            $old,
            Reservation::STATUS_REJECTED,
            $request->getClientIp(),
            (string) $request->headers->get('User-Agent'),
            'Rejected by owner'
        );

        $this->em->flush();
        $this->notifier->reservationRejected($reservation);

        $this->addFlash('success', 'Rezerwacja odrzucona.');
        return $this->redirectToRoute('app_reservations');
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/{id}/cancel', name: 'app_reservation_cancel', methods: ['POST'])]
    public function cancelReservation(Request $request, Reservation $reservation): Response
    {
        if ($reservation->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isValidToken('reservation_cancel_' . $reservation->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Błędny token.');
            return $this->redirectToRoute('app_reservations');
        }

        $now = new \DateTimeImmutable();

        if (!$this->canCancelByRenter($reservation, $now)) {
            $this->addFlash('warning', 'Nie możesz anulować rezerwacji: mniej niż 24h do rozpoczęcia lub status nie pozwala.');
            return $this->redirectToShow($reservation);
        }

        $old = $reservation->getStatus();

        $reservation->setStatus(Reservation::STATUS_CANCELLED);
        $reservation->setCancelledAt($now);
        $reservation->setCancelledBy('tenant');
        $reservation->setCancelReason('Anulowano przez najemcę.');

        $this->statusLog->log(
            $reservation,
            $this->getUser(),
            $old,
            Reservation::STATUS_CANCELLED,
            $request->getClientIp(),
            (string) $request->headers->get('User-Agent'),
            'Cancelled by renter (>=24h before start)'
        );

        $this->em->flush();

        if (method_exists($this->notifier, 'reservationCancelled')) {
            $this->notifier->reservationCancelled($reservation);
        }

        $this->addFlash('success', 'Rezerwacja została anulowana.');
        return $this->redirectToRoute('app_reservations');
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/{id}/change-end', name: 'app_reservation_change_end', methods: ['POST'])]
    public function changeEndDate(Request $request, Reservation $reservation): Response
    {
        if ($reservation->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isValidToken('reservation_change_end_' . $reservation->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Błędny token.');
            return $this->redirectToShow($reservation);
        }

        $now = new \DateTimeImmutable();

        if (!$this->canChangeByRenter($reservation, $now)) {
            $this->addFlash('warning', 'Nie możesz zmienić długości najmu: mniej niż 12h do rozpoczęcia lub status nie pozwala.');
            return $this->redirectToShow($reservation);
        }

        $endRaw = trim((string) $request->request->get('endDate', ''));
        if ($endRaw === '') {
            $this->addFlash('danger', 'Podaj nową datę zakończenia.');
            return $this->redirectToShow($reservation);
        }

        try {
            $newEnd = self::parseYmdOrFail($endRaw)->setTime(0, 0);
        } catch (\Throwable) {
            $this->addFlash('danger', 'Nieprawidłowa data zakończenia.');
            return $this->redirectToShow($reservation);
        }

        $start = \DateTimeImmutable::createFromInterface($reservation->getStartDate())->setTime(0, 0);

        if ($newEnd <= $start) {
            $this->addFlash('danger', 'Data zakończenia musi być późniejsza niż data rozpoczęcia.');
            return $this->redirectToShow($reservation);
        }

        if ($this->reservationRepository->hasOverlapForCar(
            $reservation->getCar()->getId(),
            $start,
            $newEnd,
            $reservation->getId()
        )) {
            $this->addFlash('danger', 'Nie można zmienić: termin jest zajęty.');
            return $this->redirectToShow($reservation);
        }

        $oldEnd = $reservation->getEndDate();
        $reservation->setEndDate($newEnd);

        $this->statusLog->log(
            $reservation,
            $this->getUser(),
            $reservation->getStatus(),
            $reservation->getStatus(),
            $request->getClientIp(),
            (string) $request->headers->get('User-Agent'),
            sprintf(
                'Changed endDate by renter: %s -> %s (>=12h before start)',
                $oldEnd?->format('Y-m-d') ?? '—',
                $newEnd->format('Y-m-d')
            )
        );

        $this->em->flush();

        $this->addFlash('success', 'Zmieniono datę zakończenia rezerwacji.');
        return $this->redirectToShow($reservation);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/{id}/request-change-dates', name: 'app_reservation_request_change_dates', methods: ['POST'])]
    public function requestChangeDates(
        Request $request,
        Reservation $reservation,
        ReservationChangeRequestRepository $changeRepo
    ): Response {
        if ($reservation->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isValidToken(
            'reservation_request_change_dates_' . $reservation->getId(),
            (string) $request->request->get('_token')
        )) {
            $this->addFlash('danger', 'Błędny token.');
            return $this->redirectToShow($reservation);
        }

        if (!$this->canRequestDateChange($reservation, new \DateTimeImmutable())) {
            $this->addFlash('warning', 'Nie możesz już złożyć wniosku o zmianę terminu dla tej rezerwacji.');
            return $this->redirectToShow($reservation);
        }

        $existingPending = $changeRepo->findOneBy([
            'reservation' => $reservation,
            'status' => ReservationChangeRequest::STATUS_PENDING,
        ]);

        if ($existingPending) {
            $this->addFlash('warning', 'Masz już aktywny wniosek o zmianę terminu.');
            return $this->redirectToShow($reservation);
        }

        $newStartRaw = trim((string) $request->request->get('new_start', ''));
        $newEndRaw = trim((string) $request->request->get('new_end', ''));
        $reason = trim((string) $request->request->get('reason', ''));

        if ($newStartRaw === '' || $newEndRaw === '') {
            $this->addFlash('danger', 'Podaj nową datę rozpoczęcia i zakończenia.');
            return $this->redirectToShow($reservation);
        }

        try {
            $newStart = self::parseYmdOrFail($newStartRaw)->setTime(0, 0);
            $newEnd = self::parseYmdOrFail($newEndRaw)->setTime(0, 0);
        } catch (\Throwable) {
            $this->addFlash('danger', 'Nieprawidłowy zakres dat.');
            return $this->redirectToShow($reservation);
        }

        if ($newEnd <= $newStart) {
            $this->addFlash('danger', 'Data zakończenia musi być późniejsza niż data rozpoczęcia.');
            return $this->redirectToShow($reservation);
        }

        if ($this->reservationRepository->hasOverlapForCar(
            $reservation->getCar()->getId(),
            $newStart,
            $newEnd,
            $reservation->getId()
        )) {
            $this->addFlash('danger', 'Nie można złożyć wniosku: wybrany termin jest zajęty.');
            return $this->redirectToShow($reservation);
        }

        $changeRequest = new ReservationChangeRequest();
        $changeRequest->setReservation($reservation);
        $changeRequest->setRequester($this->getUser());
        $changeRequest->setNewStartDate($newStart);
        $changeRequest->setNewEndDate($newEnd);
        $changeRequest->setMessage($reason !== '' ? $reason : null);
        $changeRequest->setStatus(ReservationChangeRequest::STATUS_PENDING);

        $this->em->persist($changeRequest);

        $this->statusLog->log(
            $reservation,
            $this->getUser(),
            $reservation->getStatus(),
            $reservation->getStatus(),
            $request->getClientIp(),
            (string) $request->headers->get('User-Agent'),
            sprintf(
                'Requested date change: %s -> %s',
                $newStart->format('Y-m-d'),
                $newEnd->format('Y-m-d')
            )
        );

        $this->em->flush();

        if (method_exists($this->notifier, 'reservationChangeRequested')) {
            $this->notifier->reservationChangeRequested($changeRequest);
        }

        $this->addFlash('success', 'Wniosek o zmianę terminu został wysłany do właściciela.');
        return $this->redirectToShow($reservation);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/change-request/{id}/accept', name: 'app_reservation_change_request_accept', methods: ['POST'])]
    public function acceptChangeRequest(
        Request $request,
        ReservationChangeRequest $changeRequest
    ): Response {
        $reservation = $changeRequest->getReservation();

        if (!$reservation) {
            throw $this->createNotFoundException('Nie znaleziono rezerwacji dla tego wniosku.');
        }

        if ($reservation->getCar()->getOwner() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isValidToken(
            'reservation_change_request_accept_' . $changeRequest->getId(),
            (string) $request->request->get('_token')
        )) {
            $this->addFlash('danger', 'Błędny token.');
            return $this->redirectToShow($reservation);
        }

        if (!$changeRequest->isPending()) {
            $this->addFlash('warning', 'Ten wniosek został już rozpatrzony.');
            return $this->redirectToShow($reservation);
        }

        $newStart = $changeRequest->getNewStartDate();
        $newEnd = $changeRequest->getNewEndDate();

        if (!$newStart || !$newEnd) {
            $this->addFlash('danger', 'Wniosek nie zawiera poprawnych dat.');
            return $this->redirectToShow($reservation);
        }

        if ($newEnd <= $newStart) {
            $this->addFlash('danger', 'Nieprawidłowy zakres dat we wniosku.');
            return $this->redirectToShow($reservation);
        }

        if ($this->reservationRepository->hasOverlapForCar(
            $reservation->getCar()->getId(),
            $newStart,
            $newEnd,
            $reservation->getId()
        )) {
            $this->addFlash('danger', 'Nie można zaakceptować zmiany: termin jest już zajęty.');
            return $this->redirectToShow($reservation);
        }

        $oldStart = $reservation->getStartDate();
        $oldEnd = $reservation->getEndDate();

        $reservation->setStartDate($newStart);
        $reservation->setEndDate($newEnd);

        $changeRequest->accept($this->getUser());

        $this->statusLog->log(
            $reservation,
            $this->getUser(),
            $reservation->getStatus(),
            $reservation->getStatus(),
            $request->getClientIp(),
            (string) $request->headers->get('User-Agent'),
            sprintf(
                'Accepted date change: %s -> %s / %s -> %s',
                $oldStart?->format('Y-m-d') ?? '—',
                $newStart->format('Y-m-d'),
                $oldEnd?->format('Y-m-d') ?? '—',
                $newEnd->format('Y-m-d')
            )
        );

        $this->em->flush();

        if (method_exists($this->notifier, 'reservationChangeAccepted')) {
            $this->notifier->reservationChangeAccepted($changeRequest);
        }

        $this->addFlash('success', 'Zmiana terminu została zaakceptowana.');
        return $this->redirectToShow($reservation);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/change-request/{id}/reject', name: 'app_reservation_change_request_reject', methods: ['POST'])]
    public function rejectChangeRequest(
        Request $request,
        ReservationChangeRequest $changeRequest
    ): Response {
        $reservation = $changeRequest->getReservation();

        if (!$reservation) {
            throw $this->createNotFoundException('Nie znaleziono rezerwacji dla tego wniosku.');
        }

        if ($reservation->getCar()->getOwner() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isValidToken(
            'reservation_change_request_reject_' . $changeRequest->getId(),
            (string) $request->request->get('_token')
        )) {
            $this->addFlash('danger', 'Błędny token.');
            return $this->redirectToShow($reservation);
        }

        if (!$changeRequest->isPending()) {
            $this->addFlash('warning', 'Ten wniosek został już rozpatrzony.');
            return $this->redirectToShow($reservation);
        }

        $changeRequest->reject($this->getUser());

        $this->statusLog->log(
            $reservation,
            $this->getUser(),
            $reservation->getStatus(),
            $reservation->getStatus(),
            $request->getClientIp(),
            (string) $request->headers->get('User-Agent'),
            sprintf(
                'Rejected date change request: %s -> %s',
                $changeRequest->getNewStartDate()?->format('Y-m-d') ?? '—',
                $changeRequest->getNewEndDate()?->format('Y-m-d') ?? '—'
            )
        );

        $this->em->flush();

        if (method_exists($this->notifier, 'reservationChangeRejected')) {
            $this->notifier->reservationChangeRejected($changeRequest);
        }

        $this->addFlash('success', 'Wniosek o zmianę terminu został odrzucony.');
        return $this->redirectToShow($reservation);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/{id}/complete', name: 'app_reservation_complete', methods: ['POST'])]
    public function completeReservation(Request $request, Reservation $reservation): Response
    {
        if ($reservation->getCar()->getOwner() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isValidToken('reservation_complete_' . $reservation->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Błędny token.');
            return $this->redirectToRoute('app_reservations');
        }

        if (!$reservation->isAccepted()) {
            $this->addFlash('warning', 'Możesz zakończyć ręcznie tylko rezerwację zaakceptowaną.');
            return $this->redirectToRoute('app_reservations');
        }

        $old = $reservation->getStatus();
        $reservation->setStatus(Reservation::STATUS_COMPLETED);

        

        $this->statusLog->log(
            $reservation,
            $this->getUser(),
            $old,
            Reservation::STATUS_COMPLETED,
            $request->getClientIp(),
            (string) $request->headers->get('User-Agent'),
            'Completed manually by owner'
        );

        $this->em->flush();

        if (method_exists($this->notifier, 'reservationCompleted')) {
            $this->notifier->reservationCompleted($reservation);
        }

        $this->addFlash('success', 'Rezerwacja zakończona.');
        return $this->redirectToShow($reservation);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/live-statuses', name: 'app_reservations_live_statuses', methods: ['GET'])]
    public function liveStatuses(ReservationRepository $repo): JsonResponse
    {
        $user = $this->getUser();

        $myReservations = $repo->findBy(['user' => $user]);

        $carReservations = $repo->createQueryBuilder('r')
            ->join('r.car', 'c')
            ->andWhere('c.owner = :owner')
            ->setParameter('owner', $user)
            ->getQuery()
            ->getResult();

        $reservations = array_merge($myReservations, $carReservations);

        $data = [];

        foreach ($reservations as $reservation) {
            $data[] = [
                'id' => $reservation->getId(),
                'status' => $reservation->getStatus(),
            ];
        }

        return $this->json($data);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/{id}', name: 'app_reservations_show', methods: ['GET'])]
    public function show(
        Request $request,
        Reservation $reservation,
        ReservationStatusLogRepository $logRepo,
        InvoiceRepository $invoiceRepo,
        ReservationChangeRequestRepository $changeRepo
    ): Response {
        $user = $this->getUser();
        $isOwner = $reservation->getCar()->getOwner() === $user;
        $isTenant = $reservation->getUser() === $user;

        if (!$isTenant && !$isOwner) {
            throw $this->createAccessDeniedException();
        }

        $from = (string) $request->query->get('from', 'reservations');
        $logs = $logRepo->findForReservation((int) $reservation->getId());
        $invoiceExists = (bool) $invoiceRepo->findOneByReservationId((int) $reservation->getId());

        $now = new \DateTimeImmutable();
        $start = \DateTimeImmutable::createFromInterface($reservation->getStartDate());
        $hoursToStart = ($start->getTimestamp() - $now->getTimestamp()) / 3600.0;

        $canCancel = $this->canCancelByRenter($reservation, $now);
        $canChange = $this->canChangeByRenter($reservation, $now);

        $changeRequests = $changeRepo->findForReservation($reservation);

        $pendingChangeRequests = array_values(array_filter(
            $changeRequests,
            static fn (ReservationChangeRequest $item) => $item->getStatus() === ReservationChangeRequest::STATUS_PENDING
        ));

        return $this->render('reservations/show.html.twig', [
            'reservation' => $reservation,
            'from' => $from,
            'logs' => $logs,
            'invoiceExists' => $invoiceExists,
            'canCancel' => $canCancel,
            'canChange' => $canChange,
            'pendingChangeRequests' => $pendingChangeRequests,
            'changeRequests' => $changeRequests,
            'hoursToStart' => $hoursToStart,
        ]);
    }

    private function prefillReservationFromUser(Reservation $reservation, mixed $user): void
    {
        if (!$user) {
            return;
        }

        if (method_exists($user, 'getPhoneNumber')) {
            $phone = trim((string) $user->getPhoneNumber());
            if ($phone !== '') {
                $reservation->setPhoneNumber($phone);
            }
        }

        if (method_exists($user, 'getFullName')) {
            $fullName = trim((string) $user->getFullName());
            if ($fullName !== '' && !$reservation->getInvoiceName()) {
                $reservation->setInvoiceName($fullName);
            }
        }

        if (method_exists($user, 'getAddress')) {
            $address = trim((string) $user->getAddress());
            if ($address !== '' && !$reservation->getInvoiceAddress()) {
                $reservation->setInvoiceAddress($address);
            }
        }

        if (method_exists($user, 'getPeselOrNip')) {
            $nip = trim((string) $user->getPeselOrNip());
            if ($nip !== '' && !$reservation->getInvoiceNip()) {
                $reservation->setInvoiceNip($nip);
            }
        }
    }

    private function canCancelByRenter(Reservation $reservation, \DateTimeImmutable $now): bool
    {
        if (!$reservation->isPending()) {
            return false;
        }

        $start = $reservation->getStartDate();
        if (!$start instanceof \DateTimeInterface) {
            return false;
        }

        $startDt = \DateTimeImmutable::createFromInterface($start);
        $seconds = $startDt->getTimestamp() - $now->getTimestamp();

        return $seconds >= 24 * 3600;
    }

    private function canChangeByRenter(Reservation $reservation, \DateTimeImmutable $now): bool
    {
        if (!$reservation->isPending()) {
            return false;
        }

        $start = $reservation->getStartDate();
        if (!$start instanceof \DateTimeInterface) {
            return false;
        }

        $startDt = \DateTimeImmutable::createFromInterface($start);
        $seconds = $startDt->getTimestamp() - $now->getTimestamp();

        return $seconds >= 12 * 3600;
    }

    private function canRequestDateChange(Reservation $reservation, \DateTimeImmutable $now): bool
    {
        $start = $reservation->getStartDate();
        if (!$start instanceof \DateTimeInterface) {
            return false;
        }

        $startDt = \DateTimeImmutable::createFromInterface($start);
        $seconds = $startDt->getTimestamp() - $now->getTimestamp();

        if ($reservation->getStatus() === Reservation::STATUS_PENDING) {
            return $seconds >= 12 * 3600;
        }

        if ($reservation->getStatus() === Reservation::STATUS_ACCEPTED) {
            return $seconds >= 24 * 3600;
        }

        return false;
    }

   

    private function isValidToken(string $id, ?string $token): bool
    {
        return $this->csrf->isTokenValid(new CsrfToken($id, (string) $token));
    }

    private function redirectToShow(Reservation $reservation): Response
    {
        return $this->redirectToRoute('app_reservations_show', [
            'id' => $reservation->getId(),
            'from' => 'reservations',
        ]);
    }

    private static function parseYmdOrFail(?string $value): \DateTimeImmutable
    {
        if (!$value) {
            throw new \InvalidArgumentException('Brak daty.');
        }

        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
        return $dt ?: new \DateTimeImmutable($value);
    }
}