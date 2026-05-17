<?php

namespace App\Controller;

use App\Entity\Car;
use App\Entity\Notification;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;

class HomeController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function index(
        Security $security,
        EntityManagerInterface $em,
        NotificationRepository $notificationRepo
    ): Response {
        $user = $security->getUser();

        $notifications = [];
        $notificationsUnreadCount = 0;

        if ($user) {
            $notifications = $notificationRepo->findBy(
                ['user' => $user],
                ['createdAt' => 'DESC'],
                5
            );
            $notificationsUnreadCount = $notificationRepo->countUnreadByUser($user);
        }

        $cars = $em->getRepository(Car::class)->findAll();

        return $this->render('home/index.html.twig', [
            'cars' => $cars,
            'notifications' => $notifications,
            'notificationsUnreadCount' => $notificationsUnreadCount,
        ]);
    }
}
