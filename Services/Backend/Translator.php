<?php

namespace WH\BackendBundle\Services\Backend;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class Translator
 *
 * @package WH\BackendBundle\Services\Backend
 */
class Translator
{

	protected $container;

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
	 * @param       $stringToTranslate
	 * @param array $parameters
	 *
	 * @return string
	 */
	public function trans($stringToTranslate, $parameters = array())
	{
		return $this->container->get('translator')->trans(
			$stringToTranslate,
			$parameters,
			'wh_backend'
		);
	}
}
