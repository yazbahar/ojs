<?php

namespace Ojs\JournalBundle\Controller;

use APY\DataGridBundle\Grid\Column\ActionsColumn;
use APY\DataGridBundle\Grid\Source\Entity;
use Ojs\CoreBundle\Controller\OjsController as Controller;
use Ojs\JournalBundle\Entity\JournalSubmissionFile;
use Ojs\JournalBundle\Event\JournalEvent;
use Ojs\JournalBundle\Event\JournalItemEvent;
use Ojs\JournalBundle\Event\JournalSubmissionFile\JournalSubmissionFileEvents;
use Ojs\JournalBundle\Event\ListEvent;
use Ojs\JournalBundle\Form\Type\JournalSubmissionFileType;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\TokenNotFoundException;

/**
 * JournalSubmissionFile controller.
 *
 */
class JournalSubmissionFileController extends Controller
{
    /**
     * Lists all SubmissionFile entities.
     *
     */
    public function indexAction()
    {
        $journal = $this->get('ojs.journal_service')->getSelectedJournal();
        $eventDispatcher = $this->get('event_dispatcher');
        if (!$this->isGranted('VIEW', $journal, 'file')) {
            throw new AccessDeniedException("You are not authorized for view this page!");
        }
        if (!$journal) {
            throw new NotFoundHttpException("Journal not found!");
        }
        $source = new Entity('OjsJournalBundle:JournalSubmissionFile');

        $grid = $this->get('grid')->setSource($source);
        $gridAction = $this->get('grid_action');

        $actionColumn = new ActionsColumn("actions", 'actions');

        $rowAction[] = $gridAction->showAction('ojs_journal_file_show', ['id']);
        $rowAction[] = $gridAction->editAction('ojs_journal_file_edit', ['id']);
        $rowAction[] = $gridAction->deleteAction('ojs_journal_file_delete', ['id']);

        $actionColumn->setRowActions($rowAction);
        $grid->addColumn($actionColumn);

        $listEvent = new ListEvent();
        $listEvent->setGrid($grid);
        $eventDispatcher->dispatch(JournalSubmissionFileEvents::LISTED, $listEvent);
        $grid = $listEvent->getGrid();

        return $grid->getGridResponse('OjsJournalBundle:SubmissionFile:index.html.twig');
    }

    /**
     * Creates a new SubmissionFile entity.
     *
     * @param  Request                   $request
     * @return RedirectResponse|Response
     */
    public function createAction(Request $request)
    {
        $journal = $this->get('ojs.journal_service')->getSelectedJournal();
        $eventDispatcher = $this->get('event_dispatcher');
        if (!$this->isGranted('CREATE', $journal, 'file')) {
            throw new AccessDeniedException("You are not authorized for view this page!");
        }
        $entity = new JournalSubmissionFile();
        $form = $this->createCreateForm($entity);
        $form->handleRequest($request);

        if ($form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $entity->setJournal($journal);

            $event = new JournalItemEvent($entity);
            $eventDispatcher->dispatch(JournalSubmissionFileEvents::PRE_CREATE, $event);

            $em->persist($entity);
            $em->flush();

            $event = new JournalItemEvent($event->getItem());
            $eventDispatcher->dispatch(JournalSubmissionFileEvents::POST_CREATE, $event);

            if ($event->getResponse()) {
                return $event->getResponse();
            }

            $this->successFlashBag('successful.create');

            return $this->redirect(
                $this->generateUrl(
                    'ojs_journal_file_show',
                    array('id' => $entity->getId())
                )
            );
        }

        return $this->render(
            'OjsJournalBundle:SubmissionFile:new.html.twig',
            array(
                'entity' => $entity,
                'form' => $form->createView(),
            )
        );
    }

    /**
     * @param  JournalSubmissionFile        $entity
     * @return \Symfony\Component\Form\Form
     */
    private function createCreateForm(JournalSubmissionFile $entity)
    {
        $languages = [];
        if (is_array($this->container->getParameter('languages'))) {
            foreach ($this->container->getParameter('languages') as $key => $language) {
                if (array_key_exists('code', $language)) {
                    $languages[$language['code']] = $language['name'];
                }
            }
        }
        $form = $this->createForm(
            new JournalSubmissionFileType(),
            $entity,
            array(
                'action' => $this->generateUrl('ojs_journal_file_create'),
                'languages' => $languages,
                'method' => 'POST',
            )
        );

        $form->add('submit', 'submit', array('label' => 'Create'));

        return $form;
    }

