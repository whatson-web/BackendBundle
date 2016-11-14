<?php

namespace WH\BackendBundle\Controller\Backend;

use FM\ElfinderBundle\Form\Type\ElFinderType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Yaml\Yaml;
use WH\LibBundle\Utils\Inflector;
use WH\MediaBundle\Form\Backend\FileType;

/**
 * Class BaseController
 *
 * @package WH\BackendBundle\Controller\Backend
 */
class BaseController extends Controller implements BaseControllerInterface
{

	public $bundlePrefix = '';
	public $bundle = '';
	public $entity = '';
	public $type = 'Backend';

	/**
	 * @return array
	 */
	public function getEntityPathConfig()
	{
		return array(
			'bundlePrefix' => $this->bundlePrefix,
			'bundle'       => $this->bundle,
			'entity'       => $this->entity,
			'type'         => $this->type,
		);
	}

	/**
	 * @param $entityPathConfig
	 * @param $action
	 *
	 * @return mixed
	 */
	public function getConfig($entityPathConfig, $action)
	{
		$ymlPath = $this->getYmlFilePath(
			$entityPathConfig,
			$action
		);

		if (!file_exists($ymlPath)) {
			throw new NotFoundHttpException(
				'Le fichier de configuration n\'existe pas. Il devrait être ici : ' . $ymlPath
			);
		}

		$config = Yaml::parse(file_get_contents($ymlPath));
		if ($this->validConfig($config)) {
			return $config;
		}

		return array();
	}

	/**
	 * @param $entityPathConfig
	 *
	 * @return mixed
	 */
	public function getGlobalConfig($entityPathConfig)
	{
		$ymlPath = $this->getYmlFilePath(
			$entityPathConfig,
			'global'
		);

		if (!file_exists($ymlPath)) {
			throw new NotFoundHttpException(
				'Le fichier de configuration globale n\'existe pas. Il devrait être ici : ' . $ymlPath
			);
		}

		$config = Yaml::parse(file_get_contents($ymlPath));

		if (!isset($config['actions'])) {
			throw new NotFoundHttpException('Le fichier de configuration globale ne contient pas le champ "actions"');
		}

		return $config;
	}

	/**
	 * @param $entityPathConfig
	 *
	 * @return string
	 */
	public function getSlug($entityPathConfig)
	{
		$slug = '';
		if (!empty($entityPathConfig['bundlePrefix'])) {

			$slug .= $entityPathConfig['bundlePrefix'];
		}
		$slug .= $entityPathConfig['bundle'] . $entityPathConfig['entity'];

		$slug = Inflector::camelize($slug);

		return $slug;
	}

	/**
	 * @param $entityPathConfig
	 *
	 * @return string
	 */
	public function getEntityPath($entityPathConfig)
	{
		$entityPath = '';
		if (!empty($entityPathConfig['bundlePrefix'])) {

			$entityPath .= $entityPathConfig['bundlePrefix'];
		}
		$entityPath .= '\\' . $entityPathConfig['bundle'] . '\Entity\\' . $entityPathConfig['entity'];

		return $entityPath;
	}

	/**
	 * @param $entityPathConfig
	 *
	 * @return string
	 */
	public function getRepositoryName($entityPathConfig)
	{
		$repositoryName = '';
		if (!empty($entityPathConfig['bundlePrefix'])) {

			$repositoryName .= $entityPathConfig['bundlePrefix'];
		}
		$repositoryName .= $entityPathConfig['bundle'] . ':' . $entityPathConfig['entity'];

		return $repositoryName;
	}

	/**
	 * @param $entityPathConfig
	 * @param $slug
	 *
	 * @return string
	 */
	private function getYmlFilePath($entityPathConfig, $slug)
	{
		$rootDir = $this->get('kernel')->getRootDir();
		$path = $rootDir . '/../src/';
		if ($entityPathConfig['bundlePrefix'] != '') {
			$path .= $entityPathConfig['bundlePrefix'] . '/';
		}
		$path .= $entityPathConfig['bundle'] . '/Resources/config/' . $entityPathConfig['type'] . '/' . $entityPathConfig['entity'] . '/' . $slug . '.yml';

		return $path;
	}

