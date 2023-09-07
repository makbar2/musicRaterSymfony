<?php

namespace App\Form;

use App\Entity\Artist;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class ArtistType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name',TextType::class)
            ->add("picture",FileType::class,[
                "required" => false,
                "constraints" => [
                    new File([
                        "maxSize" => "8M",
                        "mimeTypes" => [
                            "image/png",
                            "image/jpg",
                            "image/jpeg"
                        ],
                        "mimeTypesMessage"=> "Please upload an image that is less than 8mb in size and is a jpg or png.",
                    ])
                ]
            ])
            
            ->add("submit",SubmitType::class)
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Artist::class,
        ]);
    }
}
