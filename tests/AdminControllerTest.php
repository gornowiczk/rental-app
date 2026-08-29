<?php

namespace App\Tests;

use App\Entity\Car;
use App\Entity\Reservation;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AdminControllerTest extends WebTestCase
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

    public function testAnonymousUserCannotAccessAdminDashboard(): void
    {
        $this->client->request(
            'GET',
            '/admin/'
        );

        self::assertResponseRedirects('/login');
    }

    public function testNormalUserCannotAccessAdminDashboard(): void
    {
        $user = $this->createUser(
            'admin-normal-user@test.pl',
            ['ROLE_USER']
        );

        $this->client->loginUser($user);

        $this->client->request(
            'GET',
            '/admin/'
        );

        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminCanAccessDashboard(): void
    {
        $admin = $this->createUser(
            'admin-dashboard@test.pl',
            ['ROLE_ADMIN']
        );

        $this->client->loginUser($admin);

        $this->client->request(
            'GET',
            '/admin/'
        );

        self::assertResponseIsSuccessful();
    }

    public function testAdminCanAccessReports(): void
    {
        $admin = $this->createUser(
            'admin-reports@test.pl',
            ['ROLE_ADMIN']
        );

        $this->client->loginUser($admin);

        $this->client->request(
            'GET',
            '/admin/reports'
        );

        self::assertResponseIsSuccessful();
    }

    public function testAdminCanAccessReservationsList(): void
    {
        $admin = $this->createUser(
            'admin-reservations@test.pl',
            ['ROLE_ADMIN']
        );

        $owner = $this->createUser(
            'admin-res-owner@test.pl',
            ['ROLE_USER']
        );

        $renter = $this->createUser(
            'admin-res-renter@test.pl',
            ['ROLE_USER']
        );

        $car = $this->createCar(
            $owner,
            'ADMIN-RES-1'
        );

        $this->createReservation(
            $car,
            $renter,
            Reservation::STATUS_PENDING
        );

        $this->client->loginUser($admin);

        $this->client->request(
            'GET',
            '/admin/reservations'
        );

        self::assertResponseIsSuccessful();
    }

    public function testNormalUserCannotAccessAdminReservations(): void
    {
        $user = $this->createUser(
            'admin-res-normal@test.pl',
            ['ROLE_USER']
        );

        $this->client->loginUser($user);

        $this->client->request(
            'GET',
            '/admin/reservations'
        );

        self::assertResponseStatusCodeSame(403);
    }

    public function testInvalidCsrfDoesNotChangeReservationStatus(): void
    {
        $admin = $this->createUser(
            'admin-status@test.pl',
            ['ROLE_ADMIN']
        );

        $owner = $this->createUser(
            'admin-status-owner@test.pl',
            ['ROLE_USER']
        );

        $renter = $this->createUser(
            'admin-status-renter@test.pl',
            ['ROLE_USER']
        );

        $car = $this->createCar(
            $owner,
            'ADMIN-STATUS-1'
        );

        $reservation = $this->createReservation(
            $car,
            $renter,
            Reservation::STATUS_PENDING
        );

        $reservationId = $reservation->getId();

        self::assertNotNull($reservationId);

        $this->client->loginUser($admin);

        $this->client->request(
            'POST',
            '/admin/reservations/'
            . $reservationId
            . '/status',
            [
                '_token' => 'invalid-token',
                'status' => 'accepted',
            ]
        );

        self::assertResponseStatusCodeSame(403);

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()
            ->get('doctrine')
            ->getManager();

        $em->clear();

        $updatedReservation = $em
            ->getRepository(Reservation::class)
            ->find($reservationId);

        self::assertNotNull($updatedReservation);

        self::assertSame(
            Reservation::STATUS_PENDING,
            $updatedReservation->getStatus()
        );
    }

    public function testInvalidCsrfBlocksBulkReservationAction(): void
    {
        $admin = $this->createUser(
            'admin-bulk@test.pl',
            ['ROLE_ADMIN']
        );

        $owner = $this->createUser(
            'admin-bulk-owner@test.pl',
            ['ROLE_USER']
        );

        $renter = $this->createUser(
            'admin-bulk-renter@test.pl',
            ['ROLE_USER']
        );

        $car = $this->createCar(
            $owner,
            'ADMIN-BULK-1'
        );

        $reservation = $this->createReservation(
            $car,
            $renter,
            Reservation::STATUS_PENDING
        );

        $reservationId = $reservation->getId();

        self::assertNotNull($reservationId);

        $this->client->loginUser($admin);

        $this->client->request(
            'POST',
            '/admin/reservations/bulk',
            [
                '_token' => 'invalid-token',
                'action' => 'accept',
                'ids' => [$reservationId],
            ]
        );

        self::assertResponseStatusCodeSame(403);

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()
            ->get('doctrine')
            ->getManager();

        $em->clear();

        $updatedReservation = $em
            ->getRepository(Reservation::class)
            ->find($reservationId);

        self::assertNotNull($updatedReservation);

        self::assertSame(
            Reservation::STATUS_PENDING,
            $updatedReservation->getStatus()
        );
    }

    public function testAdminCannotDeleteReservationWithoutSuperAdminRole(): void
    {
        $admin = $this->createUser(
            'admin-delete@test.pl',
            ['ROLE_ADMIN']
        );

        $owner = $this->createUser(
            'admin-delete-owner@test.pl',
            ['ROLE_USER']
        );

        $renter = $this->createUser(
            'admin-delete-renter@test.pl',
            ['ROLE_USER']
        );

        $car = $this->createCar(
            $owner,
            'ADMIN-DELETE-1'
        );

        $reservation = $this->createReservation(
            $car,
            $renter,
            Reservation::STATUS_PENDING
        );

        $reservationId = $reservation->getId();

        self::assertNotNull($reservationId);

        $this->client->loginUser($admin);

        $this->client->request(
            'POST',
            '/admin/reservation/delete/'
            . $reservationId
        );

        self::assertResponseStatusCodeSame(403);

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()
            ->get('doctrine')
            ->getManager();

        $em->clear();

        $existingReservation = $em
            ->getRepository(Reservation::class)
            ->find($reservationId);

        self::assertNotNull($existingReservation);
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

    private function createReservation(
        Car $car,
        User $renter,
        string $status
    ): Reservation {
        $reservation = new Reservation();

        $reservation->setCar($car);
        $reservation->setUser($renter);

        $reservation->setStartDate(
            new \DateTimeImmutable('+20 days')
        );

        $reservation->setEndDate(
            new \DateTimeImmutable('+25 days')
        );

        $reservation->setStatus($status);

        $reservation->setPricePerDay(
            $car->getPricePerDay()
        );

        $this->em->persist($reservation);
        $this->em->flush();

        return $reservation;
    }
}
