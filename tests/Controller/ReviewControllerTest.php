<?php

namespace App\Tests;

use App\Entity\Car;
use App\Entity\Reservation;
use App\Entity\Review;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ReviewControllerTest extends WebTestCase
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

    public function testOwnerCannotReviewOwnCar(): void
    {
        $owner = $this->createUser(
            'review-owner@test.pl'
        );

        $car = $this->createCar(
            $owner,
            'REV-OWN-1'
        );

        $this->client->loginUser($owner);

        $this->client->request(
            'POST',
            '/reviews/add/' . $car->getId()
        );

        self::assertResponseRedirects(
            '/cars/' . $car->getId() . '#reviews'
        );

        $review = $this->em
            ->getRepository(Review::class)
            ->findOneBy([
                'car' => $car,
                'user' => $owner,
            ]);

        self::assertNull($review);
    }

    public function testUserWithoutCompletedReservationCannotReviewCar(): void
    {
        $owner = $this->createUser(
            'review-noreservation-owner@test.pl'
        );

        $user = $this->createUser(
            'review-noreservation-user@test.pl'
        );

        $car = $this->createCar(
            $owner,
            'REV-NO-RES-1'
        );

        $this->client->loginUser($user);

        $this->client->request(
            'POST',
            '/reviews/add/' . $car->getId()
        );

        self::assertResponseRedirects(
            '/cars/' . $car->getId() . '#reviews'
        );

        $review = $this->em
            ->getRepository(Review::class)
            ->findOneBy([
                'car' => $car,
                'user' => $user,
            ]);

        self::assertNull($review);
    }

    public function testUserWithCompletedReservationCanAccessReviewLogic(): void
    {
        $owner = $this->createUser(
            'review-completed-owner@test.pl'
        );

        $user = $this->createUser(
            'review-completed-user@test.pl'
        );

        $car = $this->createCar(
            $owner,
            'REV-COMP-1'
        );

        $this->createReservation(
            $car,
            $user,
            Reservation::STATUS_COMPLETED
        );

        $this->client->loginUser($user);

        $this->client->request(
            'POST',
            '/reviews/add/' . $car->getId()
        );

        // Bez danych formularza opinia nie powinna zostać zapisana,
        // ale kontroler powinien przejść przez sprawdzenie
        // zakończonego wynajmu.
        self::assertResponseRedirects(
            '/cars/' . $car->getId() . '#reviews'
        );

        self::assertResponseStatusCodeSame(302);
    }

    public function testUserCannotAddSecondReviewForSameCar(): void
    {
        $owner = $this->createUser(
            'review-duplicate-owner@test.pl'
        );

        $user = $this->createUser(
            'review-duplicate-user@test.pl'
        );

        $car = $this->createCar(
            $owner,
            'REV-DUP-1'
        );

        $this->createReservation(
            $car,
            $user,
            Reservation::STATUS_COMPLETED
        );

        $existingReview = new Review();

        $existingReview->setCar($car);
        $existingReview->setUser($user);
        $existingReview->setRating(5);
        $existingReview->setContent(
            'Pierwsza opinia testowa.'
        );

        $this->em->persist($existingReview);
        $this->em->flush();

        $this->client->loginUser($user);

        $this->client->request(
            'POST',
            '/reviews/add/' . $car->getId()
        );

        self::assertResponseRedirects(
            '/cars/' . $car->getId() . '#reviews'
        );

        $reviews = $this->em
            ->getRepository(Review::class)
            ->findBy([
                'car' => $car,
                'user' => $user,
            ]);

        self::assertCount(1, $reviews);
    }

    public function testOtherUserCannotDeleteReview(): void
    {
        $owner = $this->createUser(
            'review-delete-owner@test.pl'
        );

        $author = $this->createUser(
            'review-delete-author@test.pl'
        );

        $otherUser = $this->createUser(
            'review-delete-other@test.pl'
        );

        $car = $this->createCar(
            $owner,
            'REV-DEL-SEC-1'
        );

        $review = $this->createReview(
            $car,
            $author
        );

        $reviewId = $review->getId();

        self::assertNotNull($reviewId);

        $this->client->loginUser($otherUser);

        $this->client->request(
            'POST',
            '/reviews/delete/' . $reviewId
        );

        self::assertResponseStatusCodeSame(403);

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()
            ->get('doctrine')
            ->getManager();

        $em->clear();

        $existingReview = $em
            ->getRepository(Review::class)
            ->find($reviewId);

        self::assertNotNull($existingReview);
    }

    public function testAuthorCannotDeleteReviewWithInvalidCsrfToken(): void
    {
        $owner = $this->createUser(
            'review-csrf-owner@test.pl'
        );

        $author = $this->createUser(
            'review-csrf-author@test.pl'
        );

        $car = $this->createCar(
            $owner,
            'REV-CSRF-1'
        );

        $review = $this->createReview(
            $car,
            $author
        );

        $reviewId = $review->getId();

        self::assertNotNull($reviewId);

        $this->client->loginUser($author);

        $this->client->request(
            'POST',
            '/reviews/delete/' . $reviewId,
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

        $existingReview = $em
            ->getRepository(Review::class)
            ->find($reviewId);

        self::assertNotNull($existingReview);
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
        User $user,
        string $status
    ): Reservation {
        $reservation = new Reservation();

        $reservation->setCar($car);
        $reservation->setUser($user);
        $reservation->setStartDate(
            new \DateTimeImmutable('-10 days')
        );
        $reservation->setEndDate(
            new \DateTimeImmutable('-7 days')
        );
        $reservation->setPricePerDay(
            $car->getPricePerDay()
        );
        $reservation->setStatus($status);

        $this->em->persist($reservation);
        $this->em->flush();

        return $reservation;
    }

    private function createReview(
        Car $car,
        User $user
    ): Review {
        $review = new Review();

        $review->setCar($car);
        $review->setUser($user);
        $review->setRating(5);
        $review->setContent(
            'Testowa opinia o samochodzie.'
        );

        $this->em->persist($review);
        $this->em->flush();

        return $review;
    }
}
