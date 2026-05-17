<?php

namespace App\EventSubscriber;

use App\Entity\Invoice;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

#[AsDoctrineListener(event: Events::postPersist)]
final class InvoiceIssuedEmailSubscriber
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly RouterInterface $router,
        private readonly ParameterBagInterface $params,
    ) {}

    public function postPersist(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof Invoice) {
            return;
        }

        $reservation = $entity->getReservation();
        if (!$reservation) {
            return;
        }

        $user = $reservation->getUser();
        if (!$user || !method_exists($user, 'getEmail')) {
            return;
        }

        $to = trim((string) $user->getEmail());
        if ($to === '') {
            return;
        }

        $invoiceUrl = $this->router->generate(
            'app_invoice_generate',
            ['id' => $reservation->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $reservationUrl = $this->router->generate(
            'app_reservations_show',
            ['id' => $reservation->getId(), 'from' => 'reservations'],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $fromEmail = (string) ($this->params->has('mailer_from') ? $this->params->get('mailer_from') : 'no-reply@carrental.local');
        $fromName  = (string) ($this->params->has('seller_name') ? $this->params->get('seller_name') : 'CarRental');

        $email = (new TemplatedEmail())
            ->from(new Address($fromEmail, $fromName))
            ->to($to)
            ->subject('Wystawiono fakturę do Twojej rezerwacji')
            ->htmlTemplate('emails/invoice_issued.html.twig')
            ->context([
                'invoice' => $entity,
                'reservation' => $reservation,
                'invoice_url' => $invoiceUrl,
                'reservation_url' => $reservationUrl,
                'seller_name' => $fromName,
            ]);

        $this->mailer->send($email);
    }
}
