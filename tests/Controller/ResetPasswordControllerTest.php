<?php

namespace App\Tests;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ResetPasswordControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private UserRepository $userRepository;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $container = static::getContainer();

        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine')->getManager();
        $this->em = $em;

        $this->userRepository = $container->get(UserRepository::class);

        $existingUser = $this->userRepository->findOneBy([
            'email' => 'me@example.com',
        ]);

        if ($existingUser) {
            $connection = $this->em->getConnection();

            $connection->executeStatement(
                'DELETE FROM reset_password_request WHERE user_id = ?',
                [$existingUser->getId()]
            );

            $this->em->remove($existingUser);
            $this->em->flush();
        }
    }

    public function testResetPasswordPageLoads(): void
    {
        $this->client->request('GET', '/reset-password');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
    }

    public function testResetPasswordRequestForExistingUser(): void
    {
        $user = (new User())
            ->setEmail('me@example.com')
            ->setPassword('test-password');

        $this->em->persist($user);
        $this->em->flush();

        $crawler = $this->client->request('GET', '/reset-password');

        self::assertResponseIsSuccessful();

        $form = $crawler->filter('form')->form([
            'reset_password_request_form[email]' => 'me@example.com',
        ]);

        $this->client->submit($form);

        self::assertResponseRedirects('/reset-password/check-email');
    }
}
