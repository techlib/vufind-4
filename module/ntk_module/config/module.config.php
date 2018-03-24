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
      'content_covers' => 
      array (
        'invokables' => 
        array (
          'alephimagecovers' => 'ntk_module\\Content\\Covers\\AlephImageCovers',
        ),
      ),
      'recorddriver' =>
      array (
        'factories' =>
        array (
          'solrmarc' => 'ntk_module\\RecordDriver\\Factory::getSolrMarc',
        ),
      ),
      'recordtab' =>
      array (
        'factories' =>
        array (
          'holdingsils' => 'ntk_module\\RecordTab\\Factory::getHoldingsILS',
        ),
      ),
    ),
  ),
  'controllers' => 
  array (
    'factories' => 
    array (
      'ajax' => 'ntk_module\\Controller\\Factory::getAjaxController',
      'my-research' => 'ntk_module\\Controller\\Factory::getMyResearchController',
    ),
  ),
  'service_manager' =>
  array (
    'factories' =>
    array (
      'VuFind\\ILSHoldLogic' => 'ntk_module\Service\Factory::getILSHoldLogic',
    ),
  ),
);
