<?php
define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use local_academicpanel\local\mapping_repository;
use local_academicpanel\local\snapshot_service;

list($options, $unrecognized) = cli_get_params([
    'programid' => 0,
    'programshortname' => '',
    'semester' => '',
    'help' => false,
], [
    'h' => 'help',
]);

if ($options['help']) {
    echo "Generate academic panel snapshots.\n";
    echo "Usage: php local/academicpanel/cli/generate_snapshots.php --programid=1 --semester=2026.1\n";
    echo "Usage: php local/academicpanel/cli/generate_snapshots.php --programshortname=nutricao --semester=2026.1\n";
    exit(0);
}

if ($options['semester'] === '') {
    cli_error('Use --semester.');
}

if (!preg_match('/^\d{4}\.\d$/', $options['semester'])) {
    cli_error('Invalid --semester format. Expected YYYY.N (e.g. 2026.1).');
}

$programid = (int)$options['programid'];
if (!$programid && $options['programshortname'] !== '') {
    $program = mapping_repository::get_program_by_shortname($options['programshortname']);
    if (!$program) {
        cli_error('Program shortname not found: ' . $options['programshortname']);
    }
    $programid = (int)$program->id;
}

if (!$programid) {
    cli_error('Use --programid or --programshortname.');
}

$snapshots = snapshot_service::generate_for_program($programid, $options['semester']);
echo 'Generated snapshots: ' . count($snapshots) . "\n";
