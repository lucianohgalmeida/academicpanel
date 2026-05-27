<?php
define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use local_academicpanel\local\mapping_repository;

list($options, $unrecognized) = cli_get_params([
    'input' => '',
    'help' => false,
], [
    'h' => 'help',
    'i' => 'input',
]);

if ($options['help']) {
    echo "Import local_academicpanel configuration data from JSON.\n";
    echo "Usage: php local/academicpanel/cli/import.php --input=/path/file.json\n";
    echo "Reads JSON produced by export.php. Performs upserts by stable keys.\n";
    exit(0);
}

if ($options['input'] === '') {
    cli_error('Use --input=<file>.');
}

if (!is_readable($options['input'])) {
    cli_error('Cannot read input file: ' . $options['input']);
}

$raw = file_get_contents($options['input']);
$payload = json_decode($raw, true);

if (!is_array($payload) || !isset($payload['programs'])) {
    cli_error('Invalid JSON payload. Missing required fields.');
}

global $DB;

$programidmap = [];
$count = [
    'programs' => 0,
    'categories' => 0,
    'coordinators' => 0,
    'rules' => 0,
];

foreach ($payload['programs'] as $program) {
    $oldid = (int)$program['id'];
    $newid = mapping_repository::upsert_program($program['name'], $program['shortname']);
    if (isset($program['active']) && (int)$program['active'] === 0) {
        mapping_repository::deactivate_program($newid);
    }
    $programidmap[$oldid] = $newid;
    $count['programs']++;
}

foreach ($payload['categories'] ?? [] as $category) {
    $programid = isset($programidmap[(int)$category['programid']])
        ? $programidmap[(int)$category['programid']]
        : (int)$category['programid'];
    mapping_repository::upsert_category_mapping(
        $programid,
        (int)$category['categoryid'],
        (string)$category['semester'],
        isset($category['origin']) ? (string)$category['origin'] : 'manual'
    );
    $count['categories']++;
}

foreach ($payload['coordinators'] ?? [] as $coord) {
    $programid = isset($programidmap[(int)$coord['programid']])
        ? $programidmap[(int)$coord['programid']]
        : (int)$coord['programid'];
    if (!$DB->record_exists('user', ['id' => (int)$coord['userid'], 'deleted' => 0])) {
        fwrite(STDERR, "Skipping coordinator: user {$coord['userid']} not found\n");
        continue;
    }
    mapping_repository::add_coordinator($programid, (int)$coord['userid']);
    if (isset($coord['active']) && (int)$coord['active'] === 0) {
        $record = $DB->get_record('local_acpanel_coord', [
            'programid' => $programid,
            'userid' => (int)$coord['userid'],
        ]);
        if ($record) {
            mapping_repository::deactivate_coordinator((int)$record->id);
        }
    }
    $count['coordinators']++;
}

foreach ($payload['rules'] ?? [] as $rule) {
    $now = time();
    $programid = !empty($rule['programid'])
        ? (isset($programidmap[(int)$rule['programid']]) ? $programidmap[(int)$rule['programid']] : (int)$rule['programid'])
        : null;

    $existing = $programid
        ? $DB->get_record('local_acpanel_rule', ['programid' => $programid])
        : $DB->get_record_select('local_acpanel_rule', 'programid IS NULL', []);

    $record = (object)[
        'programid' => $programid,
        'gradecutoff' => (float)$rule['gradecutoff'],
        'rolesincluded' => (string)$rule['rolesincluded'],
        'engagementfallback' => (string)$rule['engagementfallback'],
        'timemodified' => $now,
    ];

    if ($existing) {
        $record->id = $existing->id;
        $DB->update_record('local_acpanel_rule', $record);
    } else {
        $record->timecreated = $now;
        $DB->insert_record('local_acpanel_rule', $record);
    }
    $count['rules']++;
}

fwrite(STDERR, "Import complete: " . json_encode($count) . "\n");
