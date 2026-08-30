<?php

namespace App\Tests;

use App\Entity\Car;
use App\Entity\Reservation;
use App\Entity\ReservationChangeRequest;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ReservationChangeRequestControllerTest extends WebTestCase
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

    public function testOtherUserCannotRequestDateChange(): void
    {
        $owner = $this->createUser(
            'change-owner-security@test.pl'
        );

        $renter = $this->createUser(
            'change-renter-security@test.pl'
        );

        $otherUser = $this->createUser(
            'change-other-security@test.pl'
        );

        $car = $this->createCar(
            $owner,
            'CHANGE-SEC-1'
        );

        $reservation = $this->createReservation(
            $car,
            $renter,
            new \DateTimeImmutable('+20 days'),
            new \DateTimeImmutable('+25 days')
        );

        $this->client->loginUser($otherUser);

        $this->client->request(
            'POST',
            '/reservations/'
            . $reservation->getId()
            . '/request-change-dates'
        );

        self::assertResponseStatusCodeSame(403);
    }

    public function testInvalidCsrfDoesNotCreateChangeRequest(): void
    {
        $owner = $this->createUser(
            'change-owner-csrf@test.pl'
        );

        $renter = $this->createUser(
            'change-renter-csrf@test.pl'
        );

        $car = $this->createCar(
            $owner,
            'CHANGE-CSRF-1'
        );

        $reservation = $this->createReservation(
            $car,
            $renter,
            new \DateTimeImmutable('+20 days'),
            new \DateTimeImmutable('+25 days')
        );

        $this->client->loginUser($renter);

        $this->client->request(
            'POST',
            '/reservations/'
            . $reservation->getId()
            . '/request-change-dates',
            [
                '_token' => 'invalid-token',
                'new_start' => (
                    new \DateTimeImmutable('+22 days')
                )->format('Y-m-d'),
                'new_end' => (
                    new \DateTimeImmutable('+27 days')
                )->format('Y-m-d'),
                'reason' => 'Test zmiany terminu',
            ]
        );

        self::assertResponseStatusCodeSame(302);

        $changeRequest = $this->em
            ->getRepository(
                ReservationChangeRequest::class
            )
            ->findOneBy([
                'reservation' => $reservation,
            ]);

        self::assertNull($changeRequest);
    }

    public function testOwnerCannotAcceptForeignChangeRequest(): void
    {
        $owner = $this->createUser(
            'change-real-owner@test.pl'
        );

        $foreignOwner = $this->createUser(
            'change-foreign-owner@test.pl'
        );

        $renter = $this->createUser(
            'change-renter-foreign@test.pl'
        );

        $car = $this->createCar(
            $owner,
            'CHANGE-FOREIGN-1'
        );

        $reservation = $this->createReservation(
            $car,
            $renter,
            new \DateTimeImmutable('+30 days'),
            new \DateTimeImmutable('+35 days')
        );

        $changeRequest = $this->createChangeRequest(
            $reservation,
            $renter,
            new \DateTimeImmutable('+32 days'),
            new \DateTimeImmutable('+37 days')
        );

        $this->client->loginUser($foreignOwner);

        $this->client->request(
            'POST',
            '/reservations/change-request/'
            . $changeRequest->getId()
            . '/accept'
        );

        self::assertResponseStatusCodeSame(403);
    }

    public function testOwnerCannotRejectForeignChangeRequest(): void
    {
        $owner = $this->createUser(
            'change-reject-owner@test.pl'
        );

        $foreignOwner = $this->createUser(
            'change-reject-foreign@test.pl'
        );

        $renter = $this->createUser(
            'change-reject-renter@test.pl'
        );

        $car = $this->createCar(
            $owner,
            'CHANGE-REJECT-SEC-1'
        );

        $reservation = $this->createReservation(
            $car,
            $renter,
            new \DateTimeImmutable('+40 days'),
            new \DateTimeImmutable('+45 days')
        );

        $changeRequest = $this->createChangeRequest(
            $reservation,
            $renter,
            new \DateTimeImmutable('+42 days'),
            new \DateTimeImmutable('+47 days')
        );

        $this->client->loginUser($foreignOwner);

        $this->client->request(
            'POST',
            '/reservations/change-request/'
            . $changeRequest->getId()
            . '/reject'
        );

        self::assertResponseStatusCodeSame(403);
    }

    public function testInvalidCsrfDoesNotAcceptChangeRequest(): void
    {
        $owner = $this->createUser(
            'change-accept-csrf-owner@test.pl'
        );

        $renter = $this->createUser(
            'change-accept-csrf-renter@test.pl'
        );

        $car = $this->createCar(
            $owner,
            'CHANGE-ACC-CSRF-1'
        );

        $reservation = $this->createReservation(
            $car,
            $renter,
            new \DateTimeImmutable('+50 days'),
            new \DateTimeImmutable('+55 days')
        );

        $changeRequest = $this->createChangeRequest(
            $reservation,
            $renter,
            new \DateTimeImmutable('+52 days'),
            new \DateTimeImmutable('+57 days')
        );

        $changeRequestId = $changeRequest->getId();

        self::assertNotNull($changeRequestId);

        $this->client->loginUser($owner);

        $this->client->request(
            'POST',
            '/reservations/change-request/'
            . $changeRequestId
            . '/accept',
            [
                '_token' => 'invalid-token',
            ]
        );

        self::assertResponseStatusCodeSame(302);

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()
            ->get('doctrine')
            ->getManager();

        $em->clear();

        $updatedRequest = $em
            ->getRepository(
                ReservationChangeRequest::class
            )
            ->find($changeRequestId);

        self::assertNotNull($updatedRequest);

        self::assertSame(
            ReservationChangeRequest::STATUS_PENDING,
            $updatedRequest->getStatus()
        );
    }

    public function testInvalidCsrfDoesNotRejectChangeRequest(): void
    {
        $owner = $this->createUser(
            'change-reject-csrf-owner@test.pl'
        );

        $renter = $this->createUser(
            'change-reject-csrf-renter@test.pl'
        );

        $car = $this->createCar(
            $owner,
            'CHANGE-REJ-CSRF-1'
        );

        $reservation = $this->createReservation(
            $car,
            $renter,
            new \DateTimeImmutable('+60 days'),
            new \DateTimeImmutable('+65 days')
        );

        $changeRequest = $this->createChangeRequest(
            $reservation,
            $renter,
            new \DateTimeImmutable('+62 days'),
            new \DateTimeImmutable('+67 days')
        );

        $changeRequestId = $changeRequest->getId();

        self::assertNotNull($changeRequestId);

        $this->client->loginUser($owner);

        $this->client->request(
            'POST',
            '/reservations/change-request/'
            . $changeRequestId
            . '/reject',
            [
                '_token' => 'invalid-token',
            ]
        );

        self::assertResponseStatusCodeSame(302);

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()
            ->get('doctrine')
            ->getManager();

        $em->clear();

        $updatedRequest = $em
            ->getRepository(
                ReservationChangeRequest::class
            )
            ->find($changeRequestId);

        self::assertNotNull($updatedRequest);

        self::assertSame(
            ReservationChangeRequest::STATUS_PENDING,
            $updatedRequest->getStatus()
        );
    }

    public function testAcceptedEntityChangesStatusAndDecisionData(): void
    {
        $owner = $this->createUser(
            'change-entity-accept-owner@test.pl'
        );

        $renter = $this->createUser(
            'change-entity-accept-renter@test.pl'
        );

        $car = $this->createCar(
            $owner,
            'CHANGE-ENTITY-ACC-1'
        );

        $reservation = $this->createReservation(
            $car,
            $renter,
            new \DateTimeImmutable('+70 days'),
            new \DateTimeImmutable('+75 days')
        );

        $changeRequest = $this->createChangeRequest(
            $reservation,
            $renter,
            new \DateTimeImmutable('+72 days'),
            new \DateTimeImmutable('+77 days')
        );

        self::assertTrue(
            $changeRequest->isPending()
        );

        $changeRequest->accept($owner);

        self::assertSame(
            ReservationChangeRequest::STATUS_ACCEPTED,
            $changeRequest->getStatus()
        );

        self::assertSame(
            $owner,
            $changeRequest->getDecidedBy()
        );

        self::assertNotNull(
            $changeRequest->getDecidedAt()
        );

        self::assertFalse(
            $changeRequest->isPending()
        );
    }

    public function testRejectedEntityChangesStatusAndDecisionData(): void
    {
        $owner = $this->createUser(
            'change-entity-reject-owner@test.pl'
        );

        $renter = $this->createUser(
            'change-entity-reject-renter@test.pl'
        );

        $car = $this->createCar(
            $owner,
            'CHANGE-ENTITY-REJ-1'
        );

        $reservation = $this->createReservation(
            $car,
            $renter,
            new \DateTimeImmutable('+80 days'),
            new \DateTimeImmutable('+85 days')
        );

        $changeRequest = $this->createChangeRequest(
            $reservation,
            $renter,
            new \DateTimeImmutable('+82 days'),
            new \DateTimeImmutable('+87 days')
        );

        self::assertTrue(
            $changeRequest->isPending()
        );

        $changeRequest->reject($owner);

        self::assertSame(
            ReservationChangeRequest::STATUS_REJECTED,
            $changeRequest->getStatus()
        );

        self::assertSame(
            $owner,
            $changeRequest->getDecidedBy()
        );

        self::assertNotNull(
            $changeRequest->getDecidedAt()
        );

        self::assertFalse(
            $changeRequest->isPending()
        );
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

    private function createReservation(
        Car $car,
        User $renter,
        \DateTimeInterface $start,
        \DateTimeInterface $end
    ): Reservation {
        $reservation = new Reservation();

        $reservation->setCar($car);
        $reservation->setUser($renter);
        $reservation->setStartDate($start);
        $reservation->setEndDate($end);
        $reservation->setStatus(
            Reservation::STATUS_ACCEPTED
        );
        $reservation->setPricePerDay(
            $car->getPricePerDay()
        );

        $this->em->persist($reservation);
        $this->em->flush();

        return $reservation;
    }

    private function createChangeRequest(
        Reservation $reservation,
        User $requester,
        \DateTimeImmutable $newStart,
        \DateTimeImmutable $newEnd
    ): ReservationChangeRequest {
        $changeRequest = new ReservationChangeRequest();

        $changeRequest->setReservation($reservation);
        $changeRequest->setRequester($requester);
        $changeRequest->setNewStartDate($newStart);
        $changeRequest->setNewEndDate($newEnd);
        $changeRequest->setMessage(
            'Testowa prośba o zmianę terminu.'
        );
        $changeRequest->setStatus(
            ReservationChangeRequest::STATUS_PENDING
        );

        $this->em->persist($changeRequest);
        $this->em->flush();

        return $changeRequest;
    }
}
