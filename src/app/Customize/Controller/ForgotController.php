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

namespace Customize\Controller;

use Eccube\Controller\AbstractController;
use Eccube\Entity\Master\CustomerStatus;
use Customize\Event\EccubeEvents;
use Eccube\Event\EventArgs;
use Eccube\Form\Type\Front\CustomerLoginType;
use Customize\Form\Type\Front\ForgotType;
use Customize\Form\Type\Front\ForgotAuthType;
use Customize\Form\Type\Front\ForgotEntryType;
use Customize\Form\Type\Front\ForgotLoginType;
use Customize\Form\Type\Front\PasswordResetType;
use Customize\Repository\CustomerRepository;
use Eccube\Repository\Master\CustomerStatusRepository;
use Customize\Service\MailService;
use Detection\MobileDetect;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception as HttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ForgotController extends AbstractController
{
    /**
     * @var CustomerStatusRepository
     */
    protected $customerStatusRepository;

    /**
     * @var ValidatorInterface
     */
    protected $validator;

    /**
     * @var MailService
     */
    protected $mailService;

    /**
     * @var CustomerRepository
     */
    protected $customerRepository;

    /**
     * @var UserPasswordHasherInterface
     */
    protected $passwordHasher;

    /**
     * ForgotController constructor.
     *
     * @param ValidatorInterface $validator
     * @param MailService $mailService
     * @param CustomerRepository $customerRepository
     * @param CustomerStatusRepository $customerStatusRepository
     * @param UserPasswordHasherInterface $encoderFactory
     */
    public function __construct(
        ValidatorInterface $validator,
        MailService $mailService,
        CustomerRepository $customerRepository,
        CustomerStatusRepository $customerStatusRepository,
        UserPasswordHasherInterface $passwordHasher
    ) {
        $this->validator = $validator;
        $this->mailService = $mailService;
        $this->customerRepository = $customerRepository;
        $this->customerStatusRepository = $customerStatusRepository;
        $this->passwordHasher = $passwordHasher;
    }

    /**
     * パスワードリマインダ.
     *
     * @Route("/forgot", name="forgot", methods={"GET", "POST"})
     * @Template("Forgot/index.twig")
     */
    public function index(Request $request)
    {
        if ($this->isGranted('IS_AUTHENTICATED_FULLY')) {
            throw new HttpException\NotFoundHttpException();
        }

        $builder = $this->formFactory
            ->createNamedBuilder('', ForgotType::class);

        $event = new EventArgs(
            [
                'builder' => $builder,
            ],
            $request
        );
        $this->eventDispatcher->dispatch($event, EccubeEvents::FRONT_FORGOT_INDEX_INITIALIZE);

        $form = $builder->getForm();
        $form->handleRequest($request);

        $error = null;

        if ($form->isSubmitted() && $form->isValid()) {
            $Customer = $this->customerRepository
                ->getRegularCustomerByEmail($form->get('login_email')->getData());

            if (!is_null($Customer)) {
                // リセットキーの発行・有効期限の設定
                $Customer
                    ->setResetKey($this->customerRepository->getUniqueResetKey())
                    ->setResetExpire(new \DateTime('+'.$this->eccubeConfig['eccube_customer_reset_expire'].' min'));

                // リセットキーを更新
                $this->entityManager->persist($Customer);
                $this->entityManager->flush();

                $event = new EventArgs(
                    [
                        'form' => $form,
                        'Customer' => $Customer,
                    ],
                    $request
                );
                $this->eventDispatcher->dispatch($event, EccubeEvents::FRONT_FORGOT_INDEX_COMPLETE);

                // 完了URLの生成
                $reset_url = $this->generateUrl('forgot_reset', ['reset_key' => $Customer->getResetKey()], UrlGeneratorInterface::ABSOLUTE_URL);

                // メール送信
                $this->mailService->sendPasswordResetNotificationMail($Customer, $reset_url);

                // ログ出力
                log_info('send reset password mail to:'."{$Customer->getId()} {$Customer->getEmail()} {$request->getClientIp()}");
            } else {
                log_warning(
                    'Un active customer try send reset password email: ',
                    ['Enter email' => $form->get('login_email')->getData()]
                );

                $error = trans('front.forgot.email_not_found');

                return [
                    'error' => $error,
                    'form' => $form->createView(),
                ];
            }

            return $this->redirectToRoute('forgot_complete'); 
        }

        return [
            'error' => $error,
            'form' => $form->createView(),
        ];
    }

    /**
     * 再設定URL送信完了画面.
     *
     * @Route("/forgot/complete", name="forgot_complete", methods={"GET"})
     * @Template("Forgot/complete.twig")
     */
    public function complete(Request $request)
    {
        if ($this->isGranted('IS_AUTHENTICATED_FULLY')) {
            throw new HttpException\NotFoundHttpException();
        }

        return [];
    }

    /**
     * パスワード再発行実行画面.
     *
     * @Route("/forgot/reset/{reset_key}", name="forgot_reset", methods={"GET", "POST"})
     * @Template("Forgot/reset.twig")
     */
    public function reset(Request $request, $reset_key)
    {
        if ($this->isGranted('IS_AUTHENTICATED_FULLY')) {
            throw new HttpException\NotFoundHttpException();
        }

        $errors = $this->validator->validate(
            $reset_key,
            [
                new Assert\NotBlank(),
                new Assert\Regex(
                    [
                        'pattern' => '/^[a-zA-Z0-9]+$/',
                    ]
                ),
            ]
        );

        if (count($errors) > 0) {
            // リセットキーに異常がある場合
            throw new HttpException\NotFoundHttpException();
        }

        $Customer = $this->customerRepository
            ->getRegularCustomerByResetKey($reset_key);

        if (null === $Customer) {
            // リセットキーから会員データが取得できない場合
            throw new HttpException\NotFoundHttpException();
        }

        $builder = $this->formFactory
            ->createNamedBuilder('', PasswordResetType::class);

        $form = $builder->getForm();
        $form->handleRequest($request);
        $error = null;

        if ($form->isSubmitted() && $form->isValid()) {
            // リセットキー・入力メールアドレスで会員情報検索
            $Customer = $this->customerRepository
                ->getRegularCustomerByResetKey($reset_key); //, $form->get('login_email')->getData());
            if ($Customer) {
                // パスワードの発行・更新
                $password = $this->passwordHasher->hashPassword($Customer, $form->get('password')->getData());
                $Customer->setPassword($password);

                // リセットキーをクリア
                //$Customer->setResetKey(null);

                // パスワードを更新
                $this->entityManager->persist($Customer);
                $this->entityManager->flush();

                $event = new EventArgs(
                    [
                        'Customer' => $Customer,
                    ],
                    $request
                );
                $this->eventDispatcher->dispatch($event, EccubeEvents::FRONT_FORGOT_RESET_COMPLETE);

                // 完了メッセージを設定
                $this->addFlash('password_reset_complete', trans('front.forgot.reset_complete'));

                // 完了ページへリダイレクト
                return $this->redirectToRoute('forgot_reset_complete', ['key' => $reset_key]);
            } else {
                // リセットキー・メールアドレスから会員データが取得できない場合
                $error = trans('front.forgot.reset_not_found');
            }
        }

        return [
            'error' => $error,
            'form' => $form->createView(),
        ];
    }

    /**
     * パスワード再設定完了画面.
     *
     * @Route("/forgot/reset_complete", name="forgot_reset_complete", methods={"GET"})
     * @Template("Forgot/reset_complete.twig")
     */
    public function reset_complete(Request $request)
    {
        if ($this->isGranted('IS_AUTHENTICATED_FULLY')) {
            throw new HttpException\NotFoundHttpException();
        }

        /** @var \Eccube\Entity\Customer $Customer */
        if(!is_null($request->query->get('key'))){
            $reset_key = $request->query->get('key');
        } elseif (!is_null($request->get('key'))) {
            $reset_key = $request->get('key');
        }
        $Customer = $this->customerRepository
                            ->getRegularCustomerByResetKey($reset_key);

        /** @var \Symfony\Component\Form\FormBuilderInterface $builder */
        $builder = $this->formFactory->createBuilder(ForgotLoginType::class, $Customer);

        $event = new EventArgs(
            [
                'builder' => $builder,
                'Customer' => $Customer,
            ],
            $request
        );
        $this->eventDispatcher->dispatch($event, EccubeEvents::FRONT_MYPAGE_MYPAGE_LOGIN_INITIALIZE);

        /** @var \Symfony\Component\Form\FormInterface $form */
        $form = $builder->getForm();

        $form->handleRequest($request);

        //ログイン後のリダイレクト先をカートに
        $this->setLoginTargetPath('cart');

        return [
            'form' => $form->createView(),
            'Customer' => $Customer,
            'reset_key' => $request->query->get('key'),
        ];
    }
    
    /**
     * 本人認証情報入力
     *
     * @Route("/forgot/info", name="forgot_info", methods={"GET", "POST"})
     * @Template("Forgot/info.twig")
     */
    public function info(Request $request)
    {
        if ($this->isGranted('IS_AUTHENTICATED_FULLY')) {
            throw new HttpException\NotFoundHttpException();
        }

        $builder = $this->formFactory
            ->createNamedBuilder('', ForgotType::class);

        $event = new EventArgs(
            [
                'builder' => $builder,
            ],
            $request
        );
        $this->eventDispatcher->dispatch($event, EccubeEvents::FRONT_FORGOT_INDEX_INITIALIZE);

        $form = $builder->getForm();
        $form->handleRequest($request);
        $error = null;

        if ($form->isSubmitted() && $form->isValid()) {
            $Customer = $this->customerRepository
                ->getProvisionalCustomerByEmail($form->get('login_email')->getData());

            if (!is_null($Customer)) {
                // リセットキーの発行・有効期限の設定
                $Customer
                    ->setResetKey($this->customerRepository->getUniqueResetKey())
                    ->setResetExpire(new \DateTime('+'.$this->eccubeConfig['eccube_customer_reset_expire'].' min'));

                // リセットキーを更新
                $this->entityManager->persist($Customer);
                $this->entityManager->flush();

                $event = new EventArgs(
                    [
                        'form' => $form,
                        'Customer' => $Customer,
                    ],
                    $request
                );
                $this->eventDispatcher->dispatch($event, EccubeEvents::FRONT_FORGOT_INDEX_COMPLETE);

                // 完了URLの生成
                $reset_url = $this->generateUrl('forgot_reset', ['reset_key' => $Customer->getResetKey()], UrlGeneratorInterface::ABSOLUTE_URL);

                // 本人認証番号（リセットキー）
                $reset_code = $Customer->getResetKey();

                // メール送信
                $this->mailService->sendForgotAuthNotificationMail($Customer, $reset_code);

                // ログ出力
                log_info('send auth number mail to:'."{$Customer->getId()} {$Customer->getEmail()} {$request->getClientIp()}");
            
                return $this->redirectToRoute('forgot_auth', ['key' => $Customer->getSecretKey()]);
            
            } else {
                // すでにパスワード設定済の場合はログイン画面を進む画面へ遷移
                $Customer = $this->customerRepository
                    ->getRegularCustomerByEmail($form->get('login_email')->getData());
                if(!is_null($Customer) && ($Customer->getStatus() == '本会員' or $Customer->getStatus() == 'ＰＷ設定済')){
                    return $this->redirectToRoute('forgot_info_complete');
                }

                // メールアドレスから仮会員データが取得できない場合
                $error = trans('front.forgot.email_not_found');
            }        
        }

        return [
            'error' => $error,
            'form' => $form->createView(),
        ];
    }
    
    /**
     * 本人認証情報入力
     *
     * @Route("/forgot/info_complete", name="forgot_info_complete", methods={"GET", "POST"})
     * @Template("Forgot/info_complete.twig")
     */
    public function info_complete(Request $request)
    {
        if ($this->isGranted('IS_AUTHENTICATED_FULLY')) {
            throw new HttpException\NotFoundHttpException();
        }

        // ログイン認証後のページ
        $this->setLoginTargetPath('forgot_entry_detail');

        return [];
    }

    /**
     * 本人認証画面.
     *
     * @Route("/forgot/auth", name="forgot_auth", methods={"GET", "POST"})
     * @Template("Forgot/auth.twig")
     */
    public function auth(Request $request)
    {
        if ($this->isGranted('IS_AUTHENTICATED_FULLY')) {
            throw new HttpException\NotFoundHttpException();
        }

        $builder = $this->formFactory
            ->createNamedBuilder('', ForgotAuthType::class);

        $event = new EventArgs(
            [
                'builder' => $builder,
            ],
            $request
        );
        $this->eventDispatcher->dispatch($event, EccubeEvents::FRONT_FORGOT_INDEX_INITIALIZE);

        $form = $builder->getForm();
        $form->handleRequest($request);

        if (!is_null($request->query->get('key'))) {
            $Customer = $this->customerRepository
                ->getProvisionalCustomerBySecretKey($request->query->get('key'));
        } elseif (!is_null($form->get('login_email')->getData())) {
            $Customer = $this->customerRepository
                ->getProvisionalCustomerByEmail($form->get('login_email')->getData());
        }
        
        $error = null;

        if ($form->isSubmitted() && $form->isValid()) {

            $Customer = $this->customerRepository
                ->getProvisionalCustomerByResetKey($form->get('auth_number')->getData(), $form->get('login_email')->getData());
            if (!is_null($Customer)) {
                return $this->redirectToRoute('forgot_entry', ['key' => $form->get('auth_number')->getData()]);
           
            } else {
                log_warning(
                    'auth number is input: ',
                    ['Enter email' => $form->get('login_email')->getData()]
                );

                // エラーメッセージ
                $error = trans('front.forgot.auth_number_not_match');

                $Customer = $this->customerRepository
                    ->getProvisionalCustomerByEmail($form->get('login_email')->getData());
            }
        }

        return [
            'form' => $form->createView(),
            'login_email' => $Customer->getEmail(),
            'secret_key' => $Customer->getSecretKey(),
            'error' => $error,
        ];
    }

    /**
     * 本人認証番号を再送
     *
     * @Route("/forgot/send", name="forgot_send", methods={"GET"})
     */
    public function send(Request $request)
    {
        if ($this->isGranted('IS_AUTHENTICATED_FULLY')) {
            throw new HttpException\NotFoundHttpException();
        }

        $builder = $this->formFactory
            ->createNamedBuilder('', ForgotType::class);

        $event = new EventArgs(
            [
                'builder' => $builder,
            ],
            $request
        );
        $this->eventDispatcher->dispatch($event, EccubeEvents::FRONT_FORGOT_INDEX_INITIALIZE);

        $form = $builder->getForm();
        $form->handleRequest($request);

        if (!is_null($request->query->get('key'))) {
            $Customer = $this->customerRepository
                ->getProvisionalCustomerBySecretKey($request->query->get('key'));

            if (!is_null($Customer)) {
                // リセットキーの発行・有効期限の設定
                $Customer
                    ->setResetKey($this->customerRepository->getUniqueResetKey())
                    ->setResetExpire(new \DateTime('+'.$this->eccubeConfig['eccube_customer_reset_expire'].' min'));

                // リセットキーを更新
                $this->entityManager->persist($Customer);
                $this->entityManager->flush();
                
                // 本人認証番号（リセットキー）
                $reset_code = $Customer->getResetKey();

                // メール送信
                $this->mailService->sendForgotAuthNotificationMail($Customer, $reset_code);

                // ログ出力
                log_info('send auth number mail to:'."{$Customer->getId()} {$Customer->getEmail()} {$request->getClientIp()}");
            
                return $this->redirectToRoute('forgot_auth', ['key' => $Customer->getSecretKey()]);
            
            } else {
                log_warning(
                    'send auth number email: ',
                    ['Enter email' => $form->get('login_email')->getData()]
                );
            }        
        }

        return $this->redirectToRoute('forgot_auth', ['key' => $Customer->getSecretKey()]);
    }
    
    /**
     * 個人情報入力画面.
     *
     * @Route("/forgot/entry", name="forgot_entry", methods={"GET", "POST"})
     * @Route("/forgot/entry", name="forgot_confirm", methods={"GET", "POST"})
     * @Template("Forgot/entry.twig")
     */
    public function entry(Request $request)
    {
        if ($this->isGranted('IS_AUTHENTICATED_FULLY')) {
            throw new HttpException\NotFoundHttpException();
        }

        /** @var \Eccube\Entity\Customer $Customer */
        //$Customer = $this->customerRepository->newCustomer();
        if(!is_null($request->query->get('key'))){
            $reset_key = $request->query->get('key');
        } elseif (!is_null($request->get('key'))) {
            $reset_key = $request->get('key');
        }
        $Customer = $this->customerRepository
                            ->getProvisionalCustomerByResetKey($reset_key);
        
        if (!$Customer) {
            throw new NotFoundHttpException();
        }

        // スマホ表示フラグ
        $detect = new MobileDetect;
        $is_mobile = $detect->isMobile();
        $is_tablet = $detect->isTablet();

        /** @var \Symfony\Component\Form\FormBuilderInterface $builder */
        $builder = $this->formFactory->createBuilder(ForgotEntryType::class, $Customer);

        $event = new EventArgs(
            [
                'builder' => $builder,
                'Customer' => $Customer,
            ],
            $request
        );
        $this->eventDispatcher->dispatch($event, EccubeEvents::FRONT_ENTRY_INDEX_INITIALIZE);

        /** @var \Symfony\Component\Form\FormInterface $form */
        $form = $builder->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            switch ($request->get('mode')) {
                case 'confirm':
                    log_info('個人情報入力確認');

                    return $this->render(
                        'Forgot/entry_confirm.twig',
                        [
                            'form' => $form->createView(),
                            'is_mobile' => $is_mobile,
                            'Customer' => $Customer,
                            //'Page' => $this->pageRepository->getPageByRoute('forgot_confirm'),
                            'reset_key' => $request->get('key'),
                        ]
                    );
                case 'complete':
                    log_info('個人情報入力完了');

                    //パスワード保存
                    $password = $this->passwordHasher->hashPassword($Customer, $Customer->getPlainPassword());
                    $Customer->setPassword($password);

                    //ステータスを本会員にする
                    $CustomerStatus = $this->customerStatusRepository->find(CustomerStatus::REGULAR);
                    $Customer->setStatus($CustomerStatus);

                    //本人認証番号（リセットキー）をクリア
                    //$Customer->setResetKey(null);

                    $this->entityManager->persist($Customer);
                    $this->entityManager->flush();

                    $event = new EventArgs(
                        [
                            'form' => $form,
                            'Customer' => $Customer,
                        ],
                        $request
                    );
                    $this->eventDispatcher->dispatch($event, EccubeEvents::FRONT_ENTRY_INDEX_COMPLETE);

                    // 個人情報確認済フラグをセッションに保存
                    $this->session->set(EccubeEvents::FRONT_FORGOT_ENTRY_COMPLETE, true);

                    // 完了画面へリダイレクト
                    //return $this->redirectToRoute('forgot_entry_complete', ['key' => $reset_key]);

                    // 二段階認証へリダイレクト
                    return $this->redirectToRoute('mypage_login');
            }
        };  

        return [
            'form' => $form->createView(),
            'is_mobile' => $is_mobile,
            'Customer' => $Customer,
            'reset_key' => $reset_key,
        ];
    }

    /**
     * 個人情報入力完了画面.
     *
     * @Route("/forgot/entry_complete", name="forgot_entry_complete", methods={"GET"})
     * @Template("Forgot/entry_complete.twig")
     */
    public function entry_complete(Request $request)
    {
        /** @var \Eccube\Entity\Customer $Customer */
        if(!is_null($request->query->get('key'))){
            $reset_key = $request->query->get('key');
        } elseif (!is_null($request->get('key'))) {
            $reset_key = $request->get('key');
        }
        $Customer = $this->customerRepository
                            ->getRegularCustomerByResetKey($reset_key);

        /** @var \Symfony\Component\Form\FormBuilderInterface $builder */
        $builder = $this->formFactory->createBuilder(ForgotLoginType::class, $Customer);

        $event = new EventArgs(
            [
                'builder' => $builder,
                'Customer' => $Customer,
            ],
            $request
        );
        $this->eventDispatcher->dispatch($event, EccubeEvents::FRONT_MYPAGE_MYPAGE_LOGIN_INITIALIZE);

        /** @var \Symfony\Component\Form\FormInterface $form */
        $form = $builder->getForm();

        $form->handleRequest($request);

        //ログイン後のリダイレクト先をカートに
        $this->setLoginTargetPath('cart');

        return [
            'form' => $form->createView(),
        ];
    }

    /**
     * 個人情報入力画面.
     *
     * @Route("/forgot/entry_detail", name="forgot_entry_detail", methods={"GET", "POST"})
     * @Template("Forgot/entry_detail.twig")
     */
    public function entry_detail(Request $request)
    {
        if (!$this->isGranted('ROLE_USER')) {
           return $this->redirectToRoute('mypage_login');
        }
        
        $Customer = $this->getUser();

        /** @var \Symfony\Component\Form\FormBuilderInterface $builder */
        $builder = $this->formFactory->createBuilder(ForgotEntryType::class, $Customer);

        $event = new EventArgs(
            [
                'builder' => $builder,
                'Customer' => $Customer,
            ],
            $request
        );
        $this->eventDispatcher->dispatch($event, EccubeEvents::FRONT_ENTRY_INDEX_INITIALIZE);

        // 認証フラグをOFFにする
        $Customer->setDeviceAuthed(false);
        $this->entityManager->persist($Customer);
        $this->entityManager->flush();

        /** @var \Symfony\Component\Form\FormInterface $form */
        $form = $builder->getForm();

        return [
            'form' => $form->createView(),
            'Customer' => $Customer,
            'reset_key' => $request->query->get('key'),
        ];
    }
}
