<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class ProfileController extends AbstractController
{
    #[Route('/profile', name: 'app_profile')]
    public function edit(Request $request, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(RegistrationFormType::class, $user, [
            'validation_groups' => ['Default'], // wyłączamy wymóg powtórnego hasła
        ]);
        $form->remove('plainPassword'); // edycja profilu bez zmiany hasła

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($user);
            $em->flush();

            $this->addFlash('success', 'Profil został zaktualizowany.');
            return $this->redirectToRoute('app_profile');
        }

        return $this->render('user/profile.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