    /**
     * Displays a form to create a new SubmissionFile entity.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function newAction()
    {
        $journal = $this->get('ojs.journal_service')->getSelectedJournal();
        if (!$this->isGranted('CREATE', $journal, 'file')) {
            throw new AccessDeniedException("You are not authorized for view this page!");
        }
        $entity = new JournalSubmissionFile();
        $form = $this->createCreateForm($entity);

        return $this->render(
            'OjsJournalBundle:SubmissionFile:new.html.twig',
            array(
                'entity' => $entity,
                'form' => $form->createView(),
            )
        );
    }

    /**
     * Finds and displays a SubmissionFile entity.
     *
     * @param  JournalSubmissionFile                      $entity
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function showAction(JournalSubmissionFile $entity)
    {
        $this->throw404IfNotFound($entity);
        $journal = $this->get('ojs.journal_service')->getSelectedJournal();
        if (!$this->isGranted('VIEW', $journal, 'file')) {
            throw new AccessDeniedException("You are not authorized for view this page!");
        }

        $token = $this
            ->get('security.csrf.token_manager')
            ->refreshToken('ojs_journal_file'.$entity->getId());

        return $this->render(
            'OjsJournalBundle:SubmissionFile:show.html.twig',
            array(
                'entity' => $entity,
                'token' => $token,
            )
        );
    }

    /**
     * Displays a form to edit an existing SubmissionFile entity.
     *
     * @param  JournalSubmissionFile                      $entity
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function editAction(JournalSubmissionFile $entity)
    {
        $this->throw404IfNotFound($entity);
        $journal = $this->get('ojs.journal_service')->getSelectedJournal();
        if (!$this->isGranted('EDIT', $journal, 'file')) {
            throw new AccessDeniedException("You are not authorized for view this page!");
        }
        $editForm = $this->createEditForm($entity);

        return $this->render(
            'OjsJournalBundle:SubmissionFile:edit.html.twig',
            array(
                'entity' => $entity,
                'edit_form' => $editForm->createView(),
            )
        );
    }

    /**
     * Creates a form to edit a SubmissionFile entity.
     *
     * @param  JournalSubmissionFile        $entity
     * @return \Symfony\Component\Form\Form
     */
    private function createEditForm(JournalSubmissionFile $entity)
    {
        $languages = [];
        if (is_array($this->container->getParameter('languages'))) {
            foreach ($this->container->getParameter('languages') as $key => $language) {
                if (array_key_exists('code', $language)) {
                    $languages[$language['code']] = $language['name'];
                }
            }
        }

        $form = $this->createForm(
            new JournalSubmissionFileType(),
            $entity,
            array(
                'action' => $this->generateUrl(
                    'ojs_journal_file_update',
                    array('id' => $entity->getId())
                ),
                'languages' => $languages,
                'method' => 'PUT',
            )
        );

        $form->add('submit', 'submit', array('label' => 'Update'));

        return $form;
    }

    /**
     * Edits an existing SubmissionFile entity.
     *
     * @param  Request                   $request
     * @param  JournalSubmissionFile     $entity
     * @return RedirectResponse|Response
     */
    public function updateAction(Request $request, JournalSubmissionFile $entity)
    {
        $journal = $this->get('ojs.journal_service')->getSelectedJournal();
        $eventDispatcher = $this->get('event_dispatcher');

        $this->throw404IfNotFound($entity);
        if (!$this->isGranted('EDIT', $journal, 'file')) {
            throw new AccessDeniedException("You are not authorized for view this page!");
        }

        $em = $this->getDoctrine()->getManager();
        $editForm = $this->createEditForm($entity);
        $editForm->handleRequest($request);
        if ($editForm->isValid()) {
            $event = new JournalItemEvent($entity);
            $eventDispatcher->dispatch(JournalSubmissionFileEvents::PRE_UPDATE, $event);
            $em->persist($event->getItem());
            $em->flush();

            $event = new JournalItemEvent($event->getItem());
            $eventDispatcher->dispatch(JournalSubmissionFileEvents::POST_UPDATE, $event);

            if ($event->getResponse()) {
                return $event->getResponse();
            }

            $this->successFlashBag('successful.update');

            return $this->redirect(
                $this->generateUrl(
                    'ojs_journal_file_edit',
                    array('id' => $entity->getId())
                )
            );
        }

        return $this->render(
            'OjsJournalBundle:SubmissionFile:edit.html.twig',
            array(
                'entity' => $entity,
                'edit_form' => $editForm->createView(),
            )
        );
    }

    /**
     * Deletes a SubmissionFile entity.
     *
     * @param  Request               $request
     * @param  JournalSubmissionFile $entity
     * @return RedirectResponse
     */
    public function deleteAction(Request $request, JournalSubmissionFile $entity)
    {
        $journal = $this->get('ojs.journal_service')->getSelectedJournal();
        $eventDispatcher = $this->get('event_dispatcher');

        $this->throw404IfNotFound($entity);
        if (!$this->isGranted('DELETE', $journal, 'file')) {
            throw new AccessDeniedException("You are not authorized for view this page!");
        }
        $em = $this->getDoctrine()->getManager();
        $csrf = $this->get('security.csrf.token_manager');
        $token = $csrf->getToken('ojs_journal_file'.$entity->getId());
        if ($token != $request->get('_token')) {
            throw new TokenNotFoundException("Token Not Found!");
        }

        $event = new JournalItemEvent($entity);
        $eventDispatcher->dispatch(JournalSubmissionFileEvents::PRE_DELETE, $event);

        $em->remove($entity);
        $em->flush();

        $event = new JournalEvent($journal);
        $eventDispatcher->dispatch(JournalSubmissionFileEvents::POST_DELETE, $event);

        if ($event->getResponse()) {
            return $event->getResponse();
        }

        $this->successFlashBag('successful.remove');

        return $this->redirectToRoute('ojs_journal_file_index');
    }
}
