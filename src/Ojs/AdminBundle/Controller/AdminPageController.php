<?php

namespace Ojs\AdminBundle\Controller;

use APY\DataGridBundle\Grid\Column\ActionsColumn;
use APY\DataGridBundle\Grid\Row;
use APY\DataGridBundle\Grid\Source\Entity;
use Ojs\AdminBundle\Entity\AdminPage;
use Ojs\AdminBundle\Form\Type\AdminPageType;
use Ojs\CoreBundle\Controller\OjsController as Controller;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\Exception\TokenNotFoundException;

/**
 * Page controller.
 */
class AdminPageController extends Controller
{

    /**
     * List all page entities
     *
     * @param Request $request
     * @return Response
     */
    public function indexAction(Request $request)
    {
        if (!$this->isGranted('VIEW', new AdminPage())) {
            throw new AccessDeniedException("You are not authorized for this page!");
        }
        $cache = $this->get('array_cache');
        $source = new Entity('OjsAdminBundle:AdminPage');
        $source->manipulateRow(
            function (Row $row) use ($request, $cache) {
                /* @var AdminPage $entity */
                $entity = $row->getEntity();
                $entity->setDefaultLocale($request->getDefaultLocale());
                if (!is_null($entity)) {
                    if($cache->contains('grid_row_id_'.$entity->getId())){
                        $row->setClass('hidden');
                    }else{
                        $cache->save('grid_row_id_'.$entity->getId(), true);
                        $row->setField('translations.title', $entity->getTitleTranslations());
                    }
                }
                return $row;
            }
        );

        $grid = $this->get('grid')->setSource($source);
        $gridAction = $this->get('grid_action');

        $actionColumn = new ActionsColumn("actions", 'actions');
        $rowAction[] = $gridAction->showAction('ojs_admin_page_show', 'id');
        $rowAction[] = $gridAction->editAction('ojs_admin_page_edit', 'id');
        $rowAction[] = $gridAction->deleteAction('ojs_admin_page_delete', 'id');
        $actionColumn->setRowActions($rowAction);
        $grid->addColumn($actionColumn);

        return $grid->getGridResponse('OjsAdminBundle:AdminPage:index.html.twig', ['grid' => $grid]);
    }

    /**
     * Displays a form to create a new Page entity.
     */
    public function newAction()
    {
        $entity = new AdminPage();
        if (!$this->isGranted('CREATE', $entity)) {
            throw new AccessDeniedException("You are not authorized for this page!");
        }

        $form = $this->createCreateForm($entity);

        return $this->render(
            'OjsAdminBundle:AdminPage:new.html.twig',
            ['entity' => $entity, 'form' => $form->createView()]
        );
    }

    /**
     * Creates a form to create a Page entity.
     *
     * @param  AdminPage $entity The entity
     * @return Form The form
     */
    private function createCreateForm(AdminPage $entity)
    {
        $form = $this->createForm(
            new AdminPageType(),
            $entity,
            ['action' => $this->generateUrl('ojs_admin_page_create'), 'method' => 'POST']
        );
        $form->add('submit', 'submit', ['label' => 'Create']);

        return $form;
    }

    /**
     * Creates a new Page entity.
     *
     * @param  Request $request
     * @return RedirectResponse|Response
     */
    public function createAction(Request $request)
    {
        if (!$this->isGranted('CREATE', new AdminPage())) {
            throw new AccessDeniedException("You are not authorized for this page!");
        }

        $entity = new AdminPage();
        $entity->setCurrentLocale($request->getDefaultLocale());
        $form = $this->createCreateForm($entity);
        $form->handleRequest($request);

        if ($form->isValid()) {
            $entity->setSlug($entity->getTranslationByLocale($request->getDefaultLocale())->getTitle());
            $em = $this->getDoctrine()->getManager();
            $em->persist($entity);
            $em->flush();

            $this->successFlashBag('successful.create');

            return $this->redirectToRoute('ojs_admin_page_show', ['id' => $entity->getId()]);
        }

        return $this->render(
            'OjsAdminBundle:AdminPage:new.html.twig',
            ['entity' => $entity, 'form' => $form->createView()]
        );
    }

