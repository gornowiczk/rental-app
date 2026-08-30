<?php

namespace App\Tests;

use App\Entity\Car;
use App\Entity\Reservation;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ReservationControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        // Nie restartujemy kernela pomiędzy requestami
        // wykonywanymi w ramach jednego testu.
        $this->client->disableReboot();

        $this->em = static::getContainer()
            ->get('doctrine')
            ->getManager();

        // Każdy test działa we własnej transakcji.
        // Po zakończeniu testu wszystkie zmiany są cofane.
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

    public function testUserCannotReserveOwnCar(): void
    {
        $owner = $this->createUser('owner-own@test.pl');
        $car = $this->createCar($owner, 'TEST-OWN-1');

        $this->client->loginUser($owner);

        $start = new \DateTimeImmutable('+5 days');
        $end = new \DateTimeImmutable('+7 days');

        $this->client->request(
            'GET',
            '/reservations/cars/' . $car->getId() . '/confirm',
            [
                'start' => $start->format('Y-m-d'),
                'end' => $end->format('Y-m-d'),
            ]
        );

        self::assertResponseRedirects(
            '/cars/' . $car->getId()
        );
    }

    public function testReservationWithPastDateIsRejected(): void
    {
        $owner = $this->createUser('owner-past@test.pl');
        $renter = $this->createUser('renter-past@test.pl');

        $car = $this->createCar(
            $owner,
            'TEST-PAST-1'
        );

        $this->client->loginUser($renter);

        $start = new \DateTimeImmutable('-2 days');
        $end = new \DateTimeImmutable('+2 days');

        $this->client->request(
            'GET',
            '/reservations/cars/' . $car->getId() . '/confirm',
            [
                'start' => $start->format('Y-m-d'),
                'end' => $end->format('Y-m-d'),
            ]
        );

        self::assertResponseRedirects(
            '/cars/' . $car->getId()
        );
    }

    public function testEndDateMustBeAfterStartDate(): void
    {
        $owner = $this->createUser('owner-range@test.pl');
        $renter = $this->createUser('renter-range@test.pl');

        $car = $this->createCar(
            $owner,
            'TEST-RANGE-1'
        );

        $this->client->loginUser($renter);

        $start = new \DateTimeImmutable('+10 days');
        $end = new \DateTimeImmutable('+9 days');

        $this->client->request(
            'GET',
            '/reservations/cars/' . $car->getId() . '/confirm',
            [
                'start' => $start->format('Y-m-d'),
                'end' => $end->format('Y-m-d'),
            ]
        );

        self::assertResponseRedirects(
            '/cars/' . $car->getId()
        );
    }

    public function testUnavailableCarCannotBeReserved(): void
    {
        $owner = $this->createUser(
            'owner-unavailable@test.pl'
        );

        $renter = $this->createUser(
            'renter-unavailable@test.pl'
        );

        $car = $this->createCar(
            $owner,
            'TEST-OFF-1'
        );

        $car->setIsAvailable(false);

        $this->em->flush();

        $this->client->loginUser($renter);

        $start = new \DateTimeImmutable('+5 days');
        $end = new \DateTimeImmutable('+7 days');

        $this->client->request(
            'GET',
            '/reservations/cars/' . $car->getId() . '/confirm',
            [
                'start' => $start->format('Y-m-d'),
                'end' => $end->format('Y-m-d'),
            ]
        );

        self::assertResponseRedirects(
            '/cars/' . $car->getId()
        );
    }

    public function testOverlappingReservationIsRejected(): void
    {
        $owner = $this->createUser(
            'owner-overlap@test.pl'
        );

        $firstRenter = $this->createUser(
            'renter-one@test.pl'
        );

        $secondRenter = $this->createUser(
            'renter-two@test.pl'
        );

        $car = $this->createCar(
            $owner,
            'TEST-OVERLAP-1'
        );

        $existingReservation = new Reservation();

        $existingReservation->setCar($car);
        $existingReservation->setUser($firstRenter);

        $existingReservation->setStartDate(
            new \DateTimeImmutable('+10 days')
        );

        $existingReservation->setEndDate(
            new \DateTimeImmutable('+15 days')
        );

        $existingReservation->setStatus(
            Reservation::STATUS_ACCEPTED
        );

        $existingReservation->setPricePerDay(
            $car->getPricePerDay()
        );

        $this->em->persist($existingReservation);
        $this->em->flush();

        $this->client->loginUser($secondRenter);

        $this->client->request(
            'GET',
            '/reservations/cars/' . $car->getId() . '/confirm',
            [
                'start' => (
                    new \DateTimeImmutable('+12 days')
                )->format('Y-m-d'),

                'end' => (
                    new \DateTimeImmutable('+14 days')
                )->format('Y-m-d'),
            ]
        );

        self::assertResponseRedirects(
            '/cars/' . $car->getId()
        );
    }

    public function testOwnerCanAcceptPendingReservation(): void
    {
        $owner = $this->createUser(
            'owner-accept@test.pl'
        );

        $renter = $this->createUser(
            'renter-accept@test.pl'
        );

        $car = $this->createCar(
            $owner,
            'TEST-ACCEPT-1'
        );

        $reservation = $this->createReservation(
            $car,
            $renter,
            new \DateTimeImmutable('+20 days'),
            new \DateTimeImmutable('+23 days')
        );

        // Zapamiętujemy ID przed wykonaniem requestów.
        $reservationId = $reservation->getId();

        self::assertNotNull($reservationId);

        $this->client->loginUser($owner);

        // Wchodzimy na stronę rezerwacji i pobieramy
        // prawdziwy formularz wraz z tokenem CSRF.
        $crawler = $this->client->request(
            'GET',
            '/reservations'
        );

        self::assertResponseIsSuccessful();

        $formAction =
            '/reservations/'
            . $reservationId
            . '/accept';

        $form = $crawler->filter(
            'form[action="' . $formAction . '"]'
        );

        self::assertCount(1, $form);

        $tokenField = $form->filter(
            'input[name="_token"]'
        );

        self::assertCount(1, $tokenField);

        $token = $tokenField->attr('value');

        self::assertNotEmpty($token);

        // Wysyłamy formularz akceptacji.
        $this->client->request(
            'POST',
            $formAction,
            [
                '_token' => $token,
            ]
        );

        self::assertResponseRedirects(
            '/reservations'
        );

        // Pobieramy świeżą instancję EntityManagera,
        // ponieważ request mógł zmienić stan Doctrine.
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
            Reservation::STATUS_ACCEPTED,
            $updatedReservation->getStatus()
        );
    }

    public function testOwnerCanRejectPendingReservation(): void
    {
        $owner = $this->createUser(
            'owner-reject@test.pl'
        );

        $renter = $this->createUser(
            'renter-reject@test.pl'
        );

        $car = $this->createCar(
            $owner,
            'TEST-REJECT-1'
        );

        $reservation = $this->createReservation(
            $car,
            $renter,
            new \DateTimeImmutable('+30 days'),
            new \DateTimeImmutable('+33 days')
        );

        // Zapamiętujemy ID.
        $reservationId = $reservation->getId();

        self::assertNotNull($reservationId);

        $this->client->loginUser($owner);

        // Pobieramy stronę z formularzem odrzucenia.
        $crawler = $this->client->request(
            'GET',
            '/reservations'
        );

        self::assertResponseIsSuccessful();

        $formAction =
            '/reservations/'
            . $reservationId
            . '/reject';

        $form = $crawler->filter(
            'form[action="' . $formAction . '"]'
        );

        self::assertCount(1, $form);

        $tokenField = $form->filter(
            'input[name="_token"]'
        );

        self::assertCount(1, $tokenField);

        $token = $tokenField->attr('value');

        self::assertNotEmpty($token);

        // Wysyłamy formularz odrzucenia.
        $this->client->request(
            'POST',
            $formAction,
            [
                '_token' => $token,
            ]
        );

        self::assertResponseRedirects(
            '/reservations'
        );

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
            Reservation::STATUS_REJECTED,
            $updatedReservation->getStatus()
        );
    }

    public function testOtherUserCannotAcceptReservation(): void
    {
        $owner = $this->createUser(
            'owner-security@test.pl'
        );

        $renter = $this->createUser(
            'renter-security@test.pl'
        );

        $otherUser = $this->createUser(
            'other-security@test.pl'
        );

        $car = $this->createCar(
            $owner,
            'TEST-SECURITY-1'
        );

        $reservation = $this->createReservation(
            $car,
            $renter,
            new \DateTimeImmutable('+40 days'),
            new \DateTimeImmutable('+43 days')
        );

        $reservationId = $reservation->getId();

        self::assertNotNull($reservationId);

        $this->client->loginUser($otherUser);

        // Obcy użytkownik próbuje zaakceptować
        // cudzą rezerwację.
        $this->client->request(
            'POST',
            '/reservations/'
            . $reservationId
            . '/accept'
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

        // Status nie może zostać zmieniony.
        self::assertSame(
            Reservation::STATUS_PENDING,
            $updatedReservation->getStatus()
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
            Reservation::STATUS_PENDING
        );

        $reservation->setPricePerDay(
            $car->getPricePerDay()
        );

        $this->em->persist($reservation);
        $this->em->flush();

        return $reservation;
    }
}
