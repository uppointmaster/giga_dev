<?php

namespace Customize\Controller\Admin;

use Eccube\Controller\AbstractController;
use Eccube\Util\CacheUtil;
use Customize\Form\Type\Admin\SchoolType;
use Customize\Repository\SchoolRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Knp\Component\Pager\PaginatorInterface;

class SchoolController extends AbstractController
{
    /**
     * @var SchoolRepository
     */
    protected $schoolRepository;

    /**
     * SchoolController constructor.
     *
     * @param SchoolRepository $schoolRepository
     */
    public function __construct(SchoolRepository $schoolRepository)
    {
        $this->schoolRepository = $schoolRepository;
    }

    /**
     * レシピ考案者一覧を表示する。
     *
     * @Route("/%eccube_admin_route%/school/", name="admin_school", methods={"GET"})
     * @Route("/%eccube_admin_route%/school/page/{page_no}", requirements={"page_no" = "\d+"}, name="admin_school_page", methods={"GET"})
     * @Template("@admin/school/index.twig")
     *
     * @param Request $request
     * @param int $page_no
     * @param PaginatorInterface $paginator
     *
     * @return array
     */
    public function index(Request $request, PaginatorInterface $paginator, $page_no = 1)
    {
        $qb = $this->schoolRepository->getQueryBuilderAll();

        $pagination = $paginator->paginate(
            $qb,
            $page_no,
            $this->eccubeConfig->get('eccube_default_page_count')
        );

        return [
            'pagination' => $pagination,
        ];
    }

    /**
     * @Route("/%eccube_admin_route%/school/new", name="admin_school_new", methods={"GET", "POST"})
     * @Route("/%eccube_admin_route%/school/{id}/edit", requirements={"id" = "\d+"}, name="admin_school_edit", methods={"GET", "POST"})
     * @Template("@admin/school/edit.twig")
     */
    public function edit(Request $request, $id = null)
    {
        if ($id) {
            $school = $this->schoolRepository->find($id);
            if (!$school) {
                throw new NotFoundHttpException();
            }
        } else {
            $school = new \Customize\Entity\School();
        }

        $form = $this->createForm(SchoolType::class, $school);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $school = $form->getData();
            $req = $request->request->all();
            $req = $req['admin_school'];
            if (isset($req['image']) && !empty($req['image'])) {
                $filename = $req['image'];
                // 移動
                $filesystem = new Filesystem();
                if ($filesystem->exists($this->eccubeConfig['eccube_temp_image_dir'].'/'.$filename)) {
                    $file = new File($this->eccubeConfig['eccube_temp_image_dir'].'/'.$filename);
                    $file->move($this->eccubeConfig['eccube_save_image_dir']);
                }
                $beforeFilename = $school->getFilename();
                $school->setFilename($filename);
                $this->entityManager->persist($school);
                $this->entityManager->flush();
                // 旧ファイル削除
                if (
                    $school->getFilename() !== $filename &&
                    $beforeFilename && $filesystem->exists($this->eccubeConfig['eccube_save_image_dir'].'/'.$beforeFilename)) {
                    $filesystem->remove($this->eccubeConfig['eccube_save_image_dir'].'/'.$beforeFilename);
                }
                $this->addSuccess('登録しました。', 'admin');
            } else {
                $beforeFilename = $school->getFilename();
                $school->setFilename(null);
                $this->entityManager->persist($school);
                $this->entityManager->flush();
                // 旧ファイル削除
                $filesystem = new Filesystem();
                if (
                    $beforeFilename && $filesystem->exists($this->eccubeConfig['eccube_save_image_dir'].'/'.$beforeFilename)) {
                    $filesystem->remove($this->eccubeConfig['eccube_save_image_dir'].'/'.$beforeFilename);
                }
                $this->addSuccess('登録しました。', 'admin');
            }

            return $this->redirectToRoute('admin_school_edit', ['id' => $school->getId()]);
        }

