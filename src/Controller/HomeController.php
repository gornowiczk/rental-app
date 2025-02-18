<?php

namespace App\Controller;

use App\Repository\CarRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(CarRepository $carRepository): Response
    {
        // Pobierz losowe 6 samochodów
        $cars = $carRepository->findBy([], null, 6);

        return $this->render('home/index.html.twig', [
            'cars' => $cars
        ]);
    }
}
