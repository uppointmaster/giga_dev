<?php

namespace Customize\Form\Type\Admin;

use Customize\Entity\School;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Eccube\Form\Type\AddressType;
use Eccube\Form\Type\PostalType;

class SchoolType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('name', TextType::class, [
            'constraints' => [
                new NotBlank(),
                new Length(['max' => 255]),
            ],
        ])
        ->add('filename', HiddenType::class, [
          'required' => false,
          'mapped' => true,
        ])
        ->add('image', FileType::class, [
          'multiple' => false,
          'required' => false,
          'mapped' => false,
        ])
        ->add('postal_code', PostalType::class, [
            'required' => true,
        ])
        ->add('address', AddressType::class, [
            'required' => true,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => School::class,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'admin_school';
    }
}
