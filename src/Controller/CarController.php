<?php

namespace App\Controller;

use App\Entity\Car;
use App\Entity\CarServiceLog;
use App\Entity\Reservation;
use App\Form\CarServiceLogType;
use App\Form\CarType;
use App\Form\ReservationType;
use App\Form\ReviewType;
use App\Repository\CarRepository;
use App\Repository\ReviewRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;


#[Route('/cars')]
final class CarController extends AbstractController
{
    public function __construct(private readonly string $uploadDirectory)
    {
    }

    #[Route('/all', name: 'app_car_list', methods: ['GET'])]
    public function list(Request $request, CarRepository $carRepository): Response
    {
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

        $allowedSorts = ['year', 'pricePerDay', 'brand', 'model'];
        $sortBy = $request->query->get('sortBy', 'year');
        $order = strtoupper($request->query->get('order', 'DESC'));

        if (!in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'year';
        }

        if (!in_array($order, ['ASC', 'DESC'], true)) {
            $order = 'DESC';
        }

        $cars = method_exists($carRepository, 'findCarsByFilters')
            ? $carRepository->findCarsByFilters($filters, $sortBy, $order)
            : $carRepository->findBy([], [$sortBy => $order]);

        return $this->render('cars/list.html.twig', [
            'cars' => $cars,
            'filters' => $filters,
            'sortBy' => $sortBy,
            'order' => $order,
        ]);
    }

    #[Route('/{id}', name: 'app_car_details', methods: ['GET'])]
    public function details(
        int $id,
        CarRepository $carRepository,
        ReviewRepository $reviewRepository,
        Request $request
    ): Response {
        $car = $carRepository->find($id);

        if (!$car) {
            throw $this->createNotFoundException('Samochód nie został znaleziony.');
        }

        $isOwner = $this->getUser()
            && $car->getOwner()
            && $this->getUser()->getId() === $car->getOwner()->getId();

        $reviews = $reviewRepository->findBy(
            ['car' => $car],
            ['createdAt' => 'DESC']
        );

        $serviceLogs = $car->getServiceLogs()->toArray();
        usort($serviceLogs, static function (CarServiceLog $a, CarServiceLog $b): int {
            $dateA = $a->getServiceDate()?->getTimestamp() ?? 0;
            $dateB = $b->getServiceDate()?->getTimestamp() ?? 0;

            if ($dateA === $dateB) {
                return ($b->getCreatedAt()?->getTimestamp() ?? 0) <=> ($a->getCreatedAt()?->getTimestamp() ?? 0);
            }

            return $dateB <=> $dateA;
        });

        $avg = 0.0;
        if (!empty($reviews)) {
            $sum = 0;
            foreach ($reviews as $review) {
                $sum += (int) $review->getRating();
            }
            $avg = $sum / count($reviews);
        }

        $reviewForm = null;
        if ($this->getUser() && !$isOwner) {
            $reviewForm = $this->createForm(ReviewType::class)->createView();
        }

        $reservationForm = null;
        if ($this->getUser() && !$isOwner && $car->isRentable()) {
            $reservationForm = $this->createForm(ReservationType::class)->createView();
        }

        $prefill = [
            'start' => $request->query->get('start'),
            'end' => $request->query->get('end'),
        ];

        return $this->render('cars/reservation_details.html.twig', [
            'car' => $car,
            'isOwner' => $isOwner,
            'reviews' => $reviews,
            'serviceLogs' => $serviceLogs,
            'avg' => $avg,
            'reviewForm' => $reviewForm,
            'reservationForm' => $reservationForm,
            'prefill' => $prefill,
        ]);
    }

