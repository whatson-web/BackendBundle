<?php

namespace WH\BackendBundle\Form;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Class EntryType
 *
 * @package WH\BackendBundle\Form
 */
class EntryType extends AbstractType
{
    private $container;

    /**
     * EntryType constructor.
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @param FormBuilderInterface $builder
     * @param array                $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $this->container->get('bk.wh.back.base_controller')->addFormFieldsToForm(
            $builder,
            $options['collectionOptions']['fields'],
            $options['collectionOptions']['entityPathConfig']
        );
    }

    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(
            array(
                'collectionOptions' => array(
                    'fields'           => array(),
                    'entityPathConfig' => array(),
                ),
            )
        );
    }
}