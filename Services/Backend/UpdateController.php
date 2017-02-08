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
 * Class UpdateController
 *
 * @package WH\BackendBundle\Services\Backend
 */
class UpdateController extends BaseController implements BaseControllerInterface
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
     * @param         $id
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function update($entityPathConfig, $id, Request $request)
    {
        $em = $this->container->get('doctrine')->getManager();

        $data = $em->getRepository($this->getRepositoryName($entityPathConfig))->get(
            'one',
            array(
                'conditions' => array(
                    Inflector::camelize($entityPathConfig['entity']) . '.id' => $id,
                ),
            )
        );

        if (!$data) {
            return $this->redirect($this->getActionUrl($entityPathConfig, 'index'));
        }

        $renderVars = array();

        $config = $this->getConfig($entityPathConfig, 'update');
        $globalConfig = $this->getGlobalConfig($entityPathConfig);

        $renderVars['globalConfig'] = $globalConfig;

        $renderVars['title'] = $config['title'];

        $formFields = $this->getFormFields($config['formFields'], $entityPathConfig);

        $form = $this->getEntityForm($formFields, $entityPathConfig, $data);

        if (!$this->modal) {
            $renderVars['breadcrumb'] = $this->getBreadcrumb(
                $config['breadcrumb'],
                $entityPathConfig,
                $data
            );
        } else {
            $footerFormFields = $config['footerFormFields'];

            foreach ($footerFormFields as $footerFormField) {
                $form->add(
                    $footerFormField['field'],
                    SubmitType::class,
                    array(
                        'label' => $footerFormField['label'],
                    )
                );
            }
        }

        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $data = $form->getData();

            $em->persist($data);
            $em->flush();

            $redirectUrl = $this->getActionUrl($entityPathConfig, 'index', $data);
            if ($form->has('saveAndStay') && $form->get('saveAndStay')->isClicked()) {
                $redirectUrl = $this->getActionUrl($entityPathConfig, 'update', $data);
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
        } else {
            $form->setData($data);
        }

        $form = $form->createView();
        $renderVars['form'] = $form;
        $renderVars['formFields'] = $formFields;

        if (!$this->modal) {
            if (!empty($config['central']['viewLink']['action'])) {
                $config['central']['viewLink']['url'] = $this->getActionUrl(
                    $entityPathConfig,
                    $config['central']['viewLink']['action'],
                    $data
                );
            }

            foreach ($config['central']['tabs'] as $tabSlug => $tabProperties) {
                if (isset($tabProperties['iframeContent'])) {
                    $config['central']['tabs'][$tabSlug]['iframeContent']['url'] = $this->getActionUrl(
                        $entityPathConfig,
                        $tabProperties['iframeContent']['action'],
                        $data
                    );
                }
            }

            $renderVars['central'] = $config['central'];

            foreach ($config['column']['panelZones'] as $key => $panelZone) {
                $panelZone['form'] = $form;
                $panelZone['formFields'] = $this->getFormFields($panelZone['fields'], $entityPathConfig);

                unset($panelZone['fields']);

                if (isset($panelZone['footerListFormButtons'])) {
                    foreach ($panelZone['footerListFormButtons'] as $field => $footerListFormButton) {
                        $footerListFormButton = array_merge($footerListFormButton, $config['formFields'][$field]);
                        $footerListFormButton['form'] = $form;

                        $panelZone['footerListFormButtons'][$field] = $footerListFormButton;
                    }
                }
                $config['column']['panelZones'][$key] = $panelZone;
            }

            $renderVars['column'] = $config['column'];
        } else {
            $renderVars['footerFormFields'] = $footerFormFields;
            $renderVars['formAction'] = $this->getActionUrl($entityPathConfig, 'update', $data);
        }

        $view = '@WHBackendTemplate/BackendTemplate/View/update.html.twig';
        if ($this->modal) {
            $view = '@WHBackendTemplate/BackendTemplate/View/modal.html.twig';
            if (isset($config['view'])) {
                $view = $config['view'];
            }
        }

        $renderVars = $this->translateRenderVars($entityPathConfig, $renderVars);

        return $this->container->get('templating')->renderResponse(
            $view,
            $renderVars
        );
    }

    /**
     * @param $config
     *
     * @return bool
     */
    public function validConfig($config)
    {
        if (isset($config['modal']) && $config['modal'] == 'true') {
            $this->validConfigPopup($config);
            $this->modal = true;
        } else {
            $this->validConfigClassic($config);
        }

        return true;
    }

    /**
     * @param $config
     *
     */
    public function validConfigPopup($config)
    {
        if (!isset($config['formFields'])) {

            throw new NotFoundHttpException('Le fichier de configuration ne contient pas le champ "formFields"');
        }
    }

    /**
     * @param $config
     *
     * @return bool
     */
    public function validConfigClassic($config)
    {
    }

    /**
     * @param $renderVars
     *
     * @return mixed
     */
    public function translateRenderVars($entityPathConfig, $renderVars)
    {
        $backendTranslator = $this->container->get('bk.wh.back.translator');
        $backendTranslator->setDomain($this->getTranslateDomain($entityPathConfig));

        $renderVars['title'] = $backendTranslator->trans($renderVars['title']);

        foreach ($renderVars['form']->children as $formSlug => $formField) {
            if (isset($formField->vars['label'])) {
                $formField->vars['label'] = $backendTranslator->trans($formField->vars['label']);
            }

            if (isset($formField->vars['choices'])) {
                foreach ($formField->vars['choices'] as $key => $choice) {
                    $formField->vars['choices'][$key]->label = $backendTranslator->trans($choice->label);
                }
            }

            $renderVars['form']->children[$formSlug] = $formField;
        }

        if (!$this->modal) {
            $breadcrumb = array();
            foreach ($renderVars['breadcrumb'] as $name => $url) {
                $breadcrumb[$backendTranslator->trans($name)] = $url;
            }
            $renderVars['breadcrumb'] = $breadcrumb;

            if (!empty($renderVars['central']['viewLink']['name'])) {
                $renderVars['central']['viewLink']['name'] = $backendTranslator->trans(
                    $renderVars['central']['viewLink']['name']
                );
            }

            foreach ($renderVars['central']['tabs'] as $tabSlug => $tabProperties) {
                $tabProperties['name'] = $backendTranslator->trans($tabProperties['name']);

                if (!empty($tabProperties['formZones'])) {
                    foreach ($tabProperties['formZones'] as $formZoneSlug => $formZone) {
                        if (isset($formZone['title'])) {
                            $tabProperties['formZones'][$formZoneSlug]['title'] = $backendTranslator->trans(
                                $formZone['title']
                            );
                        }
                    }
                }

                $renderVars['central']['tabs'][$tabSlug] = $tabProperties;
            }

            foreach ($renderVars['column']['panelZones'] as $key => $panelZone) {
                $panelZone['headerLabel'] = $backendTranslator->trans($panelZone['headerLabel']);

                if (isset($panelZone['footerListFormButtons'])) {
                    foreach ($panelZone['footerListFormButtons'] as $field => $footerListFormButton) {
                        $panelZone['footerListFormButtons'][$field]['label'] = $backendTranslator->trans(
                            $footerListFormButton['label']
                        );
                    }
                }

                $renderVars['column']['panelZones'][$key] = $panelZone;
            }
        }

        return $renderVars;
    }
}
