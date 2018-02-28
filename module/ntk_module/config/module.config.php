<?php

return array (
  'vufind' => 
  array (
    'plugin_managers' => 
    array (
      'ils_driver' => 
      array (
        'factories' => 
        array (
          'aleph' => 'ntk_module\\ILS\\Driver\\Factory::getAleph',
        ),
      ),
    ),
  ),
  'controllers' => 
  array (
    'factories' => 
    array (
      'ajax' => 'ntk_module\\Controller\\Factory::getAjaxController',
    ),
  ),
);
