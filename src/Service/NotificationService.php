<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\Reservation;
use App\Entity\ReservationChangeRequest;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class NotificationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ParameterBagInterface $params,
        private readonly ?MailerInterface $mailer = null,
    ) {
    }

    public function notify(
        User $user,
        string $title,
        ?string $body = null,
        ?Reservation $reservation = null,
        ?string $emailTemplate = null,
        array $emailContext = []
    ): Notification {
        $notification = (new Notification())
            ->setUser($user)
            ->setTitle($title)
            ->setBody($body)
            ->setReservation($reservation);

        $this->em->persist($notification);
        $this->em->flush();

        if ($this->mailer && $user->getEmail()) {
            $fromEmail = (string) (
                $this->params->has('mailer_from')
                    ? $this->params->get('mailer_from')
                    : 'no-reply@carrental.local'
            );

            $fromName = (string) (
                $this->params->has('seller_name')
                    ? $this->params->get('seller_name')
                    : 'CarRental'
            );

            $email = (new TemplatedEmail())
                ->from(new Address($fromEmail, $fromName))
                ->to((string) $user->getEmail())
                ->subject($title);

            if ($emailTemplate) {
                $email
                    ->htmlTemplate($emailTemplate)
                    ->context(array_merge([
                        'title' => $title,
                        'body' => $body,
                        'reservation' => $reservation,
                        'user' => $user,
                    ], $emailContext));
            } else {
                $safeTitle = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $safeBody = nl2br(htmlspecialchars((string) $body, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
                $email->html("<p>{$safeTitle}</p><p>{$safeBody}</p>");
            }

            try {
                $this->mailer->send($email);
            } catch (\Throwable) {
            }
        }

        return $notification;
    }

    public function reservationCreated(Reservation $reservation): void
    {
        $this->notify(
            $reservation->getCar()->getOwner(),
            'Nowa rezerwacja na Twoje auto',
            sprintf(
                'Użytkownik %s zarezerwował auto %s %s (%s → %s).',
                $reservation->getUser()->getEmail(),
                $reservation->getCar()->getBrand(),
                $reservation->getCar()->getModel(),
                $reservation->getStartDate()->format('Y-m-d'),
                $reservation->getEndDate()->format('Y-m-d')
            ),
            $reservation,
            'emails/reservation_created.html.twig'
        );
    }

    public function reservationAccepted(Reservation $reservation): void
    {
        $this->notify(
            $reservation->getUser(),
            'Rezerwacja zaakceptowana',
            sprintf(
                'Właściciel zaakceptował Twoją rezerwację %s %s (%s → %s).',
                $reservation->getCar()->getBrand(),
                $reservation->getCar()->getModel(),
                $reservation->getStartDate()->format('Y-m-d'),
                $reservation->getEndDate()->format('Y-m-d')
            ),
            $reservation,
            'emails/reservation_status.html.twig',
            ['status' => 'accepted']
        );
    }

    public function reservationRejected(Reservation $reservation): void
    {
        $this->notify(
            $reservation->getUser(),
            'Rezerwacja odrzucona',
            sprintf(
                'Właściciel odrzucił Twoją rezerwację %s %s (%s → %s).',
                $reservation->getCar()->getBrand(),
                $reservation->getCar()->getModel(),
                $reservation->getStartDate()->format('Y-m-d'),
                $reservation->getEndDate()->format('Y-m-d')
            ),
            $reservation,
            'emails/reservation_status.html.twig',
            ['status' => 'rejected']
        );
    }

    public function reservationCancelled(Reservation $reservation): void
    {
        $this->notify(
            $reservation->getCar()->getOwner(),
            'Rezerwacja anulowana',
            sprintf(
                'Najemca anulował rezerwację auta %s %s (%s → %s).',
                $reservation->getCar()->getBrand(),
                $reservation->getCar()->getModel(),
                $reservation->getStartDate()->format('Y-m-d'),
                $reservation->getEndDate()->format('Y-m-d')
            ),
            $reservation
        );
    }

    public function reservationCompleted(Reservation $reservation): void
    {
        $this->notify(
            $reservation->getUser(),
            'Rezerwacja zakończona',
            sprintf(
                'Rezerwacja auta %s %s została oznaczona jako zakończona.',
                $reservation->getCar()->getBrand(),
                $reservation->getCar()->getModel()
            ),
            $reservation
        );
    }

    public function reservationChangeRequested(ReservationChangeRequest $changeRequest): void
    {
        $reservation = $changeRequest->getReservation();
        if (!$reservation) {
            return;
        }

        $owner = $reservation->getCar()->getOwner();

        $this->notify(
            $owner,
            'Wniosek o zmianę terminu rezerwacji',
            sprintf(
                'Najemca %s prosi o zmianę terminu dla %s %s: %s → %s (powód: %s).',
                $reservation->getUser()->getEmail(),
                $reservation->getCar()->getBrand(),
                $reservation->getCar()->getModel(),
                $changeRequest->getNewStartDate()?->format('Y-m-d') ?? '—',
                $changeRequest->getNewEndDate()?->format('Y-m-d') ?? '—',
                $changeRequest->getMessage() ?: 'brak'
            ),
            $reservation,
            'emails/reservation_change_requested.html.twig',
            ['changeRequest' => $changeRequest]
        );
    }

    public function reservationChangeAccepted(ReservationChangeRequest $changeRequest): void
    {
        $reservation = $changeRequest->getReservation();
        if (!$reservation) {
            return;
        }

        $this->notify(
            $reservation->getUser(),
            'Zmiana terminu zaakceptowana',
            sprintf(
                'Właściciel zaakceptował zmianę terminu: %s → %s.',
                $changeRequest->getNewStartDate()?->format('Y-m-d') ?? '—',
                $changeRequest->getNewEndDate()?->format('Y-m-d') ?? '—'
            ),
            $reservation,
            'emails/reservation_change_decision.html.twig',
            [
                'changeRequest' => $changeRequest,
                'decision' => 'accepted',
            ]
        );
    }

    public function reservationChangeRejected(ReservationChangeRequest $changeRequest): void
    {
        $reservation = $changeRequest->getReservation();
        if (!$reservation) {
            return;
        }

        $this->notify(
            $reservation->getUser(),
            'Zmiana terminu odrzucona',
            'Właściciel odrzucił wniosek o zmianę terminu.',
            $reservation,
            'emails/reservation_change_decision.html.twig',
            [
                'changeRequest' => $changeRequest,
                'decision' => 'rejected',
            ]
        );
    }
}