        return [
            'form' => $form->createView(),
        ];
    }

    /**
     * 指定したレシピ考案者を削除する。
     *
     * @Route("/%eccube_admin_route%/school/{id}/delete", requirements={"id" = "\d+"}, name="admin_school_delete", methods={"DELETE"})
     *
     * @param Request $request
     * @param \Customize\Entity\School $school
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function delete(Request $request, \Customize\Entity\School $School, CacheUtil $cacheUtil)
    {
        $this->isTokenValid();

        log_info('レシピ考案者削除開始', [$School->getId()]);

        try {
            $this->schoolRepository->delete($School);

            $this->addSuccess('admin.common.delete_complete', 'admin');

            log_info('レシピ考案者削除完了', [$School->getId()]);

            // キャッシュの削除
            $cacheUtil->clearDoctrineCache();
        } catch (\Exception $e) {
            $message = trans('admin.common.delete_error_foreign_key', ['%name%' => $School->getName()]);
            $this->addError($message, 'admin');

            log_error('レシピ考案者削除エラー', [$School->getId(), $e]);
        }

        return $this->redirectToRoute('admin_school');
    }

    /**
     * 画像アップロード時にリクエストされるメソッド.
     *
     * @see https://pqina.nl/filepond/docs/api/server/#process
     * @Route("/%eccube_admin_route%/school/image/process", name="admin_school_image_process", methods={"POST"})
     */
    public function imageProcess(Request $request)
    {
        if (!$request->isXmlHttpRequest() && $this->isTokenValid()) {
            throw new BadRequestHttpException();
        }

        $data = $request->files->get('admin_school');
        $image = $data['image'];
        $allowExtensions = ['gif', 'jpg', 'jpeg', 'png'];
        // ファイルフォーマット検証
        $mimeType = $image->getMimeType();
        if (0 !== strpos($mimeType, 'image')) {
          throw new UnsupportedMediaTypeHttpException();
        }
        // 拡張子
        $extension = $image->getClientOriginalExtension();
        if (!in_array(strtolower($extension), $allowExtensions)) {
          throw new UnsupportedMediaTypeHttpException();
        }
        $filename = date('mdHis').uniqid('_').'.'.$extension;
        $image->move($this->eccubeConfig['eccube_temp_image_dir'], $filename);
        return new Response($filename);
    }

    /**
     * アップロード画像を取得する際にコールされるメソッド.
     *
     * @see https://pqina.nl/filepond/docs/api/server/#load
     * @Route("/%eccube_admin_route%/school/image/load", name="admin_school_image_load", methods={"GET"})
     */
    public function imageLoad(Request $request)
    {
        if (!$request->isXmlHttpRequest()) {
            throw new BadRequestHttpException();
        }

        $dirs = [
            $this->eccubeConfig['eccube_save_image_dir'],
            $this->eccubeConfig['eccube_temp_image_dir'],
        ];

        foreach ($dirs as $dir) {
            if (strpos($request->query->get('source'), '..') !== false) {
                throw new NotFoundHttpException();
            }
            $image = \realpath($dir.'/'.$request->query->get('source'));
            $dir = \realpath($dir);

            if (\is_file($image) && \str_starts_with($image, $dir)) {
                $file = new \SplFileObject($image);

                return $this->file($file, $file->getBasename());
            }
        }

        throw new NotFoundHttpException();
    }

    /**
     * アップロード画像をすぐ削除する際にコールされるメソッド.
     *
     * @see https://pqina.nl/filepond/docs/api/server/#revert
     * @Route("/%eccube_admin_route%/school/image/revert", name="admin_school_image_revert", methods={"DELETE"})
     */
    public function imageRevert(Request $request)
    {
        if (!$request->isXmlHttpRequest() && $this->isTokenValid()) {
            throw new BadRequestHttpException();
        }

        $tempFile = $this->eccubeConfig['eccube_temp_image_dir'].'/'.$request->getContent();
        if (is_file($tempFile) && stripos(realpath($tempFile), $this->eccubeConfig['eccube_temp_image_dir']) === 0) {
            $fs = new Filesystem();
            $fs->remove($tempFile);

            return new Response(null, Response::HTTP_NO_CONTENT);
        }

        throw new NotFoundHttpException();
    }
}
