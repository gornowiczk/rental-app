<?php

namespace App\EventSubscriber;

use App\Entity\Notification;
use App\Repository\NotificationRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

class NotificationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly NotificationRepository $notificationRepo,
        private readonly Environment $twig
    ) {}

    public function onKernelController(ControllerEvent $event): void
    {
        $user = $this->security->getUser();
        if (!$user) {
            // gość – nic nie robimy
            return;
        }

        $unreadCount = $this->notificationRepo->count(['user' => $user, 'isRead' => false]);
        $latest = $this->notificationRepo->findBy(
            ['user' => $user],
            ['createdAt' => 'DESC'],
            5
        );

        // globalne zmienne Twig
        $this->twig->addGlobal('navbar_notifications', $latest);
        $this->twig->addGlobal('navbar_notifications_count', $unreadCount);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => 'onKernelController',
        ];
    }
}
