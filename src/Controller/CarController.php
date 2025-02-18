<?php

namespace App\Controller;

use App\Entity\Car;
use App\Form\CarType;
use App\Entity\Reservation;
use App\Repository\CarRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\String\Slugger\SluggerInterface;

class CarController extends AbstractController
{
    private string $uploadDirectory;

    public function __construct(string $uploadDirectory)
    {
        $this->uploadDirectory = $uploadDirectory;
    }

    #[Route('/my', name: 'app_my_cars')]
    public function myCars(Security $security, CarRepository $carRepository): Response
    {
        $user = $security->getUser();

        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // Pobieranie tylko samochodów należących do użytkownika
        $cars = $carRepository->findBy(['owner' => $user]);

        return $this->render('cars/my_cars.html.twig', [
            'cars' => $cars
        ]);
    }





    #[Route('/all', name: 'app_list_cars', methods: ['GET'])]
    public function listCars(CarRepository $carRepository): Response
    {
        $cars = $carRepository->findAll();

        return $this->render('cars/list_cars.html.twig', [
            'cars' => $cars
        ]);
    }

    #[Route('/cars/all', name: 'app_car_list')]
    public function list(Request $request, CarRepository $carRepository): Response
    {
        // Pobranie parametrów filtrów z zapytania
        $filters = [
            'brand' => $request->query->get('brand'),
            'model' => $request->query->get('model'),
            'yearFrom' => $request->query->get('yearFrom'),
            'yearTo' => $request->query->get('yearTo'),
            'priceMin' => $request->query->get('priceMin'),
            'priceMax' => $request->query->get('priceMax'),
            'location' => $request->query->get('location'),
            'isAvailable' => $request->query->get('isAvailable'),
        ];

        // Parametry sortowania
        $sortBy = $request->query->get('sortBy', 'year');
        $order = $request->query->get('order', 'ASC');

        // Pobranie przefiltrowanych samochodów z repozytorium
        $cars = $carRepository->findCarsByFilters($filters, $sortBy, $order);

        return $this->render('cars/list.html.twig', [
            'cars' => $cars,
            'filters' => $filters,
            'sortBy' => $sortBy,
            'order' => $order,
        ]);
    }

    #[Route('/{id}', name: 'app_car_details')]
    public function details(int $id, CarRepository $carRepository): Response
    {
        $car = $carRepository->find($id);
        if (!$car) {
            throw $this->createNotFoundException('Samochód nie został znaleziony');
        }

        return $this->render('cars/reservation_details.html.twig', [
            'car' => $car,
        ]);
    }


    #[Route('/cars/add', name: 'app_add_car', methods: ['GET', 'POST'])]
    public function addCar(Request $request, EntityManagerInterface $entityManager, Security $security): Response
    {
        $user = $security->getUser();

        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $car = new Car();
        $form = $this->createForm(CarType::class, $car);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Ustawienie właściciela samochodu
            $car->setOwner($user);

            // Obsługa zdjęcia głównego (jeśli dodano)
            $mainImageFile = $form->get('mainImage')->getData();
            if ($mainImageFile) {
                $newFilename = uniqid() . '.' . $mainImageFile->guessExtension();
                $mainImageFile->move($this->uploadDirectory, $newFilename);
                $car->setMainImage($newFilename);
            }

            // Obsługa galerii zdjęć (jeśli dodano)
            $galleryFiles = $form->get('gallery')->getData();
            if (!empty($galleryFiles)) {
                $gallery = [];
                foreach ($galleryFiles as $file) {
                    $newFilename = uniqid() . '.' . $file->guessExtension();
                    $file->move($this->uploadDirectory, $newFilename);
                    $gallery[] = $newFilename;
                }
                $car->setGallery($gallery);
            }

            // Zapis do bazy danych
            $entityManager->persist($car);
            $entityManager->flush();

            return $this->redirectToRoute('app_my_cars');
        }

        return $this->render('cars/add.html.twig', [
            'form' => $form->createView(),
        ]);
    }









    #[Route('/edit/{id}', name: 'app_edit_car')]
    public function editCar(Car $car, Request $request, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(CarType::class, $car);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_my_cars');
        }

        return $this->render('cars/edit.html.twig', [
            'form' => $form->createView(),
        ]);
    }


    #[Route('/cars/delete/{id}', name: 'app_delete_car', methods: ['POST'])]
    public function deleteCar(Car $car, EntityManagerInterface $entityManager, Security $security): Response
    {
        if ($car->getOwner() !== $security->getUser()) {
            throw $this->createAccessDeniedException('Nie masz uprawnień do usunięcia tego pojazdu.');
        }

        // Usuwamy rezerwacje związane z tym autem
        $reservations = $entityManager->getRepository(Reservation::class)->findBy(['car' => $car]);

        foreach ($reservations as $reservation) {
            $entityManager->remove($reservation);
        }

        // Teraz możemy usunąć auto
        $entityManager->remove($car);
        $entityManager->flush();

        $this->addFlash('success', 'Samochód został usunięty.');
        return $this->redirectToRoute('app_my_cars');
    }
    #[Route('/cars/{id}/availability', name: 'app_car_availability', methods: ['GET'])]
    public function carAvailability(Car $car): JsonResponse
    {
        $reservations = $car->getReservations();
        $events = [];

        foreach ($reservations as $reservation) {
            $events[] = [
                'title' => 'Zarezerwowane',
                'start' => $reservation->getStartDate()->format('Y-m-d'),
                'end' => $reservation->getEndDate()->modify('+1 day')->format('Y-m-d'),
                'color' => 'red'
            ];
        }

        return new JsonResponse($events);
    }




    #[Route('/cars/{id}/reserve', name: 'app_reserve_car', methods: ['GET', 'POST'])]
    public function reserveCar(Car $car, Request $request, EntityManagerInterface $entityManager, Security $security): Response
    {
        $user = $security->getUser();

        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $startDate = $request->query->get('start_date');
        $endDate = $request->query->get('end_date');
        $confirm = $request->query->get('confirm');

        // Sprawdzenie poprawności dat
        if (!$startDate || !$endDate) {
            $this->addFlash('danger', 'Musisz wybrać daty przed potwierdzeniem rezerwacji.');
            return $this->redirectToRoute('app_car_details', ['id' => $car->getId()]);
        }

        if (!$confirm) {
            return $this->render('cars/confirm_reservation.html.twig', [
                'car' => $car,
                'startDate' => $startDate,
                'endDate' => $endDate
            ]);
        }

        // Tworzenie nowej rezerwacji
        $reservation = new Reservation();
        $reservation->setUser($user);
        $reservation->setCar($car);
        $reservation->setStartDate(new \DateTime($startDate));
        $reservation->setEndDate(new \DateTime($endDate));

        $entityManager->persist($reservation);
        $entityManager->flush();

        $this->addFlash('success', 'Rezerwacja została zatwierdzona!');
        return $this->redirectToRoute('app_my_reservations');
    }




}