    #[Route('/{id}/availability', name: 'app_car_availability', methods: ['GET'])]
    public function availability(Car $car, EntityManagerInterface $em): JsonResponse
    {
        $reservations = $em->getRepository(Reservation::class)->findBy([
            'car' => $car,
            'status' => ['pending', 'accepted'],
        ]);

        $events = [];

        foreach ($reservations as $reservation) {
            $events[] = [
                'title' => 'Zajęte',
                'start' => $reservation->getStartDate()->format('Y-m-d'),
                'end' => (clone $reservation->getEndDate())->modify('+1 day')->format('Y-m-d'),
                'color' => '#dc3545',
            ];
        }

        $pausedUntil = method_exists($car, 'getPausedUntil') ? $car->getPausedUntil() : null;

        if ($pausedUntil instanceof \DateTimeInterface) {
            $today = new \DateTimeImmutable('today');
            $pausedUntilDate = \DateTimeImmutable::createFromInterface($pausedUntil)->setTime(0, 0);

            if ($pausedUntilDate >= $today) {
                $events[] = [
                    'title' => 'Wstrzymane',
                    'start' => $today->format('Y-m-d'),
                    'end' => $pausedUntilDate->modify('+1 day')->format('Y-m-d'),
                    'color' => '#f59e0b',
                ];
            }
        }

        return new JsonResponse($events);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/add', name: 'app_add_car', methods: ['GET', 'POST'])]
    public function addCar(Request $request, EntityManagerInterface $em): Response
    {
        $car = new Car();

        $form = $this->createForm(CarType::class, $car, [
            'validation_groups' => ['Default'],
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $car->setOwner($this->getUser());

                $mainImage = $form->get('mainImage')->getData();
                if ($mainImage) {
                    $fileName = bin2hex(random_bytes(8)) . '.' . $mainImage->guessExtension();
                    $mainImage->move($this->uploadDirectory, $fileName);
                    $car->setMainImage($fileName);
                }

                $galleryFiles = $form->get('gallery')->getData();
                if ($galleryFiles) {
                    $storedFiles = [];
                    foreach ($galleryFiles as $file) {
                        $fileName = bin2hex(random_bytes(8)) . '.' . $file->guessExtension();
                        $file->move($this->uploadDirectory, $fileName);
                        $storedFiles[] = $fileName;
                    }
                    $car->setGallery($storedFiles);
                }

                $em->persist($car);
                $em->flush();

                $this->addFlash('success', 'Samochód został dodany.');
                return $this->redirectToRoute('app_my_cars');
            }

            $this->addFlash('danger', 'Formularz zawiera błędy. Popraw je poniżej.');
        }

        return $this->render('cars/add.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/{id}/edit', name: 'app_car_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Car $car, EntityManagerInterface $em): Response
    {
        if ($car->getOwner() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(CarType::class, $car, [
            'validation_groups' => ['Default'],
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $removeMain = $request->request->get('remove_main');
                $removeGallery = $request->request->all('remove_gallery');

                if ($removeMain && $car->getMainImage()) {
                    $oldMain = rtrim($this->uploadDirectory, '/') . '/' . $car->getMainImage();
                    if (is_file($oldMain)) {
                        @unlink($oldMain);
                    }
                    $car->setMainImage(null);
                }

                if (!empty($removeGallery)) {
                    $currentGallery = $car->getGallery() ?? [];
                    $updatedGallery = [];

                    foreach ($currentGallery as $imageName) {
                        if (in_array($imageName, $removeGallery, true)) {
                            $galleryFile = rtrim($this->uploadDirectory, '/') . '/' . $imageName;
                            if (is_file($galleryFile)) {
                                @unlink($galleryFile);
                            }
                            continue;
                        }

                        $updatedGallery[] = $imageName;
                    }

                    $car->setGallery($updatedGallery);
                }

                $mainImage = $form->get('mainImage')->getData();
                if ($mainImage) {
                    if ($car->getMainImage()) {
                        $oldMain = rtrim($this->uploadDirectory, '/') . '/' . $car->getMainImage();
                        if (is_file($oldMain)) {
                            @unlink($oldMain);
                        }
                    }

                    $fileName = bin2hex(random_bytes(8)) . '.' . $mainImage->guessExtension();
                    $mainImage->move($this->uploadDirectory, $fileName);
                    $car->setMainImage($fileName);
                }

                $newGallery = $form->get('gallery')->getData();
                if ($newGallery) {
                    $existingGallery = $car->getGallery() ?? [];

                    foreach ($newGallery as $file) {
                        $fileName = bin2hex(random_bytes(8)) . '.' . $file->guessExtension();
                        $file->move($this->uploadDirectory, $fileName);
                        $existingGallery[] = $fileName;
                    }

                    $car->setGallery($existingGallery);
                }

                $em->flush();

                $this->addFlash('success', 'Zmiany zapisane.');
                return $this->redirectToRoute('app_my_cars');
            }

            $this->addFlash('danger', 'Formularz zawiera błędy. Popraw je poniżej.');
        }

        return $this->render('cars/edit.html.twig', [
            'form' => $form->createView(),
            'car' => $car,
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/my', name: 'app_my_cars', methods: ['GET'])]
    public function myCars(CarRepository $carRepository): Response
    {
        $cars = $carRepository->findBy(['owner' => $this->getUser()], ['id' => 'DESC']);

        return $this->render('cars/my_cars.html.twig', [
            'cars' => $cars,
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/{id}/service-log/add', name: 'app_car_service_log_add', methods: ['GET', 'POST'])]
    public function addServiceLog(Request $request, Car $car, EntityManagerInterface $em): Response
    {
        if ($car->getOwner() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $serviceLog = new CarServiceLog();
        $serviceLog->setCar($car);

        $form = $this->createForm(CarServiceLogType::class, $serviceLog);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $em->persist($serviceLog);

                if ($serviceLog->getType() === 'service' && $serviceLog->getServiceDate()) {
                    $car->setLastServiceDate($serviceLog->getServiceDate());
                }

                if ($serviceLog->getMileage() !== null) {
                    $car->setMileage($serviceLog->getMileage());
                }

                if ($serviceLog->getType() === 'inspection' && $serviceLog->getServiceDate()) {
                    $inspectionValidUntil = (clone $serviceLog->getServiceDate())->modify('+1 year');
                    $car->setInspectionValidUntil($inspectionValidUntil);
                }

                if ($serviceLog->getType() === 'insurance' && $serviceLog->getServiceDate()) {
                    $insuranceValidUntil = (clone $serviceLog->getServiceDate())->modify('+1 year');
                    $car->setInsuranceValidUntil($insuranceValidUntil);
                }

                $em->flush();

                $this->addFlash('success', 'Wpis serwisowy został dodany.');
                return $this->redirectToRoute('app_car_details', ['id' => $car->getId()]);
            }

            $this->addFlash('danger', 'Formularz zawiera błędy. Popraw dane.');
        }

        return $this->render('cars/service_log_add.html.twig', [
            'car' => $car,
            'form' => $form->createView(),
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/delete/{id}', name: 'app_delete_car', methods: ['POST'])]
    public function delete(Request $request, Car $car, EntityManagerInterface $em): Response
    {
        if ($car->getOwner() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('delete_car_' . $car->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Błędny token.');
        }

        if ($car->getMainImage()) {
            $mainFile = rtrim($this->uploadDirectory, '/') . '/' . $car->getMainImage();
            if (is_file($mainFile)) {
                @unlink($mainFile);
            }
        }

        foreach ($car->getGallery() ?? [] as $galleryImage) {
            $galleryFile = rtrim($this->uploadDirectory, '/') . '/' . $galleryImage;
            if (is_file($galleryFile)) {
                @unlink($galleryFile);
            }
        }

        $em->remove($car);
        $em->flush();

        $this->addFlash('success', 'Samochód usunięty.');
        return $this->redirectToRoute('app_my_cars');
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/{id}/toggle', name: 'app_car_toggle', methods: ['POST'])]
    public function toggleAvailability(Request $request, Car $car, EntityManagerInterface $em): Response
    {
        if ($car->getOwner() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('toggle_car_' . $car->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Błędny token.');
        }

        $car->setIsAvailable(!$car->isAvailable());
        $em->flush();

        $this->addFlash(
            'success',
            $car->isAvailable() ? 'Ogłoszenie ponownie dostępne.' : 'Ogłoszenie wstrzymane.'
        );

        return $this->redirectToRoute('app_car_details', [
            'id' => $car->getId(),
        ]);
    }
}