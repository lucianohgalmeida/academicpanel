<?php
require_once(__DIR__ . '/../../config.php');

use local_academicpanel\form\rule_form;
use local_academicpanel\local\mapping_repository;
use local_academicpanel\local\snapshot_service;

require_login();
$context = context_system::instance();
require_capability('local/academicpanel:manage', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/academicpanel/rules.php'));
$PAGE->set_title(get_string('managerules', 'local_academicpanel'));
$PAGE->set_heading(get_string('pluginname', 'local_academicpanel'));

$rule = $DB->get_record_select(mapping_repository::RULE_TABLE, 'programid IS NULL', []);
$form = new rule_form();

if ($rule) {
    $form->set_data($rule);
} else {
    $form->set_data((object)[
        'gradecutoff' => get_config('local_academicpanel', 'gradecutoff'),
        'rolesincluded' => get_config('local_academicpanel', 'rolesincluded'),
        'engagementfallback' => get_config('local_academicpanel', 'engagementfallback'),
    ]);
}

if ($data = $form->get_data()) {
    $now = time();
    $record = (object)[
        'programid' => null,
        'gradecutoff' => $data->gradecutoff,
        'rolesincluded' => $data->rolesincluded,
        'engagementfallback' => $data->engagementfallback,
        'timemodified' => $now,
    ];

    if ($rule) {
        $record->id = $rule->id;
        $DB->update_record(mapping_repository::RULE_TABLE, $record);
    } else {
        $record->timecreated = $now;
        $DB->insert_record(mapping_repository::RULE_TABLE, $record);
    }

    snapshot_service::invalidate();

    redirect($PAGE->url, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managerules', 'local_academicpanel'));
echo html_writer::link(new moodle_url('/local/academicpanel/manage.php'), get_string('managemappings', 'local_academicpanel'), [
    'class' => 'btn btn-secondary mb-3',
]);
$form->display();
echo $OUTPUT->footer();