	/**
	 * @param $config
	 *
	 * @return bool
	 */
	public function validConfig($config)
	{
		return true;
	}

	/**
	 * @param      $configBreadcrumbs
	 * @param      $entityPathConfig
	 * @param null $data
	 *
	 * @return array
	 */
	public function getBreadcrumb($configBreadcrumbs, $entityPathConfig, $data = null)
	{
		$breadcrumb = array();

		foreach ($configBreadcrumbs as $configBreadcrumb) {
			$url = $this->getActionUrl($entityPathConfig, $configBreadcrumb['action'], $data);

			$breadcrumb[$configBreadcrumb['label']] = $url;
		}

		return $breadcrumb;
	}

	/**
	 * @param      $entityPathConfig
	 * @param      $action
	 * @param null $data
	 * @param bool $absolutePath
	 *
	 * @return string
	 */
	public function getActionUrl($entityPathConfig, $action, $data = null, $absolutePath = false)
	{
		$globalConfig = $this->getGlobalConfig($entityPathConfig);

		if (!isset($globalConfig['actions'][$action])) {
			throw new NotFoundHttpException(
				'L\'action "' . $action . '" n\'est pas déclarée dans le fichier de configuration globale'
			);
		}

		$action = $globalConfig['actions'][$action];

		$route = $action['route'];

		if (!isset($action['parameters'])) {
			return $this->generateUrl($route, array(), $absolutePath);
		}

		if (isset($action['parameters']) && !$data) {
			throw new NotFoundHttpException(
				'L\'action "' . $action['route'] . '" requiert des paramètres et aucune donnée n\'a été reçue'
			);
		}

		$parameters = array();
		foreach ($action['parameters'] as $routerParameterName => $parameter) {
			if (is_object($data)) {
				$parameter = explode('.', $parameter);

				$fieldValue = null;

				foreach ($parameter as $field) {
					if (!$fieldValue) {
						$fieldValue = $data->{'get' . Inflector::camelizeWithFirstLetterUpper($field)}();
					} else {
						$fieldValue = $fieldValue->{'get' . Inflector::camelizeWithFirstLetterUpper($field)}();
					}

					$parameters[$routerParameterName] = $fieldValue;
				}
			} elseif (is_array($data)) {
				$parameters[$routerParameterName] = $data[$parameter];
			}
		}

		return $this->generateUrl($route, $parameters, $absolutePath);
	}

	/**
	 * @param $formFields
	 * @param $entityPathConfig
	 * @param $data
	 *
	 * @return mixed|\Symfony\Component\Form\FormInterface
	 */
	public function getEntityForm($formFields, $entityPathConfig, $data)
	{
		$dataClass = $entityPathConfig['bundle'] . '\Entity\\' . $entityPathConfig['entity'];
		if ($entityPathConfig['bundlePrefix'] != '') {
			$dataClass = $entityPathConfig['bundlePrefix'] . '\\' . $dataClass;
		}

		$form = $this->container->get('form.factory')->create(
			'Symfony\Component\Form\Extension\Core\Type\FormType',
			$data,
			array(
				'data_class' => $dataClass,
			)
		);

		$form = $this->addFormFieldsToForm($form, $formFields, $entityPathConfig);

		return $form;
	}

	/**
	 * @param $formFields
	 *
	 * @return \Symfony\Component\Form\Form|\Symfony\Component\Form\FormInterface
	 */
	public function getForm($formFields)
	{
		$form = $this->container->get('form.factory')->createNamedBuilder('searchForm');

		$form = $this->addFormFieldsToForm($form, $formFields);

		return $form->getForm();
	}

