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
                    Inflector::camelize($entityPathConfig['entity']).'.id' => $id,
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
            if(isset($config['redirectionAction'])) {
                $redirectUrl = $this->getActionUrl($entityPathConfig, $config['redirectionAction'], $data, true);
            } else {
                $redirectUrl = $this->getActionUrl($entityPathConfig, 'index', $data);
                if ($form->has('saveAndStay') && $form->get('saveAndStay')->isClicked()) {
                    $redirectUrl = $this->getActionUrl($entityPathConfig, 'update', $data);
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

        } else {

            $form->setData($data);
        }

        $form = $form->createView();
        $renderVars['form'] = $form;
        $renderVars['formFields'] = $formFields;

        if (!$this->modal) {

            $renderVars['central'] = $config['central'];

            foreach ($config['column']['panelZones'] as $key => $panelZone) {

                $panelZone['form'] = $form;
                $panelZone['formFields'] = $this->getFormFields($panelZone['fields'], $entityPathConfig);

                unset($panelZone['fields']);

                foreach ($panelZone['footerListFormButtons'] as $field => $footerListFormButton) {

                    $footerListFormButton = array_merge($footerListFormButton, $config['formFields'][$field]);
                    $footerListFormButton['form'] = $form;

                    $panelZone['footerListFormButtons'][$field] = $footerListFormButton;
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
        }
        if (isset($config['view'])) {
            $view = $config['view'];
        }

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
}
