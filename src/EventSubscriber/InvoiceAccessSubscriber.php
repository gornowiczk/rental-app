<?php

namespace App\EventSubscriber;

use App\Entity\Reservation;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

#[AsEventListener(event: 'kernel.request', priority: 10)]
final class InvoiceAccessSubscriber
{
    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        // chronimy tylko route faktury
        if ($request->attributes->get('_route') !== 'app_invoice_generate') {
            return;
        }

        /** @var Reservation|null $reservation */
        $reservation = $request->attributes->get('reservation');

        // jeśli ParamConverter nie zadziałał (np. brak rezerwacji) – nie blokujemy tutaj
        if (!$reservation instanceof Reservation) {
            return;
        }

        // ✅ faktura dopiero po completed
        if ((string) $reservation->getStatus() !== 'completed') {
            throw new AccessDeniedHttpException('Faktura dostępna dopiero po zakończeniu rezerwacji.');
        }
    }
}
