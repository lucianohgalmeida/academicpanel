<?php
namespace local_academicpanel\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_single_structure;
use external_multiple_structure;
use external_value;
use local_academicpanel\local\mapping_repository;

class get_programs extends external_api {

    public static function execute_parameters() {
        return new external_function_parameters([]);
    }

    public static function execute() {
        global $USER;

        $context = \context_system::instance();
        self::validate_context($context);

        if (!has_capability('local/academicpanel:viewall', $context) &&
                !has_capability('local/academicpanel:viewassigned', $context)) {
            throw new \required_capability_exception($context,
                'local/academicpanel:viewassigned', 'nopermissions', '');
        }

        $programs = mapping_repository::get_visible_programs($USER->id);
        $result = [];

        foreach ($programs as $program) {
            $result[] = [
                'id' => (int)$program->id,
                'name' => format_string($program->name),
                'shortname' => $program->shortname,
                'active' => (int)$program->active,
            ];
        }

        return $result;
    }

    public static function execute_returns() {
        return new external_multiple_structure(
            new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Program ID'),
                'name' => new external_value(PARAM_TEXT, 'Program name'),
                'shortname' => new external_value(PARAM_ALPHANUMEXT, 'Program shortname'),
                'active' => new external_value(PARAM_INT, 'Active flag'),
            ])
        );
    }
}
