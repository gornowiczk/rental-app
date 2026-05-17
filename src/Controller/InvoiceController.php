<?php

namespace App\Controller;

use App\Entity\Reservation;
use App\Repository\InvoiceRepository;
use App\Service\InvoiceService;
use Dompdf\Dompdf;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class InvoiceController extends AbstractController
{
    #[IsGranted('ROLE_USER')]
    #[Route('/reservations/{id}/invoice/create', name: 'app_invoice_create', methods: ['POST'])]
    public function create(
        Request $request,
        Reservation $reservation,
        InvoiceService $invoiceService,
    ): Response {
        $user = $this->getUser();

        if ($reservation->getUser() !== $user && $reservation->getCar()->getOwner() !== $user) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('invoice_create_' . $reservation->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Błędny token.');
            return $this->redirectToRoute('app_reservations_show', ['id' => $reservation->getId()]);
        }

        if ((string) $reservation->getStatus() !== 'completed') {
            $this->addFlash('warning', 'Fakturę można wygenerować dopiero po zakończeniu rezerwacji.');
            return $this->redirectToRoute('app_reservations_show', ['id' => $reservation->getId()]);
        }

        if (!$reservation->wantsInvoice()) {
            $this->addFlash('warning', 'Dla tej rezerwacji nie wybrano faktury.');
            return $this->redirectToRoute('app_reservations_show', ['id' => $reservation->getId()]);
        }

        $invoice = $invoiceService->ensureInvoiceForReservation($reservation);

        if (!$invoice) {
            $this->addFlash('danger', 'Nie udało się wygenerować faktury.');
            return $this->redirectToRoute('app_reservations_show', ['id' => $reservation->getId()]);
        }

        $this->addFlash('success', 'Faktura została wygenerowana.');

        return $this->redirectToRoute('app_invoice_generate', [
            'id' => $reservation->getId(),
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/reservations/{id}/invoice', name: 'app_invoice_generate', methods: ['GET'])]
    public function generate(
        Reservation $reservation,
        Dompdf $dompdf,
        InvoiceRepository $invoiceRepo,
    ): Response {
        $user = $this->getUser();

        if ($reservation->getUser() !== $user && $reservation->getCar()->getOwner() !== $user) {
            throw $this->createAccessDeniedException();
        }

        $invoice = $invoiceRepo->findOneByReservationId((int) $reservation->getId());

        if (!$invoice) {
            $this->addFlash('warning', 'Faktura nie została jeszcze wygenerowana.');
            return $this->redirectToRoute('app_reservations_show', [
                'id' => $reservation->getId(),
                'from' => 'reservations',
            ]);
        }

        $vatRate = (float) $invoice->getVatRate();

        $html = $this->renderView('invoice/invoice.html.twig', [
            'invoice' => $invoice,
            'r' => $reservation,
            'days' => $invoice->getDays(),
            'net_total' => $invoice->getNetTotal(),
            'vat_amount' => $invoice->getVatAmount(),
            'gross_total' => $invoice->getGrossTotal(),
            'vat_rate' => $vatRate,
            'vat_percent' => $vatRate * 100,
        ]);

        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();

        $filename = sprintf(
            'faktura-%s.pdf',
            str_replace(['/', ' '], ['-', ''], $invoice->getInvoiceNumber())
        );

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }
}