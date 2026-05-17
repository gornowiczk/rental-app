<?php

namespace App\Form;

use App\Entity\ReservationChangeRequest;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class ReservationChangeRequestType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('newStartDate', DateType::class, [
                'label' => 'Nowa data startu',
                'widget' => 'single_text',
                'constraints' => [new Assert\NotBlank()],
            ])
            ->add('newEndDate', DateType::class, [
                'label' => 'Nowa data końca',
                'widget' => 'single_text',
                'constraints' => [new Assert\NotBlank()],
            ])
            ->add('newPickupLocation', TextType::class, [
                'label' => 'Nowe miejsce odbioru (opcjonalnie)',
                'required' => false,
            ])
            ->add('message', TextareaType::class, [
                'label' => 'Wiadomość do właściciela (opcjonalnie)',
                'required' => false,
                'attr' => ['rows' => 4],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ReservationChangeRequest::class,
        ]);
    }
}