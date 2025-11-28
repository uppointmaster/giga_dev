<?php

/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) EC-CUBE CO.,LTD. All Rights Reserved.
 *
 * http://www.ec-cube.co.jp/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Customize\Form\Type\Admin;

use Eccube\Common\EccubeConfig;
use Eccube\Form\Type\KanaType;
use Eccube\Form\Type\NameType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Customize\Repository\SchoolRepository;
use Customize\Repository\SchoolAuthRepository;
use Eccube\Session\Session;

class ChildType extends AbstractType
{
    /**
     * @var EccubeConfig
     */
    protected $eccubeConfig;

    protected $session;

    /**
     * @var SchoolRepository
     */
    protected $schoolRepository;

    /**
     * @var SchoolAuthRepository
     */
    protected $schoolAuthRepository;

    /**
     * @param EccubeConfig $eccubeConfig
     * @param SchoolRepository $schoolRepository
     * @param SchoolAuthRepository $schoolAuthRepository
     * @param Session $session
     */
    public function __construct(
        EccubeConfig $eccubeConfig,
        SchoolRepository $schoolRepository,
        SchoolAuthRepository $schoolAuthRepository,
        Session $session,
    )
    {
        $this->eccubeConfig = $eccubeConfig;
        $this->schoolRepository = $schoolRepository;
        $this->schoolAuthRepository = $schoolAuthRepository;
        $this->session = $session;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', NameType::class, [
                'required' => true,
            ])
            ->add('kana', KanaType::class, [
                'required' => true,
            ])
            ->add('examinee_number', TextType::class, [
                'required' => true,
                'attr' => [
                    'placeholder' => '123456',
                ],
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length([
                        'max' => $this->eccubeConfig['eccube_stext_len'],
                    ]),
                ],
            ])
            ->add('enrollment_year', ChoiceType::class, [
                'required' => true,
                'choices' => array_combine(
                    range(2025, 2099), // キーと値を一致させる
                    range(2025, 2099)
                ),
                'data' => (int)date('Y'), // 現在の西暦を初期選択として設定
                'placeholder' => '選択してください',
                'constraints' => [
                    new Assert\NotBlank(),
                ],
                'attr' => [
                    'style' => 'max-width: fit-content',
                ],
            ])
            ->add('school', ChoiceType::class, [
                'required' => true,
                'expanded' => false,
                'choices' => $this->schoolRepository->findAll(),
                'choice_label' => 'name',
                'placeholder' => '未選択',
                'attr' => [
                    'class' => 'col-md-2',
                ],
            ]);
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => 'Customize\Entity\Child',
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'child';
    }
}
