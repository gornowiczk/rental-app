<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use App\Entity\User;

class UserEditType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => 'Imię',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Podaj imię']
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Nazwisko',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Podaj nazwisko']
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'attr' => ['class' => 'form-control'],
                'disabled' => true, // e-mail niezmienny
            ])
            ->add('address', TextType::class, [
                'label' => 'Adres zamieszkania',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Podaj adres']
            ])
            ->add('phone', TelType::class, [
                'label' => 'Numer telefonu',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Podaj numer telefonu']
            ])
            ->add('peselOrNip', TextType::class, [
                'label' => 'PESEL / NIP',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Podaj PESEL lub NIP']
            ])
            ->add('plainPassword', PasswordType::class, [
                'label' => 'Nowe hasło (opcjonalnie)',
                'required' => false,
                'mapped' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Wpisz nowe hasło jeśli chcesz zmienić']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
