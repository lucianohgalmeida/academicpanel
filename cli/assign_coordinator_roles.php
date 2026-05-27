<?php
define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/accesslib.php');

use local_academicpanel\local\role_installer;

list($options, $unrecognized) = cli_get_params(['help' => false], ['h' => 'help']);

if ($options['help']) {
    echo "Assign acpanel_coordinator role to all users in local_acpanel_coord (active=1).\n";
    echo "Usage: php local/academicpanel/cli/assign_coordinator_roles.php\n";
    exit(0);
}

role_installer::ensure_coordinator_role();

$roleid = $DB->get_field('role', 'id', ['shortname' => role_installer::COORDINATOR_SHORTNAME]);
if (!$roleid) {
    cli_error('Coordinator role not found.');
}

$context = context_system::instance();
$userids = $DB->get_fieldset_select('local_acpanel_coord', 'DISTINCT userid', 'active = 1');

$count = 0;
$skipped = 0;
foreach ($userids as $userid) {
    if (!$DB->record_exists('user', ['id' => (int)$userid, 'deleted' => 0])) {
        $skipped++;
        continue;
    }
    role_assign($roleid, (int)$userid, $context->id, 'local_academicpanel');
    $count++;
}

if ($skipped) {
    echo "Skipped {$skipped} record(s) with invalid user id.\n";
}

echo "Assigned coordinator role to {$count} user(s).\n";
