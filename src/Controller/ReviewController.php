<?php

namespace App\Controller;

use App\Entity\Car;
use App\Entity\Reservation;
use App\Entity\Review;
use App\Form\ReviewType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/reviews')]
final class ReviewController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/add/{id}', name: 'app_review_add', methods: ['POST'])]
    public function add(Request $request, Car $car): Response
    {
        $user = $this->getUser();

        if ($car->getOwner() && $car->getOwner()->getId() === $user->getId()) {
            $this->addFlash('danger', 'Nie możesz ocenić własnego auta.');
            return $this->redirectToRoute('app_car_details', ['id' => $car->getId(), '_fragment' => 'reviews']);
        }

        $completedReservation = $this->em->getRepository(Reservation::class)
            ->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.car = :car')
            ->andWhere('r.user = :user')
            ->andWhere('r.status = :status')
            ->setParameter('car', $car)
            ->setParameter('user', $user)
            ->setParameter('status', 'completed')
            ->getQuery()
            ->getSingleScalarResult();

        if ((int) $completedReservation === 0) {
            $this->addFlash('danger', 'Opinię możesz dodać dopiero po zakończonym wynajmie tego auta.');
            return $this->redirectToRoute('app_car_details', ['id' => $car->getId(), '_fragment' => 'reviews']);
        }

        $existing = $this->em->getRepository(Review::class)->findOneBy([
            'car' => $car,
            'user' => $user,
        ]);

        if ($existing) {
            $this->addFlash('warning', 'Dodałeś już opinię dla tego auta.');
            return $this->redirectToRoute('app_car_details', ['id' => $car->getId(), '_fragment' => 'reviews']);
        }

        $form = $this->createForm(ReviewType::class);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('danger', 'Nie udało się dodać opinii. Sprawdź formularz.');
            return $this->redirectToRoute('app_car_details', ['id' => $car->getId(), '_fragment' => 'reviews']);
        }

        $data = $form->getData();

        $review = (new Review())
            ->setCar($car)
            ->setUser($user)
            ->setRating((int) $data['rating'])
            ->setContent(trim((string) $data['content']));

        $this->em->persist($review);
        $this->em->flush();

        $this->addFlash('success', 'Dziękujemy za opinię!');
        return $this->redirectToRoute('app_car_details', ['id' => $car->getId(), '_fragment' => 'reviews']);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/delete/{id}', name: 'app_review_delete', methods: ['POST'])]
    public function delete(Request $request, Review $review): Response
    {
        if ($review->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('delete_review_' . $review->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Błędny token.');
        }

        $carId = $review->getCar()->getId();

        $this->em->remove($review);
        $this->em->flush();

        $this->addFlash('success', 'Opinia została usunięta.');
        return $this->redirectToRoute('app_car_details', ['id' => $carId, '_fragment' => 'reviews']);
    }
}