<?php

namespace App\Controller;

use App\Entity\Car;
use App\Entity\Reservation;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\HttpFoundation\Request;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;


class ReservationController extends AbstractController
{
    #[Route('/reservations', name: 'app_reservations')]
    public function index(Security $security, EntityManagerInterface $entityManager): Response
    {
        $user = $security->getUser();

        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $myReservations = $entityManager->getRepository(Reservation::class)->findBy(['user' => $user]);

        $ownedCars = $entityManager->getRepository(Car::class)->findBy(['owner' => $user]);
        $carReservations = [];
        foreach ($ownedCars as $car) {
            $carReservations = array_merge($carReservations, $entityManager->getRepository(Reservation::class)->findBy(['car' => $car]));
        }

        return $this->render('reservations/reservations.html.twig', [
            'myReservations' => $myReservations,
            'carReservations' => $carReservations,
        ]);
    }
    #[Route('/reservations/my', name: 'app_my_reservations')]
    public function myReservations(Security $security, EntityManagerInterface $entityManager): Response
    {
        $user = $security->getUser();

        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // Pobieramy rezerwacje użytkownika
        $reservations = $entityManager->getRepository(Reservation::class)->findBy(['user' => $user]);

        return $this->render('reservations/my_reservations.html.twig', [
            'reservations' => $reservations
        ]);
    }
    #[Route('/cars/{id}/details', name: 'app_car_details')]
    public function carDetails(Car $car): Response
    {
        return $this->render('cars/reservation_details.html.twig', [
            'car' => $car,
        ]);
    }



    #[Route('/cars/{id}/reserve', name: 'app_reserve_car')]
    public function reserveCar(Request $request, Car $car, EntityManagerInterface $entityManager, Security $security): Response
    {
        $user = $security->getUser();

        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        if ($car->getOwner() === $user) {
            $this->addFlash('danger', 'Nie możesz zarezerwować własnego samochodu.');
            return $this->redirectToRoute('app_car_details', ['id' => $car->getId()]);
        }

        $reservation = new Reservation();
        $form = $this->createFormBuilder($reservation)
            ->add('startDate', DateTimeType::class, ['widget' => 'single_text'])
            ->add('endDate', DateTimeType::class, ['widget' => 'single_text'])
            ->add('comment', TextareaType::class, ['required' => false])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $reservation->setUser($user);
            $reservation->setCar($car);
            $reservation->setStatus('pending');

            $entityManager->persist($reservation);
            $entityManager->flush();

            $this->addFlash('success', 'Rezerwacja została złożona!');
            return $this->redirectToRoute('app_reservations');
        }

