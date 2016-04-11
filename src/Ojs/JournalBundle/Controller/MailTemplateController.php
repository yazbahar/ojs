<?php

namespace Ojs\JournalBundle\Controller;

use APY\DataGridBundle\Grid\Column\ActionsColumn;
use APY\DataGridBundle\Grid\Source\Entity;
use Doctrine\ORM\Query;
use Ojs\CoreBundle\Controller\OjsController as Controller;
use Ojs\JournalBundle\Entity\MailTemplate;
use Ojs\JournalBundle\Form\Type\MailTemplateType;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * MailTemplate controller.
 *
 */
class MailTemplateController extends Controller
{

    /**
     * Lists all MailTemplate entities.
     *
     * @return Response
     */
    public function indexAction()
    {
        $journal = $this->get('ojs.journal_service')->getSelectedJournal();
        if (!$this->isGranted('VIEW', $journal, 'mailTemplate')) {
            throw new AccessDeniedException("You are not authorized for view this page!");
        }
        $source = new Entity('OjsJournalBundle:MailTemplate', 'journal');

        $grid = $this->get('grid')->setSource($source);
        $gridAction = $this->get('grid_action');

        $actionColumn = new ActionsColumn("actions", 'actions');
        $rowAction = [];

        $rowAction[] = $gridAction->showAction('ojs_journal_mail_template_show', ['id']);
        $rowAction[] = $gridAction->editAction('ojs_journal_mail_template_edit', ['id']);
        $actionColumn->setRowActions($rowAction);
        $grid->addColumn($actionColumn);

        return $grid->getGridResponse('OjsJournalBundle:MailTemplate:index.html.twig', ['grid' => $grid]);
    }

    /**
     * Finds and displays a MailTemplate entity.
     *
     * @param  integer  $id
     * @return Response
     */
    public function showAction($id)
    {
        $em = $this->getDoctrine()->getManager();
        $journal = $this->get('ojs.journal_service')->getSelectedJournal();
        if (!$this->isGranted('VIEW', $journal, 'mailTemplate')) {
            throw new AccessDeniedException("You are not authorized for view this page!");
        }
        $entity = $em->getRepository('OjsJournalBundle:MailTemplate')->find($id);
        $this->throw404IfNotFound($entity);

        $token = $this
            ->get('security.csrf.token_manager')
            ->refreshToken('ojs_journal_mail_template'.$entity->getId());

        return $this->render(
            'OjsJournalBundle:MailTemplate:show.html.twig',
            array(
                'entity' => $entity,
                'token'  => $token,
            )
        );
    }

    /**
     * Displays a form to edit an existing MailTemplate entity.
     *
     * @param  integer  $id
     * @return Response
     */
    public function editAction($id)
    {
        $mailEventsChain = $this->get('ojs_core.mail.event_chain');
        $em = $this->getDoctrine()->getManager();
        $journal = $this->get('ojs.journal_service')->getSelectedJournal();
        if (!$this->isGranted('EDIT', $journal, 'mailTemplate')) {
            throw new AccessDeniedException("You are not authorized for view this page!");
        }
        /** @var MailTemplate $entity */
        $entity = $em->getRepository('OjsJournalBundle:MailTemplate')->find($id);
        $this->throw404IfNotFound($entity);

        $editForm = $this->createEditForm($entity);

        $eventDetail = $mailEventsChain->getEventOptionsByName($entity->getType());
        $eventParamsAsString = $mailEventsChain->getEventParamsAsString($eventDetail);
        return $this->render(
            'OjsJournalBundle:MailTemplate:edit.html.twig',
            array(
                'entity' => $entity,
                'form' => $editForm->createView(),
                'eventDetail' => $eventDetail,
                'eventParamsAsString' => $eventParamsAsString,
                'defaultMailTemplate' => $this->getDefaultMailTemplate($entity),
            )
        );
    }

    /**
     * @param MailTemplate $mailTemplate
     * @return null|object|MailTemplate
     */
    private function getDefaultMailTemplate(MailTemplate $mailTemplate)
    {
        $em = $this->getDoctrine()->getManager();
        $GLOBALS['Ojs\JournalBundle\Entity\MailTemplate#journalFilter'] = false;
        return $em->getRepository('OjsJournalBundle:MailTemplate')->findOneBy([
            'type' => $mailTemplate->getType(),
            'journal' => null,
            'journalDefault' => true,
            'lang' => $this->getParameter('locale'),
        ]);
    }

    /**
     * Creates a form to edit a MailTemplate entity.
     *
     * @param MailTemplate $entity The entity
     *
     * @return Form The form
     */
    private function createEditForm(MailTemplate $entity)
    {
        $form = $this->createForm(
            new MailTemplateType(),
            $entity,
            array(
                'action' => $this->generateUrl('ojs_journal_mail_template_update', array('id' => $entity->getId())),
                'method' => 'PUT',
            )
        );

        $form->add('submit', 'submit', array('label' => 'Update'));

        return $form;
    }

    /**
     * Edits an existing MailTemplate entity.
     *
     * @param  Request                   $request
     * @param  integer                   $id
     * @return RedirectResponse|Response
     */
    public function updateAction(Request $request, $id)
    {
        $em = $this->getDoctrine()->getManager();
        $journal = $this->get('ojs.journal_service')->getSelectedJournal();
        if (!$this->isGranted('EDIT', $journal, 'mailTemplate')) {
            throw new AccessDeniedException("You are not authorized for view this page!");
        }
        /** @var MailTemplate $entity */
        $entity = $em->getRepository('OjsJournalBundle:MailTemplate')->find($id);
        $this->throw404IfNotFound($entity);

        $editForm = $this->createEditForm($entity);
        $editForm->handleRequest($request);

        if ($editForm->isValid()) {
            $em->flush();
            $this->successFlashBag('successful.update');

            return $this->redirectToRoute('ojs_journal_mail_template_edit', ['id' => $entity->getId()]);
        }

        return $this->render(
            'OjsJournalBundle:MailTemplate:edit.html.twig',
            array(
                'entity' => $entity,
                'edit_form' => $editForm->createView(),
            )
        );
    }
}
