<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => 'Imię',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Podaj imię'],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Nazwisko',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Podaj nazwisko'],
            ])
            ->add('address', TextType::class, [
                'label' => 'Adres',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Podaj adres'],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Adres e-mail',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Podaj adres e-mail'],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'first_options'  => [
                    'label' => 'Hasło',
                    'attr' => ['class' => 'form-control', 'placeholder' => 'Podaj hasło'],
                ],
                'second_options' => [
                    'label' => 'Powtórz hasło',
                    'attr' => ['class' => 'form-control', 'placeholder' => 'Powtórz hasło'],
                ],
                'invalid_message' => 'Hasła muszą być identyczne.',
                'mapped' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
