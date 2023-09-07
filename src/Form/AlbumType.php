<?php

namespace App\Form;

use App\Entity\Album;
use App\Entity\Artist;
use App\Entity\Tracks;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;


class AlbumType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name',TextType::class)
            ->add("picture",FileType::class,[
                "required" => true,
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

            ->add("releaseDate", DateType::class,
            [
                "required" => true,
                "widget" => "single_text",
                'empty_data' => '0000-00-00'

            ])
            ->add('Artist', EntityType::class,
            [
                "class" => Artist::class,
                "choice_label" => "name"
            ])
            ->add("genre",ChoiceType::class ,
            [
                "choices"=>[
                    "Rap" => "Rap",
                    "Rock" => "Rock",
                    "Pop" => "Pop",
                    "Jazz" => "Jazz",
                    "Metal" => "Metal",
                    "Trap" => "Trap",
                    "Shoegaze" => "Shoegaze",
                ],
            ])

            //hidden fields that will have values passed in by js functions, fml
            ->add("numTracks", HiddenType::class,
                [
                    "mapped"=>false,
                    "data" => null
                ]

            )
            ->add("trackNames", HiddenType::class,
                [
                    "mapped"=>false,
                    "data" => null
                ]
            )
            ->add("trackLengths", HiddenType::class,
                [
                    "mapped"=>false,
                    "data" => null
                ]
            )
            ->add("lastFM_Auto_Complete",CheckboxType::class,
                [
                    "required" => false,
                    "mapped" => false,
                    "attr" => [
                        "class" => "form-check-input",

                        "onclick" => "lastFMSwitch(this)"

                    ],
                    "label_attr" => [
                    ]
                ]
            )
            ->add("submit",SubmitType::class,)

        ;

    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            "apporved" => false,
            'data_class' => Album::class,
        ]);
    }
}
