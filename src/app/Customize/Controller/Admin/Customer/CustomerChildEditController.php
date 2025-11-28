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

namespace Customize\Controller\Admin\Customer;

use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Eccube\Controller\AbstractController;
use Eccube\Entity\Customer;
use Eccube\Entity\CustomerAddress;
use Eccube\Event\EccubeEvents;
use Eccube\Event\EventArgs;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Customize\Entity\Child;
use Customize\Repository\ChildRepository;
use Customize\Form\Type\Admin\ChildType;

class CustomerChildEditController extends AbstractController
{
    /**
     * @var ChildRepository
     */
    protected $childRepository;

    public function __construct(
        ChildRepository $childRepository
    ) {
        $this->childRepository = $childRepository;
    }

    /**
     * 子供編集画面.
     *
     * @Route("/%eccube_admin_route%/customer/{id}/child/new", name="admin_customer_child_new", requirements={"id" = "\d+"}, methods={"GET", "POST"})
     * @Route("/%eccube_admin_route%/customer/{id}/child/{cid}/edit", name="admin_customer_child_edit", requirements={"id" = "\d+", "cid" = "\d+"}, methods={"GET", "POST"})
     * @Template("@admin/Customer/child_edit.twig")
     */
    public function edit(Request $request, Customer $Customer, $cid = null)
    {
        if (is_null($cid)) {
            $Child = new Child();
            $Child->setCustomer($Customer);
        } else {
            $Child = $this->childRepository->findOneBy(
                [
                    'id' => $cid,
                    'Customer' => $Customer,
                ]
            );
            if (!$Child) {
                throw new NotFoundHttpException();
            }
        }

        $builder = $this->formFactory
            ->createBuilder(ChildType::class, $Child);

        $form = $builder->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            log_info('子供登録開始', [$cid]);

            $this->entityManager->persist($Child);
            $this->entityManager->flush();

            log_info('子供登録完了', [$cid]);

            $this->addSuccess('admin.common.save_complete', 'admin');

            return $this->redirect($this->generateUrl('admin_customer_child_edit', [
                'id' => $Customer->getId(),
                'cid' => $Child->getId(),
            ]));
        }

        return [
            'form' => $form->createView(),
            'Customer' => $Customer,
            'Child' => $Child,
        ];
    }

    /**
     * @Route("/%eccube_admin_route%/customer/{id}/child/{cid}/delete", requirements={"id" = "\d+", "cid" = "\d+"}, name="admin_customer_child_delete", methods={"DELETE"})
     */
    public function delete(Request $request, Customer $Customer, $cid)
    {
        $this->isTokenValid();

        log_info('子供削除開始', [$cid]);

        $Child = $this->childRepository->find($cid);
        if (is_null($Child)) {
            throw new NotFoundHttpException();
        } else {
            if ($Child->getCustomer()->getId() != $Customer->getId()) {
                $this->deleteMessage();

                return $this->redirect($this->generateUrl('admin_customer_edit', ['id' => $Customer->getId()]));
            }
        }

        try {
            $this->childRepository->delete($Child);
            $this->addSuccess('admin.common.delete_complete', 'admin');
        } catch (ForeignKeyConstraintViolationException $e) {
            log_error('子供削除失敗', [$e]);

            $message = trans('admin.common.delete_error_foreign_key', ['%name%' => trans('admin.customer.customer_child')]);
            $this->addError($message, 'admin');
        }

        log_info('子供削除完了', [$cid]);

        return $this->redirect($this->generateUrl('admin_customer_edit', ['id' => $Customer->getId()]));
    }
}
