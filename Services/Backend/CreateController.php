<?php

namespace WH\BackendBundle\Services\Backend;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WH\BackendBundle\Controller\Backend\BaseController;
use WH\BackendBundle\Controller\Backend\BaseControllerInterface;
use WH\LibBundle\Utils\Inflector;

/**
 * Class CreateController
 *
 * @package WH\BackendBundle\Services\Backend
 */
class CreateController extends BaseController implements BaseControllerInterface
{

    protected $container;

    private $modal = false;

    /**
     * SearchController constructor.
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {

        $this->container = $container;
    }

    /**
     * @param         $entityPathConfig
     * @param Request $request
     * @param array   $arguments
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function create($entityPathConfig, Request $request, $arguments = array())
    {

        $config = $this->getConfig($entityPathConfig, 'create');
        $globalConfig = $this->getGlobalConfig($entityPathConfig);

        $urlData = $arguments;

        $title = $config['title'];
        $formFields = $this->getFormFields($config['formFields'], $entityPathConfig);
        $footerFormFields = $config['footerFormFields'];

        $entityClass = new \ReflectionClass($this->getEntityPath($entityPathConfig));
        $data = $entityClass->newInstanceArgs();

        foreach ($arguments as $argument => $value) {

            $argument = explode('.', $argument);

            $argumentEntityRepositoryName = $entityPathConfig['bundlePrefix'].$entityPathConfig['bundle'].':'.Inflector::camelizeWithFirstLetterUpper(
                    $argument[0]
                );

            $argumentValue = $this->container->get('doctrine')->getRepository($argumentEntityRepositoryName)->get(
                'one',
                array(
                    'conditions' => array(
                        $argument[0].'.'.$argument[1] => $value,
                    ),
                )
            );
            $data->{'set'.Inflector::camelizeWithFirstLetterUpper($argument[0])}($argumentValue);
        }

        $form = $this->getEntityForm($formFields, $entityPathConfig, $data);

        if (isset($footerFormFields['create'])) {

            $form->add(
                'create',
                SubmitType::class,
                array(
                    'label' => 'Créer',
                )
            );
        }

        if (isset($footerFormFields['createEdit'])) {

            $form->add(
                'createEdit',
                SubmitType::class,
                array(
                    'label' => 'Créer & Editer',
                )
            );
        }

        $form->handleRequest($request);

        if ($form->isSubmitted()) {

            $data = $form->getData();

            $em = $this->container->get('doctrine')->getManager();

            $em->persist($data);
            $em->flush();

            if(isset($config['redirectionAction'])) {
                $redirectUrl = $this->getActionUrl($entityPathConfig, $config['redirectionAction'], $data, true);
            } else {
                $redirectUrl = $this->getActionUrl($entityPathConfig, 'index', $data, true);
                if ($request->query->get('submitButton') && $request->query->get('submitButton') == 'createEdit') {
                    $redirectUrl = $this->getActionUrl($entityPathConfig, 'update', $data, true);
                }
            }

            if ($request->isXmlHttpRequest()) {

                return new JsonResponse(
                    array(
                        'success'  => true,
                        'redirect' => $redirectUrl,
                    )
                );
            }

            return $this->redirect($redirectUrl);
        }

        $view = '@WHBackendTemplate/BackendTemplate/View/modal.html.twig';
        if (isset($config['view'])) {
            $view = $config['view'];
        }

        return $this->container->get('templating')->renderResponse(
            $view,
            array(
                'globalConfig'     => $globalConfig,
                'title'            => $title,
                'form'             => $form->createView(),
                'formAction'       => $this->getActionUrl($entityPathConfig, 'create', $urlData),
                'formFields'       => $formFields,
                'footerFormFields' => $footerFormFields,
            )
        );
    }

    /**
     * @param $config
     *
     * @return bool
     */
    public function validConfig($config)
    {

        if (!isset($config['title'])) {

            throw new NotFoundHttpException('Le fichier de configuration ne contient pas le champ "title"');
        }

        if (!isset($config['formFields'])) {

            throw new NotFoundHttpException('Le fichier de configuration ne contient pas le champ "formFields"');
        }

        if (isset($config['modal']) && $config['modal'] == 'false') {
            $this->modal = false;
        }

        return true;
    }
}
