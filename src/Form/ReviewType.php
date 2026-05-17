<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class ReviewType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('rating', ChoiceType::class, [
                'label' => 'Ocena',
                'choices' => [
                    '★★★★★ (5)' => 5,
                    '★★★★☆ (4)' => 4,
                    '★★★☆☆ (3)' => 3,
                    '★★☆☆☆ (2)' => 2,
                    '★☆☆☆☆ (1)' => 1,
                ],
                'expanded' => false,
                'multiple' => false,
                'constraints' => [new Assert\NotBlank(), new Assert\Range(min:1, max:5)],
            ])
            ->add('content', TextareaType::class, [
                'label' => 'Treść opinii',
                'attr' => ['rows' => 4, 'placeholder' => 'Podziel się wrażeniami z wynajmu…'],
                'constraints' => [new Assert\NotBlank(), new Assert\Length(min: 5)],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        // formularz niemapowany bezpośrednio do encji – dane zbierzemy ręcznie
        $resolver->setDefaults([]);
    }
}
