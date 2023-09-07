<?php

namespace App\Form;

use App\Entity\Review;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ReviewAPIType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('text', TextareaType::class,
                [
                    "attr" =>
                        [
                            "maxlength" => 255,
                            "rows" => 4,
                            "cols" => 50
                        ]
                ]
            )
            ->add('score', IntegerType::class,
                [
                    "attr" =>
                        [
                            "min" => 1,
                            "max" => 5
                        ],
                    "error_bubbling" => true
                ],)
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Review::class,
            "csrf_protection" => false,
            "allow_extra_fields"=> true
        ]);
    }
}
