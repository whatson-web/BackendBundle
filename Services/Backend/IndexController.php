<?php

namespace WH\BackendBundle\Services\Backend;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WH\BackendBundle\Controller\Backend\BaseController;
use WH\BackendBundle\Controller\Backend\BaseControllerInterface;
use WH\LibBundle\Utils\Inflector;

/**
 * Class IndexController
 *
 * @package WH\BackendBundle\Services\Backend
 */
class IndexController extends BaseController implements BaseControllerInterface
{

    protected $container;

    private $search = false;
    private $tree = false;

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
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function index($entityPathConfig, Request $request, $arguments = array())
    {

        $renderVars = array();
        $conditions = array();

        $config = $this->getConfig($entityPathConfig, 'index');
        $globalConfig = $this->getGlobalConfig($entityPathConfig);

        $renderVars['globalConfig'] = $globalConfig;

        $urlData = $arguments;

        $renderVars['title'] = $config['title'];

        $renderVars['breadcrumb'] = $this->getBreadcrumb(
            $config['breadcrumb'],
            $entityPathConfig,
            $urlData
        );

        $tablePanelProperties = $config['tablePanelProperties'];
        if (isset($tablePanelProperties['headerListButtons'])) {

            foreach ($tablePanelProperties['headerListButtons'] as &$headerListButton) {

                $headerListButton['href'] = $this->getActionUrl(
                    $entityPathConfig,
                    $headerListButton['action'],
                    $urlData
                );
            }
        }

        foreach ($tablePanelProperties['tableFields'] as $entityFieldName => $tableField) {
	        if (!empty($globalConfig['formFields'][$entityFieldName])) {
		        $entityFieldGlobalConfig = $globalConfig['formFields'][$entityFieldName];
		        if (!empty($entityFieldGlobalConfig['label'])) {
			        $tablePanelProperties['tableFields'][$entityFieldName]['label'] = $entityFieldGlobalConfig['label'];
		        }
	        }
        }

        if (isset($tablePanelProperties['tableFields']['buttons'])) {

            $tablePanelProperties['tableFields']['buttons'] = $this->transformActionIntoRoute(
                $tablePanelProperties['tableFields']['buttons'],
                $entityPathConfig
            );
        }
        $renderVars['tablePanelProperties'] = $tablePanelProperties;

        if ($this->search) {

            $formFields = $config['formPanelProperties']['formFields'];
            $renderVars['formPanelProperties'] = $config['formPanelProperties'];

            $form = $this->getForm($formFields);

            $form->handleRequest($request);

            if ($form->isSubmitted()) {

                $data = $request->request->get($form->getName());
                $this->container->get('session')->set($this->getSlug($entityPathConfig).'search', $data);

                return $this->redirect($this->getActionUrl($entityPathConfig, 'index'));
            }

            $data = $this->container->get('session')->get($this->getSlug($entityPathConfig).'search');

            $form->setData($data);

            $renderVars['formPanelProperties']['form'] = $form->createView();

            $conditions = $this->getConditionsFromData(
                Inflector::camelize($entityPathConfig['entity']),
                $formFields,
                $data
            );
        }

        if ($this->tree) {

            foreach ($arguments as $condition => $value) {
                $conditions[$condition] = $value;
            }
        }

        $entityRepository = $this->get('doctrine')->getRepository($this->getRepositoryName($entityPathConfig));

        if ($this->tree) {

            $entities = $entityRepository->get(
                'all',
                array(
                    'conditions' => $conditions,
                )
            );
            $renderVars['tablePanelProperties']['entities'] = $entities;

            $renderVars['entityPathConfig'] = $entityPathConfig;

            $renderVars['urlData'] = $urlData;
            $renderVars['orderUrl'] = $this->getActionUrl($entityPathConfig, 'order', $urlData);

        } else {

            $paginationPage = 1;
            if ($request->query->get('page')) {
                $paginationPage = $request->query->get('page');
            }

            $paginationLimit = 25;

            $entities = $entityRepository->get(
                'paginate',
                array(
                    'paginate'   => array(
                        'page'  => $paginationPage,
                        'limit' => $paginationLimit,
                    ),
                    'conditions' => $conditions,
                )
            );
            $renderVars['tablePanelProperties']['entities'] = $entities;

            $pagination = $entityRepository->get(
                'pagination',
                array(
                    'paginate'   => array(
                        'page'  => $paginationPage,
                        'limit' => $paginationLimit,
                    ),
                    'conditions' => $conditions,
                )
            );
            $pagination['url'] = $this->getActionUrl($entityPathConfig, 'index', $urlData);
            $renderVars['pagination'] = $pagination;
        }

        if ($this->tree) {
            $renderVars['tree'] = true;
        }

        return $this->container->get('templating')->renderResponse(
            '@WHBackendTemplate/BackendTemplate/View/index.html.twig',
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

        if (isset($config['search']) && $config['search'] == 'true') {

            $this->validConfigSearch($config);
            $this->search = true;
        }

        if (isset($config['tree']) && $config['tree'] == 'true') {

            $this->validConfigTree($config);
            $this->tree = true;
        }

        if (!isset($config['tablePanelProperties'])) {

            throw new NotFoundHttpException(
                'Le fichier de configuration ne contient pas le champ "tablePanelProperties"'
            );
        }

        if (!isset($config['tablePanelProperties']['tableFields'])) {

            throw new NotFoundHttpException(
                'Le champ "tableFields" fichier de configuration n\'est pas présent sous "tablePanelProperties"'
            );
        }

        return true;
    }

    /**
     * @param $config
     *
     * @return bool
     */
    public function validConfigSearch($config)
    {

        if (!isset($config['formPanelProperties'])) {

            throw new NotFoundHttpException(
                'Le fichier de configuration ne contient pas le champ "formPanelProperties"'
            );
        }

        if (!isset($config['formPanelProperties']['formFields'])) {

            throw new NotFoundHttpException(
                'Le champ "formFields" fichier de configuration n\'est pas présent sous "formPanelProperties"'
            );
        }
    }

    /**
     * @param $config
     *
     * @return bool
     */
    public function validConfigTree($config)
    {

    }

    /**
     * @param $formFields
     * @param $data
     *
     * @return array
     */
    private function getConditionsFromData($entity, $formFields, $data)
    {

        $conditions = array();

        foreach ($formFields as $formField => $properties) {

            if (!isset($data[$formField])) {
                continue;
            }

            $value = $data[$formField];

            if (!$value) {
                continue;
            }

            $defaultExpression = $entity.'.'.$formField;
            if (!empty($properties['conditionField'])) {

                $defaultExpression = $entity.'.'.$properties['conditionField'];
            }

            if (!empty($properties['conditionType'])) {

                switch ($properties['conditionType']) {

                    case 'inferior':
                        $defaultExpression .= ' <';
                        break;

                    case 'inferiorOrEqual':
                        $defaultExpression .= ' <=';
                        break;

                    case 'superior':
                        $defaultExpression .= ' >';
                        break;

                    case 'superiorOrEqual':
                        $defaultExpression .= ' >=';
                        break;

                    case 'like':
                        $defaultExpression .= ' LIKE';
                        $value = '%'.$value.'%';
                        break;

                    case 'joinedEntity':
                        $defaultExpression = $formField.'.id';
                        break;

                }
            }

            $conditions[$defaultExpression] = $value;
        }

        return $conditions;
    }
}
