<?php

namespace WH\BackendBundle\Services\Backend;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Form\Form;
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

	public $container;

	public $search = false;
	public $sortable = false;
	public $tree = false;

	public $renderVars;

	public $config;
	public $globalConfig;

	public $entityPathConfig;
	public $request;
	public $arguments;

	public $conditions;

	/**
	 * SearchController constructor.
	 *
	 * @param ContainerInterface $container
	 */
	public function __construct(ContainerInterface $container)
	{
		$this->container = $container;
		$this->backendTranslator = $this->container->get('bk.wh.back.translator');
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
		$this->setTranslateDomain($entityPathConfig);

		$this->entityPathConfig = $entityPathConfig;
		$this->request = $request;
		$this->arguments = $arguments;

		$this->config = $this->getConfig($entityPathConfig, 'index');
		$this->globalConfig = $this->getGlobalConfig($entityPathConfig);
		$this->arguments = $arguments;

		$this->renderVars['globalConfig'] = $this->globalConfig;

		$this->conditions = array();

		foreach ($arguments as $condition => $value) {
			$this->conditions[$condition] = $value;
		}

		if ($this->search) {
			$this->handleSearchForm();
		}

		if ($this->tree) {
			$this->renderVars['tree'] = true;

			if (isset($this->config['treeRootLabel'])) {
				switch ($this->config['treeRootLabel']['type']) {
					case 'entity':
						$em = $this->get('doctrine')->getManager();
						$entity = $em->getRepository($this->config['treeRootLabel']['class'])->get(
							'one',
							array(
								'conditions' => array(
									$this->config['treeRootLabel']['dataField'] => $arguments[$this->config['treeRootLabel']['dataField']],
								),
							)
						);

						if ($entity) {
							$this->renderVars['treeRootLabel'] = $this->getVariableValue(
								$this->config['treeRootLabel']['field'],
								$entity
							);
						}

						break;
				}
			}
		}

		$this->getTablePanelProperties();

		if ($this->tree) {
			$this->getEntities();
		} else {
			$this->getPagination();
		}

		if ($this->sortable) {
			$this->renderVars['orderUrl'] = $this->getActionUrl($entityPathConfig, 'order', $arguments);
		}

		$this->renderVars['title'] = $this->backendTranslator->trans($this->config['title']);

		$this->renderVars['breadcrumb'] = $this->getBreadcrumb(
			$this->config['breadcrumb'],
			$entityPathConfig,
			$arguments
		);

		$view = '@WHBackendTemplate/BackendTemplate/View/index.html.twig';
		if (isset($this->config['view'])) {
			$view = $this->config['view'];
		}

		return $this->container->get('templating')->renderResponse(
			$view,
			$this->renderVars
		);
	}

	/**
	 * @return bool
	 */
	public function validConfig($config)
	{
		$this->config = $config;

		if (isset($this->config['search']) && $this->config['search'] == 'true') {
			$this->validConfigSearch();
			$this->search = true;
		}

		if (isset($this->config['tree']) && $this->config['tree'] == 'true') {
			$this->validConfigTree();
			$this->tree = true;
		}

		if (isset($this->config['sortable']) && $this->config['sortable'] == 'true') {
			$this->sortable = true;
		}

		if (!isset($this->config['tablePanelProperties'])) {
			throw new NotFoundHttpException(
				'Le fichier de configuration ne contient pas le champ "tablePanelProperties"'
			);
		}

		if (!isset($this->config['tablePanelProperties']['tableFields'])) {
			throw new NotFoundHttpException(
				'Le champ "tableFields" fichier de configuration n\'est pas présent sous "tablePanelProperties"'
			);
		}

		return true;
	}

	/**
	 * @return bool
	 */
	public function validConfigSearch()
	{
		if (!isset($this->config['formPanelProperties'])) {
			throw new NotFoundHttpException(
				'Le fichier de configuration ne contient pas le champ "formPanelProperties"'
			);
		}

		if (!isset($this->config['formPanelProperties']['formFields'])) {
			throw new NotFoundHttpException(
				'Le champ "formFields" fichier de configuration n\'est pas présent sous "formPanelProperties"'
			);
		}

		return true;
	}

	/**
	 * @return bool
	 */
	public function validConfigTree()
	{
		return true;
	}

	/**
	 * * Gère les propriétés à envoyer à la vue pour l'affichage de la liste des entités dans le tableau
	 *
	 * @return bool
	 */
	public function getTablePanelProperties()
	{
		$tablePanelProperties = $this->config['tablePanelProperties'];

		if ($this->sortable) {
			$tablePanelProperties['sortable'] = true;
		}

		// thead
		if (isset($tablePanelProperties['headerListButtons'])) {
			foreach ($tablePanelProperties['headerListButtons'] as $key => $headerListButton) {
				$headerListButton['label'] = $this->backendTranslator->trans($headerListButton['label']);
				$headerListButton['href'] = $this->getActionUrl(
					$this->entityPathConfig,
					$headerListButton['action'],
					$this->arguments
				);
				$tablePanelProperties['headerListButtons'][$key] = $headerListButton;
			}
		}

		$tablePanelProperties['headerLabel'] = $this->backendTranslator->trans($tablePanelProperties['headerLabel']);

		// tbody
		foreach ($tablePanelProperties['tableFields'] as $entityFieldName => $tableField) {
			if (!empty($this->globalConfig['formFields'][$entityFieldName])) {
				$entityFieldGlobalConfig = $this->globalConfig['formFields'][$entityFieldName];
				if (!empty($entityFieldGlobalConfig['label'])) {
					$tableField['label'] = $entityFieldGlobalConfig['label'];
				}
			}
			if (isset($tableField['label'])) {
				$tableField['label'] = $this->backendTranslator->trans($tableField['label']);
			}
			if (is_array($tableField) && key_exists('multipleFields', $tableField)) {
				foreach ($tableField as $key => $multipleField) {
					if (isset($multipleField['confirm'])) {
						$multipleField['confirm'] = $this->backendTranslator->trans($multipleField['confirm']);
					}
					$tableField[$key] = $multipleField;
				}
			}
			$tablePanelProperties['tableFields'][$entityFieldName] = $tableField;
		}

		if (isset($tablePanelProperties['tableFields']['buttons'])) {
			$tablePanelProperties['tableFields']['buttons'] = $this->transformActionIntoRoute(
				$tablePanelProperties['tableFields']['buttons'],
				$this->entityPathConfig
			);
		}

		$this->renderVars['tablePanelProperties'] = $tablePanelProperties;

		return true;
	}

	/**
	 * @return bool
	 */
	public function handleSearchForm()
	{
		$formFields = $this->config['formPanelProperties']['formFields'];

		$form = $this->getForm($formFields);

		$this->handleSearchFormSubmission($form);

		// Data initialisation
		$data = $this->container->get('session')->get($this->getSlug($this->entityPathConfig) . 'search');
		$form->setData($data);

		// Get conditions from data
		$conditions = $this->getConditionsFromData(
			Inflector::camelize($this->entityPathConfig['entity']),
			$formFields,
			$data
		);
		$this->conditions = array_merge(
			$this->conditions,
			$conditions
		);

		// Variables pour l'affichage du formulaire dans la vue
		$this->getSearchFormViewVariables($form);

		return true;
	}

	/**
	 * @param Form $form
	 *
	 * @return bool|\Symfony\Component\HttpFoundation\RedirectResponse
	 */
	public function handleSearchFormSubmission(Form $form)
	{
		$form->handleRequest($this->request);

		if ($form->isSubmitted()) {
			$data = $this->request->request->get($form->getName());
			$this->container->get('session')->set($this->getSlug($this->entityPathConfig) . 'search', $data);

			return $this->redirect(
				$this->getActionUrl($this->entityPathConfig, 'index')
			);
		}

		return true;
	}

	/**
	 * @param Form $form
	 *
	 * @return bool
	 */
	public function getSearchFormViewVariables(Form $form)
	{
		$this->config['formPanelProperties']['headerLabel'] = $this->backendTranslator->trans(
			$this->config['formPanelProperties']['headerLabel']
		);
		$this->renderVars['formPanelProperties'] = $this->config['formPanelProperties'];

		$this->renderVars['formPanelProperties']['form'] = $form->createView();

		if (isset($this->renderVars['formPanelProperties']['footerListButtons'])) {
			foreach ($this->renderVars['formPanelProperties']['footerListButtons'] as $key => $button) {
				if (isset($button['label'])) {
					$this->renderVars['formPanelProperties']['footerListButtons'][$key]['label'] = $this->backendTranslator->trans(
						$button['label']
					);
				}
			}
		}

		return true;
	}

	/**
	 * Gère la liste des entités lorsqu'il n'y a pas de pagination
	 *
	 * @return bool
	 */
	public function getEntities()
	{
		$entityRepository = $this->get('doctrine')->getRepository(
			$this->getRepositoryName($this->entityPathConfig)
		);

		$entities = $entityRepository->get(
			'all',
			array(
				'conditions' => $this->conditions,
			)
		);
		$this->renderVars['tablePanelProperties']['entities'] = $entities;

		$this->renderVars['entityPathConfig'] = $this->entityPathConfig;

		$this->renderVars['urlData'] = $this->arguments;
		$this->renderVars['orderUrl'] = $this->getActionUrl($this->entityPathConfig, 'order', $this->arguments);

		return true;
	}

	/**
	 * Gère la pagination
	 *
	 * @return bool
	 */
	public function getPagination()
	{
		$entityRepository = $this->get('doctrine')->getRepository(
			$this->getRepositoryName($this->entityPathConfig)
		);

		$paginationPage = 1;
		if ($this->request->query->get('page')) {
			$paginationPage = $this->request->query->get('page');
		}

		$paginationLimit = 25;

		$entities = $entityRepository->get(
			'paginate',
			array(
				'paginate'   => array(
					'page'  => $paginationPage,
					'limit' => $paginationLimit,
				),
				'conditions' => $this->conditions,
			)
		);
		$this->renderVars['tablePanelProperties']['entities'] = $entities;

		$pagination = $entityRepository->get(
			'pagination',
			array(
				'paginate'   => array(
					'page'  => $paginationPage,
					'limit' => $paginationLimit,
				),
				'conditions' => $this->conditions,
			)
		);
		$pagination['url'] = $this->getActionUrl($this->entityPathConfig, 'index', $this->arguments);
		$this->renderVars['pagination'] = $pagination;

		return true;
	}

	/**
	 * @param $entity
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

			$defaultExpression = $entity . '.' . $formField;
			if (!empty($properties['conditionField'])) {
				$defaultExpression = $entity . '.' . $properties['conditionField'];
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
						$value = '%' . $value . '%';
						break;

					case 'joinedEntity':
						$defaultExpression = $formField . '.id';
						break;
				}
			}

			$conditions[$defaultExpression] = $value;
		}

		return $conditions;
	}
}
