<?php

namespace App\Form;

use App\Entity\Reservation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class ReservationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('startDate', DateType::class, [
                'label'  => 'Data rozpoczęcia',
                'widget' => 'single_text',              // <input type="date">
                'html5'  => true,
                'input'  => 'datetime_immutable',       // KLUCZOWE: dopasowanie do encji
                'constraints' => [
                    new Assert\NotBlank(message: 'Podaj datę rozpoczęcia.'),
                ],
            ])
            ->add('endDate', DateType::class, [
                'label'  => 'Data zakończenia',
                'widget' => 'single_text',
                'html5'  => true,
                'input'  => 'datetime_immutable',
                'constraints' => [
                    new Assert\NotBlank(message: 'Podaj datę zakończenia.'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Reservation::class,
        ]);
    }
}
