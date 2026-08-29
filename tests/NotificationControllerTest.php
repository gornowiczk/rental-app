<?php

namespace App\Tests;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class NotificationControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();

        $this->em = static::getContainer()
            ->get('doctrine')
            ->getManager();

        $this->em->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->em->getConnection()->isTransactionActive()) {
            $this->em->getConnection()->rollBack();
        }

        $this->em->clear();

        parent::tearDown();
    }

    public function testAnonymousUserCannotAccessNotifications(): void
    {
        $this->client->request(
            'GET',
            '/notifications'
        );

        self::assertResponseRedirects('/login');
    }

    public function testLoggedUserCanAccessNotifications(): void
    {
        $user = $this->createUser(
            'notification-user@test.pl'
        );

        $this->createNotification(
            $user,
            'Testowe powiadomienie'
        );

        $this->client->loginUser($user);

        $this->client->request(
            'GET',
            '/notifications'
        );

        self::assertResponseIsSuccessful();
    }

    public function testUserCannotMarkOtherUsersNotificationAsRead(): void
    {
        $owner = $this->createUser(
            'notification-owner@test.pl'
        );

        $otherUser = $this->createUser(
            'notification-other@test.pl'
        );

        $notification = $this->createNotification(
            $owner,
            'Prywatne powiadomienie'
        );

        $notificationId = $notification->getId();

        self::assertNotNull($notificationId);

        $this->client->loginUser($otherUser);

        $this->client->request(
            'POST',
            '/notifications/' . $notificationId . '/read'
        );

        self::assertResponseStatusCodeSame(403);

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()
            ->get('doctrine')
            ->getManager();

        $em->clear();

        $existingNotification = $em
            ->getRepository(Notification::class)
            ->find($notificationId);

        self::assertNotNull($existingNotification);
        self::assertFalse(
            $existingNotification->isRead()
        );
    }

    public function testInvalidCsrfCannotMarkNotificationAsRead(): void
    {
        $user = $this->createUser(
            'notification-csrf@test.pl'
        );

        $notification = $this->createNotification(
            $user,
            'Powiadomienie CSRF'
        );

        $notificationId = $notification->getId();

        self::assertNotNull($notificationId);

        $this->client->loginUser($user);

        $this->client->request(
            'POST',
            '/notifications/' . $notificationId . '/read',
            [
                '_token' => 'invalid-token',
            ]
        );

        self::assertResponseStatusCodeSame(403);

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()
            ->get('doctrine')
            ->getManager();

        $em->clear();

        $existingNotification = $em
            ->getRepository(Notification::class)
            ->find($notificationId);

        self::assertNotNull($existingNotification);
        self::assertFalse(
            $existingNotification->isRead()
        );
    }

    public function testUserCannotOpenOtherUsersNotification(): void
    {
        $owner = $this->createUser(
            'notification-open-owner@test.pl'
        );

        $otherUser = $this->createUser(
            'notification-open-other@test.pl'
        );

        $notification = $this->createNotification(
            $owner,
            'Cudze powiadomienie'
        );

        $notificationId = $notification->getId();

        self::assertNotNull($notificationId);

        $this->client->loginUser($otherUser);

        $this->client->request(
            'GET',
            '/notifications/' . $notificationId . '/open'
        );

        self::assertResponseStatusCodeSame(403);
    }

    public function testOpeningOwnNotificationMarksItAsRead(): void
    {
        $user = $this->createUser(
            'notification-open@test.pl'
        );

        $notification = $this->createNotification(
            $user,
            'Nieprzeczytane powiadomienie'
        );

        $notificationId = $notification->getId();

        self::assertNotNull($notificationId);
        self::assertFalse($notification->isRead());

        $this->client->loginUser($user);

        $this->client->request(
            'GET',
            '/notifications/' . $notificationId . '/open'
        );

        self::assertResponseRedirects(
            '/notifications'
        );

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()
            ->get('doctrine')
            ->getManager();

        $em->clear();

        $updatedNotification = $em
            ->getRepository(Notification::class)
            ->find($notificationId);

        self::assertNotNull($updatedNotification);

        self::assertTrue(
            $updatedNotification->isRead()
        );
    }

    public function testInvalidCsrfCannotMarkAllNotificationsAsRead(): void
    {
        $user = $this->createUser(
            'notification-readall@test.pl'
        );

        $first = $this->createNotification(
            $user,
            'Pierwsze powiadomienie'
        );

        $second = $this->createNotification(
            $user,
            'Drugie powiadomienie'
        );

        $firstId = $first->getId();
        $secondId = $second->getId();

        self::assertNotNull($firstId);
        self::assertNotNull($secondId);

        $this->client->loginUser($user);

        $this->client->request(
            'POST',
            '/notifications/read-all',
            [
                '_token' => 'invalid-token',
            ]
        );

        self::assertResponseStatusCodeSame(403);

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()
            ->get('doctrine')
            ->getManager();

        $em->clear();

        $firstAfter = $em
            ->getRepository(Notification::class)
            ->find($firstId);

        $secondAfter = $em
            ->getRepository(Notification::class)
            ->find($secondId);

        self::assertNotNull($firstAfter);
        self::assertNotNull($secondAfter);

        self::assertFalse($firstAfter->isRead());
        self::assertFalse($secondAfter->isRead());
    }

    private function createUser(string $email): User
    {
        $user = new User();

        $user->setEmail($email);
        $user->setPassword('test-password');
        $user->setRoles(['ROLE_USER']);

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    private function createNotification(
        User $user,
        string $title
    ): Notification {
        $notification = new Notification();

        $notification->setUser($user);
        $notification->setTitle($title);
        $notification->setBody(
            'Treść testowego powiadomienia.'
        );
        $notification->setIsRead(false);

        $this->em->persist($notification);
        $this->em->flush();

        return $notification;
    }
}
