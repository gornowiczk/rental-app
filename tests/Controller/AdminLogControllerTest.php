<?php

namespace App\Tests;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AdminLogControllerTest extends WebTestCase
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

    public function testAnonymousUserCannotAccessAdminLogs(): void
    {
        $this->client->request(
            'GET',
            '/admin/logs/logs'
        );

        self::assertResponseRedirects('/login');
    }

    public function testNormalUserCannotAccessAdminLogs(): void
    {
        $user = $this->createUser(
            'logs-user@test.pl',
            ['ROLE_USER']
        );

        $this->client->loginUser($user);

        $this->client->request(
            'GET',
            '/admin/logs/logs'
        );

        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminCannotAccessSuperAdminLogs(): void
    {
        $admin = $this->createUser(
            'logs-admin@test.pl',
            ['ROLE_ADMIN']
        );

        $this->client->loginUser($admin);

        $this->client->request(
            'GET',
            '/admin/logs/logs'
        );

        self::assertResponseStatusCodeSame(403);
    }

    public function testSuperAdminCanAccessLogs(): void
    {
        $superAdmin = $this->createUser(
            'logs-superadmin@test.pl',
            [
                'ROLE_USER',
                'ROLE_ADMIN',
                'ROLE_SUPER_ADMIN',
            ]
        );

        $this->client->loginUser(
            $superAdmin
        );

        $this->client->request(
            'GET',
            '/admin/logs/logs'
        );

        self::assertResponseIsSuccessful();
    }

    private function createUser(
        string $email,
        array $roles
    ): User {
        $user = new User();

        $user->setEmail($email);
        $user->setPassword('test-password');
        $user->setRoles($roles);

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }
}

