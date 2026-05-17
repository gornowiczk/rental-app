<?php

namespace App\Service;

use App\Entity\Reservation;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;

final class NotificationSender
{
    public function __construct(private readonly MailerInterface $mailer) {}

    public function sendReservationCreated(Reservation $r): void
    {
        // Do najemcy
        $this->send(
            to: $r->getUser()->getEmail(),
            subject: 'Potwierdzenie złożenia rezerwacji',
            template: 'emails/reservation_created.html.twig',
            context: ['r' => $r]
        );

        // Do właściciela
        $this->send(
            to: $r->getCar()->getOwner()->getEmail(),
            subject: 'Nowa rezerwacja na Twoje auto',
            template: 'emails/reservation_created_owner.html.twig',
            context: ['r' => $r]
        );
    }

    public function sendReservationAccepted(Reservation $r): void
    {
        $this->send(
            to: $r->getUser()->getEmail(),
            subject: 'Rezerwacja zaakceptowana',
            template: 'emails/reservation_accepted.html.twig',
            context: ['r' => $r]
        );
    }

    public function sendReservationRejected(Reservation $r): void
    {
        $this->send(
            to: $r->getUser()->getEmail(),
            subject: 'Rezerwacja odrzucona',
            template: 'emails/reservation_rejected.html.twig',
            context: ['r' => $r]
        );
    }

    private function send(string $to, string $subject, string $template, array $context): void
    {
        $email = (new TemplatedEmail())
            ->to($to)
            ->subject($subject)
            ->htmlTemplate($template)
            ->context($context);

        $this->mailer->send($email);
    }
}
