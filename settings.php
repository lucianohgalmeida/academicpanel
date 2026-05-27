<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_academicpanel', get_string('pluginname', 'local_academicpanel'));

    $settings->add(new admin_setting_configtext(
        'local_academicpanel/gradecutoff',
        get_string('gradecutoff', 'local_academicpanel'),
        '',
        '7',
        PARAM_FLOAT
    ));

    $settings->add(new admin_setting_configtext(
        'local_academicpanel/rolesincluded',
        get_string('rolesincluded', 'local_academicpanel'),
        '',
        'student',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configselect(
        'local_academicpanel/engagementfallback',
        get_string('engagementfallback', 'local_academicpanel'),
        '',
        'access',
        [
            'none' => get_string('engagementfallback_none', 'local_academicpanel'),
            'access' => get_string('engagementfallback_access', 'local_academicpanel'),
            'completion' => get_string('engagementfallback_completion', 'local_academicpanel'),
        ]
    ));

    $ADMIN->add('localplugins', $settings);
}
