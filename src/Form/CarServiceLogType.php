<?php

namespace App\Form;

use App\Entity\CarServiceLog;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class CarServiceLogType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('type', ChoiceType::class, [
                'label' => 'Typ wpisu',
                'choices' => [
                    'Serwis' => 'service',
                    'Naprawa' => 'repair',
                    'Przegląd' => 'inspection',
                    'Ubezpieczenie' => 'insurance',
                    'Opony' => 'tires',
                    'Inne' => 'other',
                ],
            ])
            ->add('serviceDate', DateType::class, [
                'label' => 'Data',
                'widget' => 'single_text',
                'html5' => true,
                'constraints' => [
                    new Assert\NotBlank(),
                ],
            ])
            ->add('title', TextType::class, [
                'label' => 'Tytuł',
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(max: 255),
                ],
            ])
            ->add('mileage', IntegerType::class, [
                'label' => 'Przebieg (km)',
                'required' => false,
                'constraints' => [
                    new Assert\PositiveOrZero(),
                ],
            ])
            ->add('cost', MoneyType::class, [
                'label' => 'Koszt',
                'required' => false,
                'currency' => 'PLN',
                'divisor' => 1,
                'scale' => 2,
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Opis',
                'required' => false,
                'attr' => [
                    'rows' => 4,
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CarServiceLog::class,
        ]);
    }
}