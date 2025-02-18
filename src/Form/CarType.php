<?php

namespace App\Form;

use App\Entity\Car;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class CarType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('brand')
            ->add('model')
            ->add('year')
            ->add('registrationNumber')
            ->add('pricePerDay', TextType::class, [
                'label' => 'Cena za dzień',
                'required' => true,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('location')
            ->add('isAvailable')
            ->add('mainImage', FileType::class, [
                'label' => 'Zdjęcie główne samochodu (opcjonalne)',
                'mapped' => false,
                'required' => false,
            ])
            ->add('gallery', FileType::class, [
                'label' => 'Galeria zdjęć (opcjonalna, można wybrać wiele plików)',
                'mapped' => false,
                'required' => false,
                'multiple' => true,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Car::class,
        ]);
    }
}
