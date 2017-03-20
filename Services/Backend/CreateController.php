<?php

namespace WH\BackendBundle\Services\Backend;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WH\BackendBundle\Controller\Backend\BaseController;
use WH\BackendBundle\Controller\Backend\BaseControllerInterface;

/**
 * Class CreateController
 *
 * @package WH\BackendBundle\Services\Backend
 */
class CreateController extends BaseController implements BaseControllerInterface
{

	public $container;

	public $modal = false;

	public $renderVars;

	public $config;
	public $globalConfig;

	public $entityPathConfig;
	public $request;
	public $arguments;

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
	 * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
	 */
	public function create($entityPathConfig, Request $request, $arguments = array())
	{
		$this->setTranslateDomain($entityPathConfig);

		$this->entityPathConfig = $entityPathConfig;
		$this->request = $request;
		$this->arguments = $arguments;

		$this->renderVars = array();

		$this->config = $this->getConfig($entityPathConfig, 'create');
		$this->globalConfig = $this->getGlobalConfig($entityPathConfig);

		$this->renderVars['globalConfig'] = $this->globalConfig;

		$form = $this->getCreateForm();

		$form->handleRequest($request);

		if ($form->isSubmitted()) {
			return $this->handleFormSubmission($form);
		}

		$this->renderVars['title'] = $this->backendTranslator->trans($this->config['title']);

		$view = '@WHBackendTemplate/BackendTemplate/View/modal.html.twig';
		if (isset($config['view'])) {
			$view = $config['view'];
		}

		return $this->container->get('templating')->renderResponse(
			$view,
			$this->renderVars
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

	/**
	 * @return mixed|\Symfony\Component\Form\FormInterface
	 */
	public function getCreateForm()
	{
		$formFields = $this->getFormFields($this->config['formFields'], $this->entityPathConfig);
		$footerFormFields = $this->config['footerFormFields'];

		$form = $this->getEntityForm($formFields, $this->entityPathConfig, $this->getData());

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

		$this->renderVars['form'] = $form->createView();
		$this->renderVars['formAction'] = $this->getActionUrl($this->entityPathConfig, 'create', $this->arguments);
		$this->renderVars['formFields'] = $formFields;
		$this->renderVars['footerFormFields'] = $footerFormFields;

		return $form;
	}

	/**
	 * @return object
	 */
	public function getData()
	{
		$entityClass = new \ReflectionClass($this->getEntityPath($this->entityPathConfig));
		$data = $entityClass->newInstanceArgs();

		foreach ($this->arguments as $argument => $value) {
			$argument = explode('.', $argument);

			$argumentEntityRepositoryName = '';
			if ($this->entityPathConfig['bundlePrefix'] != '') {
				$argumentEntityRepositoryName .= $this->entityPathConfig['bundlePrefix'];
			}
			$argumentEntityRepositoryName .= $this->entityPathConfig['bundle'] . ':' . ucfirst($argument[0]);

			if (isset($this->globalConfig['repositories'][$argument[0]])) {
				$argumentEntityRepositoryName = $this->globalConfig['repositories'][$argument[0]];
			}

			$argumentValue = $this->container->get('doctrine')->getRepository($argumentEntityRepositoryName)->get(
				'one',
				array(
					'conditions' => array(
						$argument[0] . '.' . $argument[1] => $value,
					),
				)
			);
			$data->{'set' . ucfirst($argument[0])}($argumentValue);
		}

		return $data;
	}

	/**
	 * @param Form $form
	 *
	 * @return JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse
	 */
	public function handleFormSubmission(Form $form)
	{
		$data = $form->getData();
		$this->saveEntity($data);

		return $this->redirectAfterSave($data);
	}

	/**
	 * @param $data
	 */
	public function saveEntity($data)
	{
		$em = $this->container->get('doctrine')->getManager();

		$em->persist($data);
		$em->flush();
	}

	/**
	 * @param $data
	 *
	 * @return JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse
	 */
	public function redirectAfterSave($data)
	{
		if (isset($config['redirectionAction'])) {
			$redirectUrl = $this->getActionUrl($this->entityPathConfig, $config['redirectionAction'], $data, true);
		} else {
			$redirectUrl = $this->getActionUrl($this->entityPathConfig, 'index', $data, true);
			if ($this->request->query->get('submitButton') && $this->request->query->get(
					'submitButton'
				) == 'createEdit'
			) {
				$redirectUrl = $this->getActionUrl($this->entityPathConfig, 'update', $data, true);
			}
		}

		if ($this->request->isXmlHttpRequest()) {

			return new JsonResponse(
				array(
					'success'  => true,
					'redirect' => $redirectUrl,
				)
			);
		}

		return $this->redirect($redirectUrl);
	}

}
