<?php

namespace App\Tests;

use App\Entity\Car;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CarControllerTest extends WebTestCase
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

    public function testAnonymousUserCannotAccessAddCarPage(): void
    {
        $this->client->request(
            'GET',
            '/cars/add'
        );

        self::assertResponseRedirects('/login');
    }

    public function testLoggedUserCanAccessAddCarPage(): void
    {
        $user = $this->createUser(
            'car-add@test.pl'
        );

        $this->client->loginUser($user);

        $this->client->request(
            'GET',
            '/cars/add'
        );

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
    }

    public function testOwnerCanAccessEditCarPage(): void
    {
        $owner = $this->createUser(
            'car-owner-edit@test.pl'
        );

        $car = $this->createCar(
            $owner,
            'CAR-EDIT-1'
        );

        $this->client->loginUser($owner);

        $this->client->request(
            'GET',
            '/cars/' . $car->getId() . '/edit'
        );

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
    }

    public function testOtherUserCannotEditCar(): void
    {
        $owner = $this->createUser(
            'car-owner-security@test.pl'
        );

        $otherUser = $this->createUser(
            'car-other-security@test.pl'
        );

        $car = $this->createCar(
            $owner,
            'CAR-SEC-1'
        );

        $this->client->loginUser($otherUser);

        $this->client->request(
            'GET',
            '/cars/' . $car->getId() . '/edit'
        );

        self::assertResponseStatusCodeSame(403);
    }

    public function testOtherUserCannotDeleteCar(): void
    {
        $owner = $this->createUser(
            'car-owner-delete@test.pl'
        );

        $otherUser = $this->createUser(
            'car-other-delete@test.pl'
        );

        $car = $this->createCar(
            $owner,
            'CAR-DELETE-1'
        );

        $carId = $car->getId();

        self::assertNotNull($carId);

        $this->client->loginUser($otherUser);

        $this->client->request(
            'POST',
            '/cars/delete/' . $carId
        );

        self::assertResponseStatusCodeSame(403);

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()
            ->get('doctrine')
            ->getManager();

        $em->clear();

        $existingCar = $em
            ->getRepository(Car::class)
            ->find($carId);

        self::assertNotNull($existingCar);
    }

    public function testUserCanAccessOwnCarsPage(): void
    {
        $owner = $this->createUser(
            'car-my@test.pl'
        );

        $this->createCar(
            $owner,
            'CAR-MY-1'
        );

        $this->client->loginUser($owner);

        $this->client->request(
            'GET',
            '/cars/my'
        );

        self::assertResponseIsSuccessful();
    }

    public function testCarDetailsPageLoads(): void
    {
        $owner = $this->createUser(
            'car-details@test.pl'
        );

        $car = $this->createCar(
            $owner,
            'CAR-DETAILS-1'
        );

        $this->client->request(
            'GET',
            '/cars/' . $car->getId()
        );

        self::assertResponseIsSuccessful();
    }

    public function testNonExistingCarReturns404(): void
    {
        $this->client->request(
            'GET',
            '/cars/999999999'
        );

        self::assertResponseStatusCodeSame(404);
    }

    public function testCarListPageLoads(): void
    {
        $owner = $this->createUser(
            'car-list@test.pl'
        );

        $this->createCar(
            $owner,
            'CAR-LIST-1'
        );

        $this->client->request(
            'GET',
            '/cars/all'
        );

        self::assertResponseIsSuccessful();
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

    private function createCar(
        User $owner,
        string $registrationNumber
    ): Car {
        $car = new Car();

        $car->setOwner($owner);
        $car->setBrand('Audi');
        $car->setModel('A6');
        $car->setYear(2010);
        $car->setRegistrationNumber(
            $registrationNumber
        );
        $car->setPricePerDay('250.00');
        $car->setLocation('Gdynia');
        $car->setIsAvailable(true);

        $this->em->persist($car);
        $this->em->flush();

        return $car;
    }
}
