<?php

namespace WH\BackendBundle\Services\Backend;

use Sensio\Bundle\GeneratorBundle\Manipulator\KernelManipulator;
use Sensio\Bundle\GeneratorBundle\Model\Bundle;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class SrcBundleInstallator
 *
 * @package WH\BackendBundle\Services\Backend
 */
class SrcBundleInstallator
{

    private $container;

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
     * @param $bundleSlug
     *
     * @return bool
     */
    public function install($bundleSlug)
    {
        $kernel = $this->container->get('kernel');

        switch ($bundleSlug) {
            case 'cms':
                $bundleName = 'CmsBundle';
                $srcBundleDirPath = $kernel->getRootDir() . '/../vendor/whatson-web/cms-bundle/SrcBundle';
                break;
            default:
                return false;
                break;
        }

        $fs = new Filesystem();
        $fs->mirror($srcBundleDirPath, $kernel->getRootDir() . '/../src/');

        $kernelManipulator = new KernelManipulator($kernel);

        $bundle = new Bundle(
            $bundleName,
            $bundleName,
            $kernel->getRootDir() . '/../src/',
            'annotation',
            false
        );

        $kernelManipulator->addBundle($bundle->getBundleClassName());

        switch ($bundleSlug) {
            case 'cms':
                $bundleNameSpace = 'WH\CmsBundle';
                $bundleName = 'WHCmsBundle';
                $bundleDirPath = $kernel->getRootDir() . '/../vendor/whatson-web/cms-bundle/CmsBundle/';
                break;
        }

        $bundle = new Bundle(
            $bundleNameSpace,
            $bundleName,
            $bundleDirPath,
            'annotation',
            false
        );

        $kernelManipulator->addBundle($bundle->getBundleClassName());

        return true;
    }

}
