<?php

namespace App\Tests;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AccessControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $this->em = static::getContainer()
            ->get('doctrine')
            ->getManager();
    }

    public function testHomepageLoads(): void
    {
        $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
    }

    public function testCarListLoads(): void
    {
        $this->client->request('GET', '/cars/all');

        self::assertResponseIsSuccessful();
    }

    public function testAnonymousUserCannotAccessAdminPanel(): void
    {
        $this->client->request('GET', '/admin/');

        self::assertResponseRedirects('/login');
    }

    public function testNormalUserCannotAccessAdminPanel(): void
    {
        $user = $this->createUser(
            'user@example.com',
            ['ROLE_USER']
        );

        $this->client->loginUser($user);

        $this->client->request('GET', '/admin/');

        self::assertResponseStatusCodeSame(403);
    }

    public function testAdministratorCanAccessAdminPanel(): void
    {
        $admin = $this->createUser(
            'admin@example.com',
            ['ROLE_ADMIN']
        );

        $this->client->loginUser($admin);

        $this->client->request('GET', '/admin/');

        self::assertResponseIsSuccessful();
    }

    private function createUser(
        string $email,
        array $roles
    ): User {
        $existingUser = $this->em
            ->getRepository(User::class)
            ->findOneBy(['email' => $email]);

        if ($existingUser) {
            $this->em->remove($existingUser);
            $this->em->flush();
        }

        $user = new User();
        $user->setEmail($email);
        $user->setPassword('test-password');
        $user->setRoles($roles);

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }
}
