<?php
define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

list($options, $unrecognized) = cli_get_params([
    'output' => '',
    'help' => false,
], [
    'h' => 'help',
    'o' => 'output',
]);

if ($options['help']) {
    echo "Export local_academicpanel configuration data as JSON.\n";
    echo "Usage: php local/academicpanel/cli/export.php --output=/path/file.json\n";
    echo "       php local/academicpanel/cli/export.php > file.json\n";
    echo "Exports: programs, category mappings, coordinators, rules.\n";
    echo "Does NOT export snapshots (regenerable) or seed records.\n";
    exit(0);
}

global $DB;

$payload = [
    'version' => 1,
    'exported_at' => date('c'),
    'programs' => array_values($DB->get_records('local_acpanel_program', null, 'id ASC')),
    'categories' => array_values($DB->get_records('local_acpanel_category', null, 'id ASC')),
    'coordinators' => array_values($DB->get_records('local_acpanel_coord', null, 'id ASC')),
    'rules' => array_values($DB->get_records('local_acpanel_rule', null, 'id ASC')),
];

$json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

if ($options['output'] !== '') {
    if (file_put_contents($options['output'], $json) === false) {
        cli_error('Failed to write output to ' . $options['output']);
    }
    fwrite(STDERR, "Exported to {$options['output']}\n");
    exit(0);
}

echo $json . "\n";
