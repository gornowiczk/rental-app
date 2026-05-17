<?php

namespace App\Controller;

use App\Entity\Notification;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/notifications')]
class NotificationController extends AbstractController
{
    #[IsGranted('ROLE_USER')]
    #[Route('', name: 'app_notifications')]
    public function index(NotificationRepository $repo): Response
    {
        $notifications = $repo->findBy(
            ['user' => $this->getUser()],
            ['createdAt' => 'DESC']
        );

        return $this->render('notifications/index.html.twig', [
            'notifications' => $notifications,
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/{id}/read', name: 'app_notification_read')]
    public function markAsRead(Notification $notification, EntityManagerInterface $em): Response
    {
        if ($notification->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $notification->markAsRead();
        $em->flush();

        return $this->redirectToRoute('app_notifications');
    }
    #[Route('/notifications/{id}/open', name: 'app_notification_open')]
    public function open(Notification $notification, EntityManagerInterface $em): Response
    {
        if ($notification->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Nie masz uprawnień do tej akcji.');
        }

        if (!$notification->isRead()) {
            $notification->setIsRead(true);
            $em->flush();
        }

        if ($notification->getReservation()) {
            return $this->redirectToRoute('app_reservations_show', [
                'id' => $notification->getReservation()->getId(),
            ]);
        }

        return $this->redirectToRoute('app_notifications');
    }


}
