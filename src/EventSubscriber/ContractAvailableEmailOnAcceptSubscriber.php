<?php

namespace App\EventSubscriber;

use App\Entity\Reservation;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

final class ContractAvailableEmailOnAcceptSubscriber implements EventSubscriber
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly RouterInterface $router,
        private readonly ParameterBagInterface $params,
    ) {}

    public function getSubscribedEvents(): array
    {
        return [Events::onFlush];
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $em = $args->getObjectManager();
        if (!$em instanceof EntityManagerInterface) {
            return;
        }

        $uow = $em->getUnitOfWork();

        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            if (!$entity instanceof Reservation) {
                continue;
            }

            $changeSet = $uow->getEntityChangeSet($entity);

            if (!isset($changeSet['status'])) {
                continue;
            }

            [$oldStatus, $newStatus] = $changeSet['status'];

            if ((string) $newStatus !== 'accepted') {
                continue;
            }

            $user = $entity->getUser();
            if (!$user || !method_exists($user, 'getEmail')) {
                continue;
            }

            $to = trim((string) $user->getEmail());
            if ($to === '') {
                continue;
            }

            $contractUrl = $this->router->generate(
                'app_contract_generate',
                ['id' => $entity->getId()],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            $reservationUrl = $this->router->generate(
                'app_reservations_show',
                ['id' => $entity->getId(), 'from' => 'reservations'],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            $fromEmail = (string) $this->getParam('mailer_from', 'no-reply@carrental.local');
            $fromName  = (string) $this->getParam('seller_name', 'CarRental');

            $email = (new TemplatedEmail())
                ->from(new Address($fromEmail, $fromName))
                ->to($to)
                ->subject('Umowa najmu jest dostępna do pobrania')
                ->htmlTemplate('emails/contract_available.html.twig')
                ->context([
                    'reservation' => $entity,
                    'contract_url' => $contractUrl,
                    'reservation_url' => $reservationUrl,
                    'seller_name' => $fromName,
                ]);

            $this->mailer->send($email);
        }
    }

    private function getParam(string $key, mixed $default = null): mixed
    {
        if (method_exists($this->params, 'has') && $this->params->has($key)) {
            return $this->params->get($key);
        }
        return $default;
    }
}
