<?php

namespace App\Form;

use App\Entity\Reservation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class ReservationConfirmType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('phoneNumber', TelType::class, [
                'label' => 'Telefon',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Np. 600700800',
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'Podaj numer telefonu.'),
                    new Assert\Length(min: 7, max: 30),
                ],
            ])
            ->add('rentalLocation', TextType::class, [
                'label' => 'Miejsce odbioru',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Np. Warszawa, lotnisko Chopina',
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'Podaj miejsce odbioru.'),
                    new Assert\Length(min: 2, max: 255),
                ],
            ])
            ->add('comment', TextareaType::class, [
                'label' => 'Uwagi dla właściciela',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Dodatkowe informacje dla właściciela (opcjonalnie)',
                ],
            ])
            ->add('wantsInvoice', ChoiceType::class, [
                'label' => 'Faktura',
                'expanded' => true,
                'multiple' => false,
                'choices' => [
                    'Bez faktury' => false,
                    'Z fakturą' => true,
                ],
            ])
            ->add('invoiceName', TextType::class, [
                'label' => 'Nabywca / nazwa firmy',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Np. Jan Kowalski albo Firma XYZ Sp. z o.o.',
                ],
                'constraints' => [
                    new Assert\Length(max: 255),
                ],
            ])
            ->add('invoiceStreet', TextType::class, [
                'label' => 'Ulica i numer',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Np. Kwiatowa 12/4',
                ],
                'constraints' => [
                    new Assert\Length(max: 255),
                ],
            ])
            ->add('invoicePostalCode', TextType::class, [
                'label' => 'Kod pocztowy',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '00-000',
                ],
                'constraints' => [
                    new Assert\Length(max: 20),
                ],
            ])
            ->add('invoiceCity', TextType::class, [
                'label' => 'Miasto',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Np. Warszawa',
                ],
                'constraints' => [
                    new Assert\Length(max: 120),
                ],
            ])
            ->add('invoiceNip', TextType::class, [
                'label' => 'NIP (opcjonalnie)',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Np. 1234567890',
                ],
                'constraints' => [
                    new Assert\Length(max: 30),
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