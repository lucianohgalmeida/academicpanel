<?php
defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_academicpanel_get_programs' => [
        'classname'   => 'local_academicpanel\external\get_programs',
        'description' => 'Returns the academic programs visible to the current user.',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'local/academicpanel:viewall,local/academicpanel:viewassigned',
    ],
    'local_academicpanel_get_dashboard' => [
        'classname'   => 'local_academicpanel\external\get_dashboard',
        'description' => 'Returns aggregated indicators and per-course snapshots for a program and semester.',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'local/academicpanel:viewall,local/academicpanel:viewassigned',
    ],
];

$services = [
    'Academic Panel Service' => [
        'functions'       => [
            'local_academicpanel_get_programs',
            'local_academicpanel_get_dashboard',
        ],
        'restrictedusers' => 1,
        'enabled'         => 1,
        'shortname'       => 'local_academicpanel_service',
        'downloadfiles'   => 0,
        'uploadfiles'     => 0,
    ],
];
