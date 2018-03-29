<?php

namespace ntk_module\Controller;

class Factory
{

    /**
     * Construct the AjaxController.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return AjaxController
     */
    public static function getAjaxController(\Zend\ServiceManager\ServiceManager $sm)
    {
        return new \ntk_module\Controller\AjaxController(
            $sm->getServiceLocator(),
            $sm->getServiceLocator()->get('VuFind\Config')->get('config')
        );
    }

    public static function getMyResearchController(\Zend\ServiceManager\ServiceManager $sm)
    {
        return new \ntk_module\Controller\MyResearchController(
            $sm->getServiceLocator(),
            $sm->getServiceLocator()->get('VuFind\Config')->get('config')
        );
    }

    /**
     * Construct the RecordController.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return RecordController
     */
    public static function getRecordController(\Zend\ServiceManager\ServiceManager $sm)
    {
        return new RecordController(
            $sm->getServiceLocator(),
            $sm->getServiceLocator()->get('VuFind\Config')->get('config')
        );
    }
}

