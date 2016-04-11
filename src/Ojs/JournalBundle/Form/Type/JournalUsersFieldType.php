<?php

namespace Ojs\JournalBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 *
 * Class JournalUsersFieldType
 * @package Ojs\JournalBundle\Form\Type
 */
class JournalUsersFieldType extends AbstractType
{
    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(
            array(
                'remote_route' => 'ojs_journal_user_search_based_journal',
                'multiple' => true,
                'class' => 'OjsUserBundle:User'
            )
        );
    }

    public function getName()
    {
        return 'journal_users_type';
    }

    public function getParent()
    {
        return 'tetranz_select2entity';
    }
}
