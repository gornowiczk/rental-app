<?php
namespace App\Controller;

use App\Entity\Reservation;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ContractController extends AbstractController
{
#[Route('/contract/{id}', name: 'app_contract_generate')]
public function generateContract(Reservation $reservation): Response
{
$html = $this->renderView('contract/contract.html.twig', [
'reservation' => $reservation
]);

$pdfOptions = new Options();
$pdfOptions->set('defaultFont', 'Arial');

$dompdf = new Dompdf($pdfOptions);
$dompdf->loadHtml($html);
$dompdf->render();

return new Response($dompdf->stream("Umowa_Najmu.pdf", ["Attachment" => true]));
}
}
