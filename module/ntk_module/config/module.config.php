<?php
namespace ntk_module\Module\Configuration;

$config = [
    'controllers' => array(
        'invokables' => array(
            'ajax' => 'ntk_module\Controller\AjaxController',
        ),
    ),
    'vufind' => array(
        'plugin_managers' => array(
            'ils_driver' => array(
                'factories' => array(
                    'aleph' => 'ntk_module\ILS\Driver\Factory::getAleph',
                ),
            ),
        ),
    ),
];

return $config;
