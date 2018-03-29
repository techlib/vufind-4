<?php

namespace ntk_module\Service;
use Zend\ServiceManager\ServiceManager;

class Factory
{
    /**
     * Construct the ILS hold logic.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFind\ILS\Logic\Holds
     */
    public static function getILSHoldLogic(ServiceManager $sm)
    {
        return new \ntk_module\ILS\Logic\Holds(
            $sm->get('VuFind\ILSAuthenticator'), $sm->get('VuFind\ILSConnection'),
            $sm->get('VuFind\HMAC'), $sm->get('VuFind\Config')->get('config')
        );
    }

    /**
     * Construct the date converter.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFind\Date\Converter
     */
    public static function getDateConverter(ServiceManager $sm)
    {
        return new \ntk_module\Date\Converter(
            $sm->get('VuFind\Config')->get('config')
        );
    }
}

