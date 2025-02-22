<?php

namespace Ojs\AdminBundle\Form\Type;

use Ojs\CoreBundle\Params\PublisherStatuses;
use Ojs\JournalBundle\Entity\PublisherRepository;
use Ojs\JournalBundle\Entity\SubjectRepository;
use Ojs\JournalBundle\Form\Type\ApplicationJournalContactType;
use Ojs\JournalBundle\Form\Type\JournalApplicationUploadFileType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class JournalApplicationType extends AbstractType
{

    /**
     * @param FormBuilderInterface $builder
     * @param array                $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('title', null, [
                'label' => 'journal.full.title',
                'required' => true,
                'constraints' => new NotBlank()
            ])
            ->add('subtitle', null, [
                'label' => 'journal.subtitle',
                'required' => false
            ])
            ->add('title_abbr', null, [
                'label' => 'journal.titleAbbr',
                'required' => false
            ])
            ->add('titleTransliterated', null, ['label' => 'journal.titleTransliterated', 'attr' => ['class' => 'validate[required]']])
            ->add('domain', null, ['label' => 'journal.domain', 'attr' => ['class' => 'validate[required]']])
            ->add(
                'country',
                'entity',
                array(
                    'class' => 'BulutYazilimLocationBundle:Country',
                    'label' => 'journal.country',
                    'attr' => ['class' => 'select2-element validate[required]'],
                )
            )
            ->add('issn', null, ['label' => 'journal.issn', 'attr' => ['class' => 'validate[required] maskissn']])
            ->add('eissn', null, ['label' => 'journal.eissn', 'attr' => ['class' => 'validate[required] maskissn']])
            ->add(
                'founded',
                'collot_datetime',
                array(
                    'label' => 'journal.founded',
                    'date_format' => 'yyyy',
                    'widget' => 'single_text',
                    'pickerOptions' => [
                        'format' => 'yyyy',
                        'startView' => 'decade',
                        'minView' => 'decade',
                        'todayBtn' => 'true',
                        'todayHighlight' => 'true',
                        'autoclose' => 'true',
                    ],
                )
            )
            ->add(
                'accessModal',
                'choice',
                [
                    'label' => 'journal.access.modal',
                    'choices' => [
                        0 => 'open.access',
                        1 => 'access.with.subscription',
                    ],
                ]
            )
            ->add(
                'mandatoryLang',
                'entity',
                [
                    'label' => 'Mandatory Lang',
                    'class' => 'Ojs\JournalBundle\Entity\Lang',
                    'attr' => [
                        'class' => 'select2-element ',
                    ]
                ]
            )
            ->add(
                'periods',
                'entity',
                array(
                    'label' => 'journal.period',
                    'class' => 'Ojs\JournalBundle\Entity\Period',
                    'required' => true,
                    'multiple' => true,
                    'expanded' => false,
                    'attr' => [
                        'class' => 'select2-element validate[required]',
                    ]
                )
            )
            ->add('tags', 'tags', [
                'attr' => [
                    'class' => 'validate[required]',
                    'label' => 'journal.tags'
                    ]
                ]
            )
            ->add('journalIndexesBag', 'tags', [
                    'placeholder' => 'comma.seperated.index.list',
                    'label' => 'journalindex.plural',
                    'attr' => [
                        'class' => 'validate[required]',
                    ]
                ]
            )
            ->add('url', 'url', [
                'label' => 'journal.url',
                 'required' => false
                ]
            )
            ->add(
                'publisher',
                'entity',
                array(
                    'class' => 'OjsJournalBundle:Publisher',
                    'query_builder' => function(PublisherRepository $er) {
                        return $er->createQueryBuilder('publisher')
                            ->andWhere('publisher.status = :status')
                            ->andWhere('publisher.verified = :verified')
                            ->setParameter('status', PublisherStatuses::STATUS_COMPLETE)
                            ->setParameter('verified', true)
                            ;
                    },
                    'attr' => ['class' => 'select2-element validate[required]'],
                    'label' => 'journal.publisher',
                    'required' => true
                )
            )
            ->add(
                'languages',
                'entity',
                array(
                    'class' => 'OjsJournalBundle:Lang',
                    'multiple' => true,
                    'label' => 'journal.supported_languages',
                    'attr' => ['class' => 'select2-element validate[required]'],
                )
            )
            ->add(
                'subjects',
                'entity',
                array(
                    'class' => 'OjsJournalBundle:Subject',
                    'multiple' => true,
                    'required' => true,
                    'property' => 'indentedSubject',
                    'label' => 'journal.subjects',
                    'attr' => [
                        'size' => '10',
                    ],
                    'query_builder' => function(SubjectRepository $er) {
                        return $er->getChildrenQueryBuilder(null, null, 'root', 'asc', false);
                    }
                )
            )
            ->add('header', 'jb_crop_image_ajax', array(
                'endpoint' => 'journal',
                'label' => 'Header Image',
                'img_width' => 960,
                'img_height' => 200,
                'crop_options' => array(
                    'aspect-ratio' => 960 / 200,
                    'maxSize' => "[960, 200]"
                )
            ))
            ->add('image', 'jb_crop_image_ajax', array(
                'endpoint' => 'journal',
                'label' => 'Cover Image',
                'img_width' => 200,
                'img_height' => 300,
                'crop_options' => array(
                    'aspect-ratio' => 200 / 300,
                    'maxSize' => "[200, 300]"
                )
            ))
            ->add('address', null, ['label' => 'journal.address', 'attr' => ['class' => 'validate[required]']])
            ->add('phone', null, ['label' => 'journal.phone', 'attr' => ['class' => 'validate[required,custom[email]]']])
            ->add('email', 'email', ['label' => 'journal.email', 'attr' => ['class' => 'validate[required,custom[email]]']])
            ->add('journalContacts', 'collection', [
                'type' => new ApplicationJournalContactType(),
                'allow_add' => true,
                'allow_delete' => true
                ]
            )
            ->add('journalApplicationUploadFiles', 'collection', array(
                    'type' => new JournalApplicationUploadFileType(),
                    'allow_add' => false,
                    'allow_delete' => false,
                    'options' => array(
                    )
                )
            )
        ;
    }

    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(
            array(
                'data_class' => 'Ojs\JournalBundle\Entity\Journal',
                'cascade_validation' => true,
                'attr' => [
                    'class' => 'form-validate',
                ],
            )
        );
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'ojs_journalbundle_journalapplication';
    }
}