	/**
	 * @param $configFormFields
	 * @param $entityPathConfig
	 *
	 * @return array
	 */
	public function getFormFields($configFormFields, $entityPathConfig)
	{
		$globalConfig = $this->getGlobalConfig($entityPathConfig);

		$formFields = array();

		foreach ($configFormFields as $key => $configFormField) {
			if (is_array($configFormField)) {
				$formFieldSlug = $key;

				if (isset($globalConfig['formFields'][$key])) {
					$formField = array_merge($globalConfig['formFields'][$key], $configFormField);
				} else {
					$formField = $configFormField;
				}
			} else {
				if (is_integer($key)) {
					$formFieldSlug = $configFormField;
				} else {
					$formFieldSlug = $key;
				}

				$formField = $globalConfig['formFields'][$formFieldSlug];
			}

			$formFields[$formFieldSlug] = $formField;
		}

		return $formFields;
	}

	/**
	 * @param       $form
	 * @param       $formFields
	 * @param array $entityPathConfig
	 *
	 * @return mixed
	 */
	private function addFormFieldsToForm($form, $formFields, $entityPathConfig = array())
	{
		foreach ($formFields as $formField => $properties) {
			$options = array(
				'label'    => (!empty($properties['label'])) ? $properties['label'] : false,
				'required' => false,
			);

			$optionsToGive = array(
				'attr',
				'choice_label',
				'class',
			);
			foreach ($optionsToGive as $optionToGive) {
				if (!empty($properties[$optionToGive])) {
					$options[$optionToGive] = $properties[$optionToGive];
				}
			}

			switch ($properties['type']) {

				case 'checkbox':
					$properties['type'] = CheckboxType::class;
					break;

				case 'choice':
					$properties['type'] = ChoiceType::class;

					if ($properties['options']['type'] == 'static') {

						$field = 'get' . Inflector::camelizeWithFirstLetterUpper($properties['options']['field']);
						$entityPath = '\\' . $entityPathConfig['bundlePrefix'] . '\\' . $entityPathConfig['bundle'] . '\Entity\\' . $entityPathConfig['entity'];
						$options['choices'] = $entityPath::$field();
						$options['empty_value'] = false;
					}

					break;

				case 'entity':
					$properties['type'] = EntityType::class;
					break;

				case 'hidden':
					$properties['type'] = HiddenType::class;
					break;

				case 'text':
					$properties['type'] = TextType::class;
					break;

				case 'textarea':
					$properties['type'] = TextareaType::class;
					break;

				case 'tinymce':
					$properties['type'] = TextareaType::class;

					$class = (!empty($options['attr']['class'])) ? $options['attr']['class'] : '';
					$class = 'tinymce ' . $class;
					$options['attr']['class'] = $class;

					break;

				case 'wh_file':
					$properties['type'] = FileType::class;
					break;

				case 'elfinder':
					$properties['type'] = ElFinderType::class;
					$properties['attr']['instance'] = 'default';
					$properties['attr']['enable'] = true;
					break;

				case 'form':
					$properties['type'] = $properties['form'];
					break;

				case 'submit':
					unset($options['required']);
					$properties['type'] = SubmitType::class;
					break;
			}

			$form->add(
				$formField,
				$properties['type'],
				$options
			);
		}

		return $form;
	}

	/**
	 * @param $arguments
	 *
	 * @return array
	 */
	public function getDataFromArguments($arguments)
	{

		$data = array();

		if (empty($arguments)) {
			return $data;
		}

		foreach ($arguments as $condition => $value) {
			$data[Inflector::transformConditionInConditionParameter($condition)] = $value;
		}

		return $data;
	}

	/**
	 * @param $fields
	 * @param $entityPathConfig
	 *
	 * @return mixed
	 */
	public function transformActionIntoRoute($fields, $entityPathConfig)
	{

		$globalConfig = $this->getGlobalConfig($entityPathConfig);

		foreach ($fields as $key => $field) {

			if (isset($field['action'])) {

				$action = $globalConfig['actions'][$field['action']];
				$field = array_merge($field, $action);

				unset($field['action']);

				$fields[$key] = $field;
			}
		}

		return $fields;
	}

}
