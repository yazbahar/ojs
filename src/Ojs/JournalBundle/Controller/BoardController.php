<?php

namespace Ojs\JournalBundle\Controller;

use APY\DataGridBundle\Grid\Column\ActionsColumn;
use APY\DataGridBundle\Grid\Source\Entity;
use Doctrine\ORM\QueryBuilder;
use Ojs\CoreBundle\Controller\OjsController as Controller;
use Ojs\JournalBundle\Entity\Board;
use Ojs\JournalBundle\Entity\BoardMember;
use Ojs\JournalBundle\Event\Board\BoardEvents;
use Ojs\JournalBundle\Event\JournalEvent;
use Ojs\JournalBundle\Event\JournalItemEvent;
use Ojs\JournalBundle\Event\ListEvent;
use Ojs\JournalBundle\Form\Type\BoardMemberType;
use Ojs\JournalBundle\Form\Type\BoardType;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\TokenNotFoundException;
use APY\DataGridBundle\Grid\Row;

/**
 * Board controller.
 *
 */
class BoardController extends Controller
{
    /**
     * Lists all Board entities.
     *
     */
    public function indexAction(Request $request)
    {
        $journal = $this->get('ojs.journal_service')->getSelectedJournal();
        $eventDispatcher = $this->get('event_dispatcher');

        if (!$this->isGranted('VIEW', $journal, 'boards')) {
            throw new AccessDeniedException("You not authorized for view this journal's boards!");
        }
        $cache = $this->get('array_cache');
        $source = new Entity('OjsJournalBundle:Board');
        $source->manipulateRow(
            function (Row $row) use ($request, $cache)
            {
                /* @var Board $entity */
                $entity = $row->getEntity();
                $entity->setDefaultLocale($request->getDefaultLocale());
                if(!is_null($entity)){
                    if($cache->contains('grid_row_id_'.$entity->getId())){
                        $row->setClass('hidden');
                    }else{
                        $cache->save('grid_row_id_'.$entity->getId(), true);
                        $row->setField('translations.name', $entity->getNameTranslations());
                        $row->setField('translations.description', $entity->getDescriptionTranslations());
                    }
                }
                return $row;
            }
        );

        $grid = $this->get('grid')->setSource($source);
        $gridAction = $this->get('grid_action');

        $actionColumn = new ActionsColumn("actions", 'actions');
        $rowAction[] = $gridAction->showAction(
            'ojs_journal_board_show',
            ['id'],
            null,
            [
                'icon' => 'users',
                'title' => 'add.user'
            ]
        );
        if ($this->isGranted('EDIT', $journal, 'boards')) {
            $rowAction[] = $gridAction->editAction('ojs_journal_board_edit', ['id']);
            $rowAction[] = $gridAction->deleteAction(
                'ojs_journal_board_delete',
                ['id']
            );
        }
        $actionColumn->setRowActions($rowAction);
        $grid->addColumn($actionColumn);

        $listEvent = new ListEvent();
        $listEvent->setGrid($grid);
        $eventDispatcher->dispatch(BoardEvents::LISTED, $listEvent);
        $grid = $listEvent->getGrid();

        return $grid->getGridResponse('OjsJournalBundle:Board:index.html.twig');
    }

    /**
     * Creates a new Board entity.
     *
     * @param  Request $request
     * @return RedirectResponse|Response
     */
    public function createAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $eventDispatcher = $this->get('event_dispatcher');

        $journal = $this->get('ojs.journal_service')->getSelectedJournal();
        if (!$this->isGranted('CREATE', $journal, 'boards')) {
            throw new AccessDeniedException("You not authorized for create this journal's boards!");
        }

        $entity = new Board();
        $form = $this->createCreateForm($entity);
        $form->handleRequest($request);

        if ($form->isValid()) {
            $entity->setJournal($journal);

            $event = new JournalItemEvent($entity);
            $eventDispatcher->dispatch(BoardEvents::PRE_CREATE, $event);

            $em->persist($event->getItem());
            $em->flush();

            $event = new JournalItemEvent($event->getItem());
            $eventDispatcher->dispatch(BoardEvents::POST_CREATE, $event);

            if ($event->getResponse()) {
                return $event->getResponse();
            }

            $this->successFlashBag('successful.create');

            return $this->redirectToRoute(
                'ojs_journal_board_show',
                ['id' => $entity->getId()]
            );
        }

