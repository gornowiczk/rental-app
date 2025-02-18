<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Car;
use App\Entity\Reservation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    #[Route('/', name: 'admin_dashboard')]
    public function dashboard(EntityManagerInterface $entityManager): Response
    {
        return $this->render('admin/dashboard.html.twig', [
            'users' => $entityManager->getRepository(User::class)->findAll(),
            'cars' => $entityManager->getRepository(Car::class)->findAll(),
            'reservations' => $entityManager->getRepository(Reservation::class)->findAll(),
        ]);
    }

    // 🚗 ZARZĄDZANIE SAMOCHODAMI
    #[Route('/cars', name: 'admin_cars')]
    public function manageCars(EntityManagerInterface $entityManager): Response
    {
        return $this->render('admin/cars.html.twig', [
            'cars' => $entityManager->getRepository(Car::class)->findAll(),
        ]);
    }

    #[Route('/car/delete/{id}', name: 'admin_delete_car', methods: ['POST'])]
    public function deleteCar(Car $car, EntityManagerInterface $entityManager): Response
    {
        $entityManager->remove($car);
        $entityManager->flush();
        $this->addFlash('success', 'Samochód został usunięty.');
        return $this->redirectToRoute('admin_cars');
    }

    // 👤 ZARZĄDZANIE UŻYTKOWNIKAMI
    #[Route('/users', name: 'admin_users')]
    public function manageUsers(EntityManagerInterface $entityManager): Response
    {
        return $this->render('admin/users.html.twig', [
            'users' => $entityManager->getRepository(User::class)->findAll(),
        ]);
    }

    #[Route('/user/delete/{id}', name: 'admin_delete_user', methods: ['POST'])]
    public function deleteUser(User $user, EntityManagerInterface $entityManager): Response
    {
        $entityManager->remove($user);
        $entityManager->flush();
        $this->addFlash('success', 'Użytkownik został usunięty.');
        return $this->redirectToRoute('admin_users');
    }

    // 📅 ZARZĄDZANIE REZERWACJAMI
    #[Route('/reservations', name: 'admin_reservations')]
    public function manageReservations(EntityManagerInterface $entityManager): Response
    {
        return $this->render('admin/reservations.html.twig', [
            'reservations' => $entityManager->getRepository(Reservation::class)->findAll(),
        ]);
    }

    #[Route('/reservation/accept/{id}', name: 'admin_accept_reservation', methods: ['POST'])]
    public function acceptReservation(Reservation $reservation, EntityManagerInterface $entityManager): Response
    {
        $reservation->setStatus('accepted');
        $entityManager->flush();
        $this->addFlash('success', 'Rezerwacja została zaakceptowana.');
        return $this->redirectToRoute('admin_reservations');
    }

    #[Route('/reservation/reject/{id}', name: 'admin_reject_reservation', methods: ['POST'])]
    public function rejectReservation(Reservation $reservation, EntityManagerInterface $entityManager): Response
    {
        $reservation->setStatus('rejected');
        $entityManager->flush();
        $this->addFlash('danger', 'Rezerwacja została odrzucona.');
        return $this->redirectToRoute('admin_reservations');
    }
}