        return $this->render('reservations/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }



    #[Route('/reservations/{id}/accept', name: 'app_accept_reservation', methods: ['POST'])]
    public function acceptReservation(Reservation $reservation, Security $security, EntityManagerInterface $entityManager): Response
    {
        $user = $security->getUser();

        if (!$user || $reservation->getCar()->getOwner() !== $user) {
            $this->addFlash('danger', 'Nie masz uprawnień do akceptowania tej rezerwacji.');
            return $this->redirectToRoute('app_reservations');
        }

        if ($reservation->getStatus() !== 'pending') {
            $this->addFlash('warning', 'Nie można już zmienić statusu tej rezerwacji.');
            return $this->redirectToRoute('app_reservations');
        }

        $reservation->setStatus('accepted');
        $entityManager->flush();

        $this->addFlash('success', 'Rezerwacja została zaakceptowana.');
        return $this->redirectToRoute('app_reservations');
    }

    #[Route('/reservations/{id}/reject', name: 'app_reject_reservation', methods: ['POST'])]
    public function rejectReservation(Reservation $reservation, Security $security, EntityManagerInterface $entityManager): Response
    {
        $user = $security->getUser();

        if (!$user || $reservation->getCar()->getOwner() !== $user) {
            $this->addFlash('danger', 'Nie masz uprawnień do odrzucenia tej rezerwacji.');
            return $this->redirectToRoute('app_reservations');
        }

        if ($reservation->getStatus() !== 'pending') {
            $this->addFlash('warning', 'Nie można już zmienić statusu tej rezerwacji.');
            return $this->redirectToRoute('app_reservations');
        }

        $reservation->setStatus('rejected');
        $entityManager->flush();

        $this->addFlash('danger', 'Rezerwacja została odrzucona.');
        return $this->redirectToRoute('app_reservations');
    }




    #[Route('/reservations/history', name: 'app_reservation_history')]
    public function reservationHistory(Security $security, EntityManagerInterface $entityManager): Response
    {
        $user = $security->getUser();

        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // Pobieramy rezerwacje użytkownika
        $reservations = $entityManager->getRepository(Reservation::class)->findBy([
            'user' => $user
        ]);

        return $this->render('reservations/history_reservations.html.twig', [
            'reservations' => $reservations
        ]);
    }

    #[Route('/reservations/{id}/contract', name: 'app_generate_contract')]
    public function generateContract(Reservation $reservation): Response
    {
        // Konfiguracja Dompdf
        $pdfOptions = new Options();
        $pdfOptions->set('defaultFont', 'Arial');

        $dompdf = new Dompdf($pdfOptions);

        // Pobieramy dane rezerwacji
        $user = $reservation->getUser(); // Najemca
        $car = $reservation->getCar(); // Samochód
        $owner = $car->getOwner(); // Właściciel samochodu
        $startDate = $reservation->getStartDate()->format('Y-m-d');
        $endDate = $reservation->getEndDate()->format('Y-m-d');

        // Obliczamy cenę
        $days = $reservation->getStartDate()->diff($reservation->getEndDate())->days;
        $totalPrice = $days * $car->getPricePerDay();

        // Tworzymy HTML faktury
        $html = '
        <h1>Faktura</h1>
        <p><strong>Najemca:</strong> ' . $user->getFullName() . ' (' . $user->getEmail() . ')</p>
        <p><strong>Właściciel pojazdu:</strong> ' . $owner->getFullName() . ' (' . $owner->getEmail() . ')</p>
        <p><strong>Samochód:</strong> ' . $car->getBrand() . ' ' . $car->getModel() . ' (' . $car->getYear() . ')</p>
        <p><strong>Numer rejestracyjny:</strong> ' . $car->getRegistrationNumber() . '</p>
        <p><strong>Okres wynajmu:</strong> ' . $startDate . ' - ' . $endDate . ' (' . $days . ' dni)</p>
        <p><strong>Cena za dzień:</strong> ' . number_format($car->getPricePerDay(), 2) . ' PLN</p>
        <h3><strong>Łączna kwota:</strong> ' . number_format($totalPrice, 2) . ' PLN</h3>
    ';

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="faktura.pdf"'
        ]);
    }
    #[Route('/reservations/{id}/rental-agreement', name: 'app_generate_rental_agreement')]
    public function generateRentalAgreement(Reservation $reservation): Response
    {
        $pdfOptions = new Options();
        $pdfOptions->set('defaultFont', 'Arial');

        $dompdf = new Dompdf($pdfOptions);

        $user = $reservation->getUser();
        $car = $reservation->getCar();
        $owner = $car->getOwner();
        $startDate = $reservation->getStartDate()->format('Y-m-d');
        $endDate = $reservation->getEndDate()->format('Y-m-d');

        $days = $reservation->getStartDate()->diff($reservation->getEndDate())->days;
        $totalPrice = $days * $car->getPricePerDay();

        $html = '
        <h1>Umowa najmu pojazdu</h1>
        <p><strong>Najemca:</strong> ' . $user->getFullName() . ' (' . $user->getEmail() . ')</p>
        <p><strong>Właściciel pojazdu:</strong> ' . $owner->getFullName() . ' (' . $owner->getEmail() . ')</p>
        <p><strong>Samochód:</strong> ' . $car->getBrand() . ' ' . $car->getModel() . ' (' . $car->getYear() . ')</p>
        <p><strong>Numer rejestracyjny:</strong> ' . $car->getRegistrationNumber() . '</p>
        <p><strong>Okres wynajmu:</strong> ' . $startDate . ' - ' . $endDate . ' (' . $days . ' dni)</p>
        <p><strong>Cena za dzień:</strong> ' . number_format($car->getPricePerDay(), 2) . ' PLN</p>
        <h3><strong>Łączna kwota:</strong> ' . number_format($totalPrice, 2) . ' PLN</h3>
    ';

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="umowa_najmu.pdf"'
        ]);
    }
    #[Route('/reservations/{id<\d+>}', name: 'app_reservation_details')]
    public function reservationDetails(int $id, EntityManagerInterface $entityManager, Security $security): Response
    {
        $reservation = $entityManager->getRepository(Reservation::class)->find($id);

        if (!$reservation) {
            throw $this->createNotFoundException('Nie znaleziono rezerwacji o ID: ' . $id);
        }

        $user = $security->getUser();

        // Sprawdź, czy użytkownik ma dostęp do tej rezerwacji
        if ($reservation->getCar()->getOwner() !== $user && $reservation->getUser() !== $user) {
            throw $this->createAccessDeniedException('Nie masz uprawnień do podglądu tej rezerwacji.');
        }

        return $this->render('reservations/reservation_details.html.twig', [
            'reservation' => $reservation,
        ]);
    }
    #[Route('/cars/{id}/availability', name: 'app_car_availability', methods: ['GET'])]
    public function getCarAvailability(Car $car, EntityManagerInterface $entityManager): Response
    {
        $reservations = $entityManager->getRepository(Reservation::class)->findBy(['car' => $car]);
        $events = [];

        foreach ($reservations as $reservation) {
            $events[] = [
                'title' => 'Zajęty',
                'start' => $reservation->getStartDate()->format('Y-m-d'),
                'end' => $reservation->getEndDate()->format('Y-m-d'),
                'color' => 'red',
                'status' => 'reserved'
            ];
        }

        return $this->json($events);
    }
    #[Route('/cars/{id}/confirm-reservation', name: 'app_confirm_reservation', methods: ['GET', 'POST'])]
    public function confirmReservation(Request $request, Car $car, EntityManagerInterface $entityManager): Response
    {
        if ($request->isMethod('GET')) {
            $startDate = $request->query->get('start_date');
            $endDate = $request->query->get('end_date');

            if (!$startDate || !$endDate) {
                throw $this->createNotFoundException('Nie podano daty rezerwacji.');
            }

            return $this->render('cars/confirm_reservation.html.twig', [
                'car' => $car,
                'startDate' => $startDate,
                'endDate' => $endDate,
            ]);
        }

        // Jeśli metoda to POST – tworzymy rezerwację
        $startDate = new \DateTime($request->request->get('start_date'));
        $endDate = new \DateTime($request->request->get('end_date'));
        $phoneNumber = $request->request->get('phoneNumber');
        $comments = $request->request->get('comments');

        $today = new \DateTime();
        $today->setTime(0, 0);

        // ❌ Blokowanie rezerwacji w przeszłości
        if ($startDate < $today || $endDate < $today) {
            $this->addFlash('danger', 'Nie można dokonać rezerwacji na przeszłe dni.');
            return $this->redirectToRoute('app_car_details', ['id' => $car->getId()]);
        }

        $reservation = new Reservation();
        $reservation->setCar($car);
        $reservation->setUser($this->getUser());
        $reservation->setStartDate($startDate);
        $reservation->setEndDate($endDate);
        $reservation->setPhoneNumber($phoneNumber);
        $reservation->setComments($comments);
        $reservation->setStatus('pending');

        $entityManager->persist($reservation);
        $entityManager->flush();

        $this->addFlash('success', 'Rezerwacja została pomyślnie złożona.');
        return $this->redirectToRoute('app_reservations');
    }
    #[Route('/cars/{id}/finalize-reservation', name: 'app_finalize_reservation', methods: ['POST'])]
    public function finalizeReservation(Request $request, Car $car, EntityManagerInterface $entityManager, Security $security): Response
    {
        $user = $security->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $startDate = new \DateTime($request->request->get('start_date'));
        $endDate = new \DateTime($request->request->get('end_date'));
        $phoneNumber = $request->request->get('phone_number');
        $rentalLocation = $request->request->get('rental_location');
        $comments = $request->request->get('comments');

        $reservation = new Reservation();
        $reservation->setUser($user);
        $reservation->setCar($car);
        $reservation->setStartDate($startDate);
        $reservation->setEndDate($endDate);
        $reservation->setPhoneNumber($phoneNumber);
        $reservation->setRentalLocation($rentalLocation);
        $reservation->setComments($comments);
        $reservation->setConfirmed(false);

        $entityManager->persist($reservation);
        $entityManager->flush();

        $this->addFlash('success', 'Rezerwacja została złożona i oczekuje na potwierdzenie.');
        return $this->redirectToRoute('app_list_cars');
    }





}