    /**
     * Finds and displays a Page entity.
     *
     * @param  AdminPage $entity
     * @return Response
     */
    public function showAction(AdminPage $entity)
    {
        $this->throw404IfNotFound($entity);

        if (!$this->isGranted('VIEW', $entity)) {
            throw new AccessDeniedException("You are not authorized for this page!");
        }

        $token = $this
            ->get('security.csrf.token_manager')
            ->refreshToken('ojs_admin_page'.$entity->getId());

        return $this->render(
            'OjsAdminBundle:AdminPage:show.html.twig',
            ['entity' => $entity, 'token' => $token]
        );
    }

    /**
     * Displays a form to edit an existing Page entity.
     *
     * @param  AdminPage $entity
     * @return Response
     */
    public function editAction(AdminPage $entity)
    {
        $this->throw404IfNotFound($entity);

        if (!$this->isGranted('EDIT', $entity)) {
            throw new AccessDeniedException("You are not authorized for this page!");
        }

        $token = $this
            ->get('security.csrf.token_manager')
            ->refreshToken('ojs_admin_page'.$entity->getId());

        $editForm = $this->createEditForm($entity);

        return $this->render(
            'OjsAdminBundle:AdminPage:edit.html.twig',
            [
                'token' => $token,
                'entity' => $entity,
                'edit_form' => $editForm->createView(),
            ]
        );
    }

    /**
     * Creates a form to edit a Page entity.
     *
     * @param  AdminPage $entity The entity
     * @return Form The form
     */
    private function createEditForm(AdminPage $entity)
    {
        $form = $this->createForm(
            new AdminPageType(),
            $entity,
            [
                'action' => $this->generateUrl('ojs_admin_page_update', ['id' => $entity->getId()]),
                'method' => 'PUT',
            ]
        );

        $form->add('submit', 'submit', ['label' => 'Update']);

        return $form;
    }

    /**
     * Edits an existing Page entity.
     *
     * @param  Request $request
     * @param  AdminPage $entity
     * @return RedirectResponse|Response
     */
    public function updateAction(Request $request, AdminPage $entity)
    {
        $this->throw404IfNotFound($entity);

        if (!$this->isGranted('EDIT', $entity)) {
            throw new AccessDeniedException("You are not authorized for this page!");
        }

        $em = $this->getDoctrine()->getManager();
        $editForm = $this->createEditForm($entity);
        $editForm->handleRequest($request);

        if ($editForm->isValid()) {
            $em->flush();
            $this->successFlashBag('successful.update');

            return $this->redirectToRoute('ojs_admin_page_edit', ['id' => $entity->getId()]);
        }

        return $this->render(
            'OjsAdminBundle:AdminPage:edit.html.twig',
            array(
                'entity' => $entity,
                'edit_form' => $editForm->createView(),
            )
        );
    }

    /**
     * Deletes a Page entity.
     * @param  Request $request
     * @param  AdminPage $entity
     * @return RedirectResponse
     */
    public function deleteAction(Request $request, AdminPage $entity)
    {
        $this->throw404IfNotFound($entity);

        if (!$this->isGranted('DELETE', $entity)) {
            throw new AccessDeniedException("You are not authorized for this page!");
        }

        $em = $this->getDoctrine()->getManager();
        $csrf = $this->get('security.csrf.token_manager');
        $token = $csrf->getToken('ojs_admin_page'.$entity->getId());

        if ($token != $request->get('_token')) {
            throw new TokenNotFoundException("Token not found!");
        }
        $this->get('ojs_core.delete.service')->check($entity);

        $em->remove($entity);
        $em->flush();
        $this->successFlashBag('successful.remove');

        return $this->redirectToRoute('ojs_admin_page_index');
    }
}
