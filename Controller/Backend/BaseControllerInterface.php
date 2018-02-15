<?php

namespace WH\BackendBundle\Controller\Backend;

/**
 * Class BaseControllerInterface.
 */
interface BaseControllerInterface
{
    /**
     * @param $config
     *
     * @return bool
     */
    public function validConfig(array $config);
}
