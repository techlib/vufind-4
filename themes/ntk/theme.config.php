<?php
return array(
    'extends' => 'bootstrap3',
    'css' => array(
        'compiled.css',
        'styles.css',
        'vufind.css',
        'ntk.css',
    ),
    'js' => array(
        'NTK.js',
        'bootstrap-datepicker.js',
        'bootstrap-datepicker.cs.js',
    ),
    'less' => array(
        'active' => true,
        'compiled.less'
    ),
    'helpers' => array(
        'factories' => array(
            'recorddataformatter' => 'ntk_module\View\Helper\RecordDataFormatterFactory',
        ),
    ),
);
