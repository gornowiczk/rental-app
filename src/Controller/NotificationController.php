<?php

namespace App\Controller;

use App\Entity\Notification;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
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
    #[Route('/{id}/read', name: 'app_notification_read', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function markAsRead(Request $request, Notification $notification, EntityManagerInterface $em): Response
    {
        if ($notification->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('notification_read_' . $notification->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Bledny token.');
        }

        $notification->markAsRead();
        $em->flush();

        return $this->redirectToRoute('app_notifications');
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/read-all', name: 'app_notification_read_all', methods: ['POST'])]
    public function markAllAsRead(Request $request, NotificationRepository $repo, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('notification_read_all', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Bledny token.');
        }

        foreach ($repo->findBy(['user' => $this->getUser(), 'isRead' => false]) as $notification) {
            $notification->markAsRead();
        }

        $em->flush();

        return $this->redirectToRoute('app_notifications');
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/{id}/open', name: 'app_notification_open', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function open(Notification $notification, EntityManagerInterface $em): Response
    {
        if ($notification->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Nie masz uprawnien do tej akcji.');
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