        return $this->render(
            'OjsJournalBundle:Board:new.html.twig',
            array(
                'entity' => $entity,
                'form' => $form->createView(),
            )
        );
    }

    /**
     * Creates a form to create a Board entity.
     *
     * @param  Board   $entity  The entity
     * @return Form    The form
     */
    private function createCreateForm(Board $entity)
    {
        $form = $this->createForm(
            new BoardType(),
            $entity,
            array(
                'action' => $this->generateUrl('ojs_journal_board_create'),
                'method' => 'POST',
            )
        );

        $form->add('submit', 'submit', array('label' => 'Create'));

        return $form;
    }

    /**
     * Displays a form to create a new Board entity.
     *
     */
    public function newAction()
    {
        $journal = $this->get('ojs.journal_service')->getSelectedJournal();
        if (!$this->isGranted('CREATE', $journal, 'boards')) {
            throw new AccessDeniedException("You not authorized for create this journal's boards!");
        }
        $entity = new Board();
        $form = $this->createCreateForm($entity);

        return $this->render(
            'OjsJournalBundle:Board:new.html.twig',
            array(
                'entity' => $entity,
                'form' => $form->createView(),
            )
        );
    }

    /**
     * Finds and displays a board and it's details.
     *  This page is also an arrangement page for a board.
     * In this page journal manager can :
     *              - list members
     *              - add members to a board
     *              - change orders of the members
     *
     * @param  Board    $board
     * @return Response
     */
    public function showAction(Board $board)
    {
        $journal = $this->get('ojs.journal_service')->getSelectedJournal();
        if (!$this->isGranted('VIEW', $journal, 'boards')) {
            throw new AccessDeniedException("You not authorized for view this journal's boards!");
        }

        $boardMember = new BoardMember();
        $addMemberForm = $this->createAddMemberForm($boardMember, $board);

        $token = $this
            ->get('security.csrf.token_manager')
            ->refreshToken('ojs_journal_board'.$board->getId());

        $source = new Entity('OjsJournalBundle:BoardMember');
        $alias = $source->getTableAlias();
        $source->manipulateQuery(
            function (QueryBuilder $query) use ($alias, $board) {
                $query
                    ->andWhere($alias.'.board = :board')
                    ->setParameter('board', $board);
            }
        );
        $membersGrid = $this->get('grid')->setSource($source);

        $gridAction = $this->get('grid_action');

        $actionColumn = new ActionsColumn("actions", 'actions');
        $rowAction = [];
        if ($this->isGranted('EDIT', $journal, 'boards')) {
            $rowAction[] = $gridAction->deleteAction(
                'ojs_journal_board_member_remove',
                ['id', 'boardId' => $board->getId()]
            );
        }
        $actionColumn->setRowActions($rowAction);
        $membersGrid->addColumn($actionColumn);

        return $membersGrid->getGridResponse(
            'OjsJournalBundle:Board:show.html.twig',
            array(
                'membersGrid' => $membersGrid,
                'entity' => $board,
                'token' => $token,
                'addMemberForm' => $addMemberForm->createView()
            )
        );
    }

    /**
     * Creates a form to add Member to Board entity.
     *
     * @param BoardMember $entity
     * @param Board $board
     * @return Form
     */
    private function createAddMemberForm(BoardMember $entity, Board $board)
    {
        $form = $this->createForm(
            new BoardMemberType(),
            $entity,
            array(
                'action' => $this->generateUrl(
                    'ojs_journal_board_member_add',
                    array('boardId' => $board->getId())
                ),
                'method' => 'PUT',
            )
        );

        return $form;
    }

    /**
     * Displays a form to edit an existing Board entity.
     *
     *
     * @param  Board    $board
     * @return Response
     */
    public function editAction(Board $board)
    {
        $journal = $this->get('ojs.journal_service')->getSelectedJournal();
        if (!$this->isGranted('EDIT', $journal, 'boards')) {
            throw new AccessDeniedException("You not authorized for edit this journal's boards!");
        }

        $editForm = $this->createEditForm($board);

        return $this->render(
            'OjsJournalBundle:Board:edit.html.twig',
            array(
                'entity' => $board,
                'edit_form' => $editForm->createView(),
            )
        );
    }

    /**
     * Creates a form to edit a Board entity.
     *
     * @param Board $entity The entity
     *
     * @return Form The form
     */
    private function createEditForm(Board $entity)
    {
        $form = $this->createForm(
            new BoardType(),
            $entity,
            array(
                'action' => $this->generateUrl(
                    'ojs_journal_board_update',
                    array('id' => $entity->getId())
                ),
                'method' => 'PUT',
            )
        );

        return $form;
    }

    /**
     * Edits an existing Board entity.
     *
     * @param  Request                   $request
     * @param  Board                     $board
     * @return RedirectResponse|Response
     */
    public function updateAction(Request $request, Board $entity)
    {
        $journal = $this->get('ojs.journal_service')->getSelectedJournal();
        $eventDispatcher = $this->get('event_dispatcher');

        if (!$this->isGranted('EDIT', $journal, 'boards')) {
            throw new AccessDeniedException("You not authorized for edit this journal's boards!");
        }
        $em = $this->getDoctrine()->getManager();

        $editForm = $this->createEditForm($entity);
        $editForm->handleRequest($request);

        if ($editForm->isValid()) {

            $event = new JournalItemEvent($entity);
            $eventDispatcher->dispatch(BoardEvents::PRE_UPDATE, $event);
            $em->persist($event->getItem());
            $em->flush();

            $event = new JournalItemEvent($event->getItem());
            $eventDispatcher->dispatch(BoardEvents::POST_UPDATE, $event);

            if ($event->getResponse()) {
                return $event->getResponse();
            }

            $this->successFlashBag('successful.update');

            return $this->redirectToRoute(
                'ojs_journal_board_edit',
                ['id' => $entity->getId()]
            );
        }

        return $this->render(
            'OjsJournalBundle:Board:edit.html.twig',
            array(
                'entity' => $entity,
                'edit_form' => $editForm->createView(),
            )
        );
    }

    /**
     * Deletes a Board entity.
     *
     * @param  Request          $request
     * @param  Board            $board
     * @return RedirectResponse
     */
    public function deleteAction(Request $request, Board $entity)
    {
        $journal = $this->get('ojs.journal_service')->getSelectedJournal();
        $eventDispatcher = $this->get('event_dispatcher');

        if (!$this->isGranted('DELETE', $journal, 'boards')) {
            throw new AccessDeniedException("You not authorized for delete this journal's boards!");
        }

        $em = $this->getDoctrine()->getManager();

        $csrf = $this->get('security.csrf.token_manager');
        $token = $csrf->getToken('ojs_journal_board'.$entity->getId());
        if ($token != $request->get('_token')) {
            throw new TokenNotFoundException("Token Not Found!");
        }
        $this->get('ojs_core.delete.service')->check($entity);

        $event = new JournalItemEvent($entity);
        $eventDispatcher->dispatch(BoardEvents::PRE_DELETE, $event);

        $em->remove($entity);
        $em->flush();

        $event = new JournalEvent($journal);
        $eventDispatcher->dispatch(BoardEvents::POST_DELETE, $event);

        if ($event->getResponse()) {
            return $event->getResponse();
        }

        $this->successFlashBag('successful.remove');

        return $this->redirectToRoute('ojs_journal_board_index');
    }

    /**
     *  add posted user id  as board member with given board id
     * @param  Request          $request
     * @param  $boardId
     * @return RedirectResponse
     */
    public function addMemberAction(Request $request, $boardId)
    {
        $journal = $this->get('ojs.journal_service')->getSelectedJournal();
        if (!$this->isGranted('EDIT', $journal, 'boards')) {
            throw new AccessDeniedException("You not authorized for edit this journal's board!");
        }
        $em = $this->getDoctrine()->getManager();
        $board = $em->getRepository('OjsJournalBundle:Board')->find($boardId);

        $boardMember = new BoardMember();
        $addMemberForm = $this->createAddMemberForm($boardMember, $board);
        $addMemberForm->handleRequest($request);

        if ($addMemberForm->isValid()) {
            $boardMember->setBoard($board);
            $em->persist($boardMember);
            $em->flush();
            $this->successFlashBag('successful.create');
        }

        return $this->redirectToRoute(
            'ojs_journal_board_show',
            ['id' => $board->getId()]
        );
    }

    /**
     * @param  $boardId
     * @param  $id
     * @return RedirectResponse
     */
    public function removeMemberAction($boardId, $id)
    {
        $journal = $this->get('ojs.journal_service')->getSelectedJournal();
        if (!$this->isGranted('EDIT', $journal, 'boards')) {
            throw new AccessDeniedException("You not authorized for edit this journal's board!");
        }
        $em = $this->getDoctrine()->getManager();

        $boardMember = $em->getRepository('OjsJournalBundle:BoardMember')->find($id);
        $this->throw404IfNotFound($boardMember);
        $this->get('ojs_core.delete.service')->check($boardMember);
        $em->remove($boardMember);
        $em->flush();

        return $this->redirectToRoute(
            'ojs_journal_board_show',
            ['id' => $boardId]
        );
    }

    /**
     * @return RedirectResponse
     */
    public function otoGenerateAction()
    {
        $journal = $this->get('ojs.journal_service')->getSelectedJournal();
        if (!$this->isGranted('EDIT', $journal, 'boards')) {
            throw new AccessDeniedException("You not authorized for edit this journal's board!");
        }
        $em = $this->getDoctrine()->getManager();
        $translator = $this->get('translator');

        $getEditorUsers = $em->getRepository('OjsUserBundle:User')->findUsersByJournalRole(
            ['ROLE_EDITOR']
        );

        $board = new Board();
        $board->setJournal($journal);
        foreach($this->getParameter('locale_support') as $localeCode){
            $board
                ->setCurrentLocale($localeCode)
                ->setName($translator->trans('board', [], null, $localeCode))
                ->setDescription($translator->trans('board', [], null, $localeCode))
                ;
        }
        $counter = 1;
        foreach($getEditorUsers as $user){
            $boardMember = new BoardMember();
            $boardMember
                ->setBoard($board)
                ->setUser($user)
                ->setSeq($counter);
            $counter = $counter+1;
            $board->addBoardMember($boardMember);
            $em->persist($boardMember);
        }
        $em->persist($board);
        $em->flush();

        $this->successFlashBag('successfully.created');
        return $this->redirectToRoute('ojs_journal_board_index');
    }
}
