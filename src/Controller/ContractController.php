<?php

namespace App\Controller;

use App\Entity\Reservation;
use App\Repository\ReservationRepository;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class ContractController extends AbstractController
{
    #[IsGranted('ROLE_USER')]
    #[Route('/contracts', name: 'app_contract_list', methods: ['GET'])]
    public function list(ReservationRepository $repo): Response
    {
        $user = $this->getUser();

        // Pobieramy tylko rezerwacje zalogowanego użytkownika (najemcy)
        $reservations = $repo->findBy(['user' => $user], ['startDate' => 'DESC']);

        return $this->render('contract/list.html.twig', [
            'reservations' => $reservations,
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/contract/{id}', name: 'app_contract_generate', methods: ['GET'])]
    public function generateContract(Reservation $reservation): Response
    {
        $user = $this->getUser();

        // Dostęp: najemca albo właściciel auta
        if ($reservation->getUser() !== $user && $reservation->getCar()->getOwner() !== $user) {
            throw $this->createAccessDeniedException();
        }

        $status = (string) $reservation->getStatus();

        // Dokument nie jest dostępny dla rezerwacji odrzuconych lub anulowanych.
        if (in_array($status, ['rejected', 'cancelled'], true)) {
            $this->addFlash('warning', 'Dokumenty nie są dostępne dla odrzuconej/anulowanej rezerwacji.');
            return $this->redirectToRoute('app_reservations_show', ['id' => $reservation->getId(), 'from' => 'reservations']);
        }

        // Umowa jest dostępna po zaakceptowaniu albo zakończeniu rezerwacji.
        if (!in_array($status, ['accepted', 'completed'], true)) {
            $this->addFlash('warning', 'Umowa będzie dostępna po akceptacji rezerwacji.');
            return $this->redirectToRoute('app_reservations_show', ['id' => $reservation->getId(), 'from' => 'reservations']);
        }

        $html = $this->renderView('contract/contract.html.twig', [
            'reservation' => $reservation,
        ]);

        $pdfOptions = new Options();
        $pdfOptions->set('defaultFont', 'Arial');
        $pdfOptions->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($pdfOptions);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $pdfOutput = $dompdf->output();

        return new Response($pdfOutput, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="Umowa_Najmu.pdf"',
        ]);
    }
    #[IsGranted('ROLE_USER')]
    #[Route('/protocol/{id}', name: 'app_protocol_generate', methods: ['GET'])]
    public function generateProtocol(Reservation $reservation): Response
    {
        $user = $this->getUser();

        if ($reservation->getUser() !== $user && $reservation->getCar()->getOwner() !== $user) {
            throw $this->createAccessDeniedException();
        }

        $status = (string) $reservation->getStatus();

        if (!in_array($status, ['accepted', 'completed'], true)) {
            $this->addFlash('warning', 'Protokół jest dostępny po akceptacji rezerwacji.');
            return $this->redirectToRoute('app_reservations_show', [
                'id' => $reservation->getId(),
                'from' => 'reservations',
            ]);
        }

        $html = $this->renderView('contract/protocol.html.twig', [
            'reservation' => $reservation,
        ]);

        $pdfOptions = new Options();
        $pdfOptions->set('defaultFont', 'DejaVu Sans');
        $pdfOptions->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($pdfOptions);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="Protokol_Wydania_Zwrotu.pdf"',
        ]);
    }
}
