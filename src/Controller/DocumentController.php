<?php

namespace App\Controller;

use App\Repository\DocumentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class DocumentController extends AbstractController
{
    #[Route('/documents', name: 'app_documents', methods: ['GET'])]
    public function index(DocumentRepository $repo): Response
    {
        $items = $repo->latestFor($this->getUser(), 100);
        return $this->render('documents/index.html.twig', ['items' => $items]);
    }

    // mały widżet (ostatnie 5) – użyjesz w dashboardzie lub gdzie chcesz
    #[Route('/documents/widget', name: 'app_documents_widget', methods: ['GET'])]
    public function widget(DocumentRepository $repo): Response
    {
        return $this->render('documents/_widget.html.twig', [
            'items' => $repo->latestFor($this->getUser(), 5)
        ]);
    }
}
