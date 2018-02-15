<?php

namespace WH\BackendBundle\Controller\Backend;

use FM\ElfinderBundle\Form\Type\ElFinderType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Role\Role;
use Symfony\Component\Yaml\Yaml;
use WH\LibBundle\Utils\Inflector;
use WH\MediaBundle\Form\Backend\FileType;
use WH\MediaBundle\Form\Backend\TranslatableFileType;

/**
 * Class BaseController.
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
        return [
            'bundlePrefix' => $this->bundlePrefix,
            'bundle' => $this->bundle,
            'entity' => $this->entity,
            'type' => $this->type,
        ];
    }

    /**
     * @param array  $entityPathConfig
     * @param string $action
     *
     * @return mixed
     */
    public function getConfig(array $entityPathConfig, string $action)
    {
        $ymlPath = $this->getYmlFilePath(
            $entityPathConfig,
            $action
        );

        if (!file_exists($ymlPath)) {
            throw new NotFoundHttpException(
                'Le fichier de configuration n\'existe pas. Il devrait être ici : '.$ymlPath
            );
        }

        $resourcesYmlPath = $this->getYmlResourcesFilePath(
            $entityPathConfig,
            $action
        );

        if (file_exists($resourcesYmlPath)) {
            $ymlPath = $resourcesYmlPath;
        }

        $config = Yaml::parse(file_get_contents($ymlPath));

        if ($this->validConfig($config)) {
            return $config;
        }
    }

    /**
     * @param array $entityPathConfig
     *
     * @return mixed
     */
    public function getGlobalConfig(array $entityPathConfig)
    {
        $ymlPath = $this->getYmlFilePath(
            $entityPathConfig,
            'global'
        );

        if (!file_exists($ymlPath)) {
            throw new NotFoundHttpException(
                'Le fichier de configuration globale n\'existe pas. Il devrait être ici : '.$ymlPath
            );
        }

        $resourcesYmlPath = $this->getYmlResourcesFilePath(
            $entityPathConfig,
            'global'
        );

        if (file_exists($resourcesYmlPath)) {
            $ymlPath = $resourcesYmlPath;
        }

        $config = Yaml::parse(file_get_contents($ymlPath));

        if (!isset($config['actions'])) {
            throw new NotFoundHttpException('Le fichier de configuration globale ne contient pas le champ "actions"');
        }

        return $config;
    }

    /**
     * @param array $entityPathConfig
     *
     * @return string
     */
    public function getSlug(array $entityPathConfig)
    {
        $slug = '';
        $slug .= $entityPathConfig['bundlePrefix'];
        $slug .= $entityPathConfig['bundle'];
        $slug .= $entityPathConfig['entity'];
        $slug = Inflector::camelize($slug);

        return $slug;
    }

    /**
     * @param array $entityPathConfig
     *
     * @return string
     */
    public function getEntityPath(array $entityPathConfig)
    {
        $entityPath = '';
        $entityPath .= $entityPathConfig['bundlePrefix'];
        $entityPath .= '\\';
        $entityPath .= $entityPathConfig['bundle'];
        $entityPath .= '\\Entity\\';
        $entityPath .= $entityPathConfig['entity'];

        return $entityPath;
    }

    /**
     * @param array $entityPathConfig
     *
     * @return string
     */
    public function getRepositoryName(array $entityPathConfig)
    {
        $repositoryName = '';
        $repositoryName .= $entityPathConfig['bundlePrefix'];
        $repositoryName .= $entityPathConfig['bundle'];
        $repositoryName .= ':';
        $repositoryName .= $entityPathConfig['entity'];

        return $repositoryName;
    }

    /**
     * @param array $entityPathConfig
     *
     * @return string
     */
    public function getTranslateDomain(array $entityPathConfig)
    {
        $translateDomain = '';
        $translateDomain .= $entityPathConfig['bundlePrefix'];
        $translateDomain .= $entityPathConfig['bundle'];
        $translateDomain .= '_';
        $translateDomain .= $entityPathConfig['type'];
        $translateDomain .= '_';
        $translateDomain .= $entityPathConfig['entity'];

        return $translateDomain;
    }

    /**
     * @param array $config
     *
     * @return bool
     */
    public function validConfig(array $config)
    {
        return true;
    }

    /**
     * @param array $configBreadcrumbs
     * @param array $entityPathConfig
     * @param null  $data
     *
     * @return array
     */
    public function getBreadcrumb(array $configBreadcrumbs, array $entityPathConfig, $data = null)
    {
        $breadcrumb = [];

        foreach ($configBreadcrumbs as $configBreadcrumb) {
            $label = $configBreadcrumb['label'];

            $url = $this->getActionUrl(
                $entityPathConfig,
                $configBreadcrumb['action'],
                $data
            );

            $breadcrumb[$label] = $url;
        }

        return $breadcrumb;
    }

    /**
     * @param array  $entityPathConfig
     * @param string $actionSlug
     * @param null   $data
     * @param bool   $absolutePath
     *
     * @return string
     */
    public function getActionUrl(
        array $entityPathConfig,
        string $actionSlug,
        $data = null,
        int $absolutePath = Router::ABSOLUTE_PATH
    ) {
        $globalConfig = $this->getGlobalConfig($entityPathConfig);

        $action = $this->getActionFromGlobalConfig($globalConfig, $actionSlug);

        $route = $action['route'];

        $parameters = $this->getActionParameters($entityPathConfig, $actionSlug, $data);

        return $this->generateUrl($route, $parameters, $absolutePath);
    }

    /**
     * @param array  $formFields
     * @param array  $entityPathConfig
     * @param        $data
     * @param string $formName
     * @param array  $formOptions
     *
     * @return mixed|\Symfony\Component\Form\FormInterface
     */
    public function getEntityForm(
        array $formFields,
        array $entityPathConfig,
        $data,
        string $formName = 'form',
        array $formOptions = []
    ) {
        $className = $this->getClassNameFromEntityPathConfig($entityPathConfig);

        $form = $this->container->get('form.factory')->createNamed(
            $formName,
            'Symfony\Component\Form\Extension\Core\Type\FormType',
            $data,
            array_merge(
                [
                    'data_class' => $className,
                ],
                $formOptions
            )
        );

        $form = $this->addFormFieldsToForm($form, $formFields, $entityPathConfig);

        return $form;
    }

    /**
     * @param array  $formFields
     * @param string $formName
     *
     * @return mixed
     */
    public function getForm(array $formFields, string $formName = 'searchForm')
    {
        $form = $this->container->get('form.factory')->createNamedBuilder($formName);

        $form = $this->addFormFieldsToForm($form, $formFields);

        return $form->getForm();
    }

    /**
     * @param array $configFormFields
     * @param array $entityPathConfig
     *
     * @return array
     */
    public function getFormFields(array $configFormFields, array $entityPathConfig)
    {
        $globalConfig = $this->getGlobalConfig($entityPathConfig);

        $configFormFields = $this->prepareFormFields($configFormFields);

        $formFields = [];

        foreach ($configFormFields as $key => $configFormField) {
            $configDefaultFormField = $this->getVariableValue($key, $globalConfig['formFields']);

            if (is_array($configDefaultFormField)) {
                $configFormField = array_merge($configDefaultFormField, $configFormField);
            }

            $formFields[$key] = $configFormField;
        }

        return $formFields;
    }

    /**
     * @param Form  $form
     * @param array $formFields
     * @param array $entityPathConfig
     *
     * @return Form
     */
    public function addFormFieldsToForm(Form $form, array $formFields, array $entityPathConfig = [])
    {
        foreach ($formFields as $formField => $properties) {
            $fieldSlug = $formField;

            if (isset($properties['field'])) {
                $fieldSlug = $properties['field'];
            }

            $options = $this->getFormFieldDefaultOptions();

            $options = $this->getOverridedOptionsFromProperties($options, $properties);

            $formFieldType = $this->getFormFieldType($properties);

            $options = $this->getFormFieldTypeOptions($options, $properties, $entityPathConfig);

            switch ($properties['type']) {
                case 'elfinder':
                    $properties['attr']['instance'] = 'default';
                    $properties['attr']['enable'] = true;

                    break;
                case 'form':
                    $properties['type'] = $properties['form'];

                    break;
            }

            if (FormType::class === $formFieldType) {
                $subForm = $this->getEntityForm(
                    $properties['fields'],
                    $properties['entityPathConfig'],
                    $this->getVariableValue($fieldSlug, $form->getData()),
                    $fieldSlug,
                    [
                        'auto_initialize' => false,
                    ]
                );

                $form->add($subForm);
            } else {
                $form->add(
                    $fieldSlug,
                    $formFieldType,
                    $options
                );
            }
        }

        return $form;
    }

    /**
     * @param array $arguments
     *
     * @return array
     */
    public function getConditionsFromArguments(array $arguments)
    {
        $conditions = [];

        if (empty($arguments)) {
            return $conditions;
        }

        foreach ($arguments as $conditionKey => $value) {
            // Il faudrait le faire de façon récursive, là ça traite un seul niveau
            if (is_array($value)) {
                foreach ($value as $conditionSubKey => $conditionSubValue) {
                    $conditions[$conditionKey.'.'.$conditionSubKey] = $conditionSubValue;
                }
            } else {
                $conditions[$conditionKey] = $value;
            }
        }

        return $conditions;
    }

    /**
     * @param array $fields
     * @param array $entityPathConfig
     *
     * @return array
     */
    public function transformActionIntoRoute(array $fields, array $entityPathConfig)
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

    /**
     * @param $variable
     * @param $data
     *
     * @return string
     */
    public function getVariableValue(string $variable, $data)
    {
        $fields = explode('.', $variable);

        $value = $data;

        foreach ($fields as $field) {
            $getMethodeName = 'get'.ucfirst($field);

            if (is_object($value) && method_exists($value, $getMethodeName)) {
                $value = $value->{$getMethodeName}();
            } elseif (is_array($value) && isset($value[$field])) {
                $value = $value[$field];
            } else {
                $value = null;
            }
        }

        return $value;
    }

    /**
     * @param array      $entityPathConfig
     * @param string     $actionSlug
     * @param null|mixed $data
     *
     * @return array
     */
    private function getActionParameters(array $entityPathConfig, string $actionSlug, $data = null)
    {
        $globalConfig = $this->getGlobalConfig($entityPathConfig);

        $action = $this->getActionFromGlobalConfig($globalConfig, $actionSlug);

        $parameters = [];

        if (isset($action['parameters'])) {
            if (null === $data) {
                $data = $this->getDefaultDataFromGlobalConfig($globalConfig);
            }

            if (isset($action['parameters']) && !$data) {
                throw new NotFoundHttpException(
                    'L\'action "'.$action['route'].'" requiert des paramètres et aucune donnée n\'a été reçue'
                );
            }

            $parameters = [];

            foreach ($action['parameters'] as $routerParameterName => $parameter) {
                $parameterValue = $this->getVariableValue($parameter, $data);

                $parameters[$routerParameterName] = $parameterValue;
            }
        }

        return $parameters;
    }

    /**
     * @param array $configFormFields
     *
     * @return array
     */
    private function prepareFormFields(array $configFormFields)
    {
        $cleanedFormFields = [];

        foreach ($configFormFields as $key => $configFormField) {
            $formFieldSlug = $key;

            $formField = $configFormField;

            if (null === $configFormField) {
                $formField = [];
            }

            if (is_integer($key)) {
                $formFieldSlug = $configFormField;
                $formField = [];
            }

            $cleanedFormFields[$formFieldSlug] = $formField;
        }

        return $cleanedFormFields;
    }

    /**
     * @return array
     */
    private function getFormFieldDefaultOptions()
    {
        return [
            'label' => false,
            'required' => false,
        ];
    }

    /**
     * @param array $options
     * @param array $properties
     *
     * @return array
     */
    private function getOverridedOptionsFromProperties(array $options, array $properties)
    {
        $overridableOptions = [
            'label',
            'attr',
            'choice_label',
            'class',
        ];

        foreach ($overridableOptions as $overridableOption) {
            if (!empty($properties[$overridableOption])) {
                $options[$overridableOption] = $properties[$overridableOption];
            }
        }

        return $options;
    }

    /**
     * @param array $options
     * @param array $properties
     * @param array $entityPathConfig
     *
     * @return array
     */
    private function getFormFieldTypeOptions(array $options, array $properties, array $entityPathConfig)
    {
        switch ($properties['type']) {
            case 'checkbox':
                return $this->getCheckboxOptions($options, $properties);
            case 'choice':
                return $this->getChoiceOptions($options, $properties, $entityPathConfig);
            case 'date':
                return $this->getDateOptions($options, $properties);
            case 'entity':
                return $this->getEntityOptions($options, $properties);
            case 'text':
                return $this->getTextOptions($options, $properties);
            case 'number':
                return $this->getNumberOptions($options, $properties);
            case 'tinymce':
                return $this->getTinymceOptions($options, $properties);
            case 'collection':
                return $this->getCollectionOptions($options, $properties);
            case 'submit':
                return $this->getSubmitOptions($options, $properties);
        }

        return $options;
    }

    /**
     * @param array $properties
     *
     * @return null|mixed|string
     */
    private function getFormFieldType(array $properties)
    {
        switch ($properties['type']) {
            case 'checkbox':
                return CheckboxType::class;
            case 'choice':
                return ChoiceType::class;
            case 'date':
                return DateType::class;
            case 'datetime':
                return DateTimeType::class;
            case 'email':
                return EmailType::class;
            case 'entity':
                return EntityType::class;
            case 'hidden':
                return HiddenType::class;
            case 'integer':
                return IntegerType::class;
            case 'password':
                return PasswordType::class;
            case 'text':
                return TextType::class;
            case 'number':
                return NumberType::class;
            case 'textarea':
                return TextareaType::class;
            case 'tinymce':
                return TextareaType::class;
            case 'file':
                return \Symfony\Component\Form\Extension\Core\Type\FileType::class;
            case 'wh_file':
                return FileType::class;
            case 'wh_file_translatable':
                return TranslatableFileType::class;
            case 'elfinder':
                return ElFinderType::class;
            case 'form':
                return $properties['form'];
            case 'collection':
                return CollectionType::class;
            case 'sub-form':
                return FormType::class;
            case 'submit':
                return SubmitType::class;
        }

        return null;
    }

    /**
     * @param array $options
     * @param array $properties
     *
     * @return array
     */
    private function getCheckboxOptions(array $options, array $properties)
    {
        if (isset($properties['disabled'])) {
            $options['disabled'] = $properties['disabled'];
        }

        return $options;
    }

    /**
     * @param array $options
     * @param array $properties
     * @param array $entityPathConfig
     *
     * @return array
     */
    private function getChoiceOptions(array $options, array $properties, array $entityPathConfig = [])
    {
        if (isset($properties['empty_data'])) {
            $options['placeholder'] = $properties['empty_data'];
        }

        if (isset($properties['multiple'])) {
            $options['multiple'] = $properties['multiple'];
        }

        if (isset($properties['options']['type'])) {
            switch ($properties['options']['type']) {
                case 'static':
                    $options = $this->getStaticChoiceOptions($options, $properties, $entityPathConfig);

                    break;
                case 'parameter':
                    $options = $this->getParameterChoiceOptions($options, $properties, $entityPathConfig);

                    break;
            }
        }

        return $options;
    }

    /**
     * @param array $options
     * @param array $properties
     * @param array $entityPathConfig
     *
     * @return array
     */
    private function getStaticChoiceOptions(array $options, array $properties, array $entityPathConfig = [])
    {
        $field = 'get'.ucfirst($properties['options']['field']);

        $entityPath = $this->getClassNameFromEntityPathConfig($entityPathConfig);

        if (isset($properties['options']['entityPath'])) {
            $entityPath = $properties['options']['entityPath'];
        }

        $options['choices'] = array_flip($entityPath::$field());

        foreach ($options['choices'] as $key => $value) {
            $options['choices'][$key] = $value;
        }

        $options['placeholder'] = false;

        if (isset($properties['placeholder'])) {
            $options['placeholder'] = $properties['placeholder'];
        }

        return $options;
    }

    /**
     * @param array $options
     * @param array $properties
     * @param array $entityPathConfig
     *
     * @return array
     */
    private function getParameterChoiceOptions(array $options, array $properties, array $entityPathConfig = [])
    {
        switch ($properties['options']['parameter']) {
            case 'security.role_hierarchy.roles':
                $arrayRoles = $this->getUser()->getRoles();

                $roles = [];
                foreach ($arrayRoles as $arrayRole) {
                    $roles[] = new Role($arrayRole);
                }

                $roles = $this->get('security.role_hierarchy')->getReachableRoles($roles);

                $choices = [];
                foreach ($roles as $role) {
                    $choices[$role->getRole()] = $role->getRole();
                }

                unset($choices['ROLE_USER']);

                $options['choices'] = $choices;

                break;
            default:
                $choices = $this->container->getParameter($properties['options']['parameter']);
                $choices = array_flip($choices);
                $options['choices'] = $choices;

                break;
        }

        return $options;
    }

    /**
     * @param array $options
     * @param array $properties
     *
     * @return array
     */
    private function getDateOptions(array $options, array $properties)
    {
        if (isset($properties['startYear'])) {
            $years = [];

            $now = new \DateTime();
            $startYear = $properties['startYear'];

            while ($startYear < $now->format('Y')) {
                $years[] = $startYear;
                ++$startYear;
            }

            $options['years'] = $years;
        }

        return $options;
    }

    /**
     * @param array $options
     * @param array $properties
     *
     * @return array
     */
    private function getEntityOptions(array $options, array $properties)
    {
        $options['class'] = $properties['class'];

        if (isset($properties['choice_label'])) {
            $options['choice_label'] = $properties['choice_label'];

            $em = $this->container->get('doctrine')->getManager();

            $entityRepository = $em->getRepository($properties['class']);
            $query = $entityRepository->get('query');

            $options['query_builder'] = $query;
        }

        if (isset($properties['multiple'])) {
            $options['multiple'] = $properties['multiple'];
        }

        if (isset($properties['group_by'])) {
            $options['group_by'] = $properties['group_by'];
        }

        if (isset($properties['choices'])) {
            $options['choices'] = $properties['choices'];
        }

        if (isset($properties['custom_query_builder'])) {
            $em = $this->container->get('doctrine')->getManager();
            $entityRepository = $em->getRepository($properties['class']);

            $conditions = [];

            if (isset($properties['custom_query_builder']['conditions'])) {
                foreach ($properties['custom_query_builder']['conditions'] as $condition => $conditionValue) {
                    if (is_int($condition)) {
                        $condition = $conditionValue;
                        $conditions[$condition] = $this->getVariableValue($condition, $form->getData());
                    } else {
                        $conditions[$condition] = $conditionValue;
                    }
                }
            }

            $query = $entityRepository->get(
                'query',
                [
                    'conditions' => $conditions,
                ]
            );

            $options['query_builder'] = $query;
        }

        return $options;
    }

    /**
     * @param array $options
     * @param array $properties
     *
     * @return array
     */
    private function getTextOptions(array $options, array $properties)
    {
        if (isset($properties['disabled'])) {
            $options['disabled'] = $properties['disabled'];
        }

        return $options;
    }

    /**
     * @param array $options
     * @param array $properties
     *
     * @return array
     */
    private function getNumberOptions(array $options, array $properties)
    {
        if (!empty($properties['scale'])) {
            $options['scale'] = (int) $properties['scale'];
        }

        return $options;
    }

    /**
     * @param array $options
     * @param array $properties
     *
     * @return array
     */
    private function getTinymceOptions(array $options, array $properties)
    {
        $class = (!empty($options['attr']['class'])) ? $options['attr']['class'] : '';
        $class = 'tinymce '.$class;
        $options['attr']['class'] = $class;

        return $options;
    }

    /**
     * @param array $options
     * @param array $properties
     *
     * @return array
     */
    private function getCollectionOptions(array $options, array $properties)
    {
        $options['entry_type'] = $properties['form'];
        $options['allow_add'] = true;
        $options['allow_delete'] = true;
        $options['delete_empty'] = true;
        $options['delete_empty'] = true;
        $options['by_reference'] = false;
        $options['attr']['data-form-template'] = $properties['formTemplate'];

        if (isset($properties['formTemplateHead'])) {
            $options['attr']['data-form-template-head'] = $properties['formTemplateHead'];
        }

        if (isset($properties['sortable'])) {
            $options['attr']['data-sortable'] = true;
        }

        if (isset($properties['disableAdd'])) {
            $options['attr']['disableAdd'] = $properties['disableAdd'];
        }

        return $options;
    }

    /**
     * @param array $options
     * @param array $properties
     *
     * @return array
     */
    private function getSubmitOptions(array $options, array $properties)
    {
        unset($options['required']);

        return $options;
    }

    /**
     * @param array  $entityPathConfig
     * @param string $slug
     *
     * @return string
     */
    private function getYmlResourcesFilePath(array $entityPathConfig, string $slug)
    {
        $rootDir = $this->get('kernel')->getRootDir();
        $bundleName = $this->getBundleName($entityPathConfig);

        $path = $rootDir;
        $path .= '/Resources/';
        $path .= $bundleName;
        $path .= '/config/';
        $path .= $entityPathConfig['type'];
        $path .= '/';
        $path .= $entityPathConfig['entity'];
        $path .= '/';
        $path .= $slug;
        $path .= '.yml';

        return $path;
    }

    /**
     * @param array  $entityPathConfig
     * @param string $slug
     *
     * @return string
     */
    private function getYmlFilePath(array $entityPathConfig, string $slug)
    {
        $path = '@';
        $path .= $entityPathConfig['bundlePrefix'];
        $path .= $entityPathConfig['bundle'];
        $path .= '/Resources/config/';
        $path .= $entityPathConfig['type'];
        $path .= '/';
        $path .= $entityPathConfig['entity'];
        $path .= '/';
        $path .= $slug;
        $path .= '.yml';

        $path = $this->get('kernel')->locateResource($path);

        return $path;
    }

    /**
     * @param array $entityPathConfig
     *
     * @return string
     */
    private function getBundleName(array $entityPathConfig)
    {
        $bundleName = '';
        $bundleName .= $entityPathConfig['bundlePrefix'];
        $bundleName .= $entityPathConfig['bundle'];

        return $bundleName;
    }

    /**
     * @param array $globalConfig
     *
     * @return mixed
     */
    private function getActionFromGlobalConfig(array $globalConfig, string $actionSlug)
    {
        if (!isset($globalConfig['actions'][$actionSlug])) {
            throw new NotFoundHttpException(
                'L\'action "'.$actionSlug.'" n\'est pas déclarée dans le fichier de configuration globale'
            );
        }

        $action = $globalConfig['actions'][$actionSlug];

        return $action;
    }

    /**
     * @param array $globalConfig
     *
     * @return null|mixed
     */
    private function getDefaultDataFromGlobalConfig(array $globalConfig)
    {
        $data = null;

        if (isset($globalConfig['defaultData'])) {
            $data = $globalConfig['defaultData'];
        }

        return $data;
    }

    /**
     * @param array $entityPathConfig
     *
     * @return string
     */
    private function getClassNameFromEntityPathConfig(array $entityPathConfig)
    {
        $className = '';

        if ('' !== $entityPathConfig['bundlePrefix']) {
            $className .= $entityPathConfig['bundlePrefix'];
            $className .= '\\';
        }

        $className .= $entityPathConfig['bundle'];
        $className .= '\\Entity\\';
        $className .= $entityPathConfig['entity'];

        return $className;
    }
}
