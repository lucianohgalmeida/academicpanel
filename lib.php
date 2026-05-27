<?php
defined('MOODLE_INTERNAL') || die();

function local_academicpanel_extend_navigation(global_navigation $navigation) {
    $context = context_system::instance();

    if (!isloggedin() || isguestuser()) {
        return;
    }

    if (has_capability('local/academicpanel:viewall', $context) ||
            has_capability('local/academicpanel:viewassigned', $context)) {
        $navigation->add(
            get_string('pluginname', 'local_academicpanel'),
            new moodle_url('/local/academicpanel/index.php'),
            navigation_node::TYPE_CUSTOM,
            null,
            'local_academicpanel',
            new pix_icon('i/report', '')
        );
    }
}
