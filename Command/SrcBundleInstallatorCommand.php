<?php

namespace WH\BackendBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class SrcBundleInstallatorCommand.
 */
class SrcBundleInstallatorCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('wh:install:bundle')
            ->setDescription('Installe les bundles dans le dossier /src')
            ->addArgument('bundle', InputArgument::REQUIRED);
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return bool
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainer();

        $bundle = $input->getArgument('bundle');

        $container->get('bk.wh.back.srcbundleinstallator')->install($bundle);

        return true;
    }
}
