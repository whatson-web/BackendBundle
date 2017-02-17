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
     * @param $bundle
     *
     * @return bool
     */
    public function install($bundle)
    {
        $kernel = $this->container->get('kernel');

        switch ($bundle) {
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

        $bundle = new Bundle(
            $bundleName,
            $bundleName,
            $kernel->getRootDir() . '/../src/',
            'annotation',
            false
        );

        $kernelManipulator = new KernelManipulator($kernel);
        $kernelManipulator->addBundle($bundle->getBundleClassName());

        return true;
    }

}
