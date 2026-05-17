<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class CarsRedirectController extends AbstractController
{
    #[Route('/cars', name: 'app_cars_redirect', methods: ['GET'])]
    #[Route('/cars/', name: 'app_cars_redirect_slash', methods: ['GET'])]
    public function redirectToList(): Response
    {
        return $this->redirectToRoute('app_car_list');
    }
}
