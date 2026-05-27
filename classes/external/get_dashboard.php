<?php
namespace local_academicpanel\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use core_external\external_value;
use local_academicpanel\local\indicator_calculator;
use local_academicpanel\local\mapping_repository;
use local_academicpanel\local\snapshot_service;

class get_dashboard extends external_api {

    public static function execute_parameters() {
        return new external_function_parameters([
            'programid' => new external_value(PARAM_INT, 'Program ID'),
            'semester' => new external_value(PARAM_TEXT, 'Semester in YYYY.N format'),
        ]);
    }

    public static function execute($programid, $semester) {
        global $USER, $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'programid' => $programid,
            'semester' => $semester,
        ]);

        $context = \context_system::instance();
        self::validate_context($context);

        if (!has_capability('local/academicpanel:viewall', $context) &&
                !has_capability('local/academicpanel:viewassigned', $context)) {
            throw new \required_capability_exception($context,
                'local/academicpanel:viewassigned', 'nopermissions', '');
        }

        if (!preg_match('/^\d{4}\.\d$/', $params['semester'])) {
            throw new \invalid_parameter_exception('Invalid semester format. Use YYYY.N.');
        }

        $programs = mapping_repository::get_visible_programs($USER->id);
        if (!isset($programs[$params['programid']])) {
            throw new \required_capability_exception($context,
                'local/academicpanel:viewassigned', 'nopermissions', '');
        }

        $snapshots = snapshot_service::get_snapshots($params['programid'], $params['semester']);
        $metricrows = [];
        $courseids = [];
        $courseentries = [];

        foreach ($snapshots as $snapshot) {
            $row = [
                'enrolled' => (int)$snapshot->enrolled,
                'withgrade' => (int)$snapshot->withgrade,
                'approved' => (int)$snapshot->approved,
                'failed' => (int)$snapshot->failed,
                'engaged' => (int)$snapshot->engaged,
                'neveraccessed' => (int)$snapshot->neveraccessed,
                'abandoned' => (int)$snapshot->abandoned,
            ];
            $metricrows[] = $row;
            $courseids[] = (int)$snapshot->courseid;
            $courseentries[(int)$snapshot->courseid] = [
                'snapshot' => $snapshot,
                'metrics' => indicator_calculator::merge([$row]),
            ];
        }

        $coursenames = [];
        if (!empty($courseids)) {
            list($insql, $sqlparams) = $DB->get_in_or_equal(array_unique($courseids), SQL_PARAMS_NAMED);
            $records = $DB->get_records_select('course', 'id ' . $insql, $sqlparams, '', 'id, fullname, shortname');
            foreach ($records as $course) {
                $coursenames[(int)$course->id] = [
                    'fullname' => format_string($course->fullname),
                    'shortname' => format_string($course->shortname),
                ];
            }
        }

        $summary = indicator_calculator::merge($metricrows);
        $courses = [];

        foreach ($courseentries as $courseid => $entry) {
            $courses[] = [
                'courseid' => $courseid,
                'fullname' => isset($coursenames[$courseid]) ? $coursenames[$courseid]['fullname'] : (string)$courseid,
                'shortname' => isset($coursenames[$courseid]) ? $coursenames[$courseid]['shortname'] : '',
                'enrolled' => (int)$entry['metrics']['enrolled'],
                'withgrade' => (int)$entry['metrics']['withgrade'],
                'approved' => (int)$entry['metrics']['approved'],
                'failed' => (int)$entry['metrics']['failed'],
                'engaged' => (int)$entry['metrics']['engaged'],
                'neveraccessed' => (int)$entry['metrics']['neveraccessed'],
                'abandoned' => (int)$entry['metrics']['abandoned'],
                'approvalamonggraded' => (float)$entry['metrics']['approvalamonggraded'],
                'failureamonggraded' => (float)$entry['metrics']['failureamonggraded'],
                'engagementrate' => (float)$entry['metrics']['engagementrate'],
                'neveraccessedrate' => (float)$entry['metrics']['neveraccessedrate'],
                'abandonmentrate' => (float)$entry['metrics']['abandonmentrate'],
                'timegenerated' => (int)$entry['snapshot']->timegenerated,
            ];
        }

        return [
            'programid' => (int)$params['programid'],
            'semester' => $params['semester'],
            'summary' => [
                'enrolled' => (int)$summary['enrolled'],
                'withgrade' => (int)$summary['withgrade'],
                'approved' => (int)$summary['approved'],
                'failed' => (int)$summary['failed'],
                'engaged' => (int)$summary['engaged'],
                'neveraccessed' => (int)$summary['neveraccessed'],
                'abandoned' => (int)$summary['abandoned'],
                'approvalamonggraded' => (float)$summary['approvalamonggraded'],
                'approvalamongenrolled' => (float)$summary['approvalamongenrolled'],
                'failureamonggraded' => (float)$summary['failureamonggraded'],
                'failureamongenrolled' => (float)$summary['failureamongenrolled'],
                'engagementrate' => (float)$summary['engagementrate'],
                'neveraccessedrate' => (float)$summary['neveraccessedrate'],
                'abandonmentrate' => (float)$summary['abandonmentrate'],
            ],
            'courses' => $courses,
        ];
    }

    public static function execute_returns() {
        return new external_single_structure([
            'programid' => new external_value(PARAM_INT, 'Program ID'),
            'semester' => new external_value(PARAM_TEXT, 'Semester'),
            'summary' => new external_single_structure([
                'enrolled' => new external_value(PARAM_INT, 'Enrolled'),
                'withgrade' => new external_value(PARAM_INT, 'With grade'),
                'approved' => new external_value(PARAM_INT, 'Approved'),
                'failed' => new external_value(PARAM_INT, 'Failed'),
                'engaged' => new external_value(PARAM_INT, 'Engaged'),
                'neveraccessed' => new external_value(PARAM_INT, 'Never accessed'),
                'abandoned' => new external_value(PARAM_INT, 'Abandoned'),
                'approvalamonggraded' => new external_value(PARAM_FLOAT, 'Approval among graded (%)'),
                'approvalamongenrolled' => new external_value(PARAM_FLOAT, 'Approval among enrolled (%)'),
                'failureamonggraded' => new external_value(PARAM_FLOAT, 'Failure among graded (%)'),
                'failureamongenrolled' => new external_value(PARAM_FLOAT, 'Failure among enrolled (%)'),
                'engagementrate' => new external_value(PARAM_FLOAT, 'Engagement rate (%)'),
                'neveraccessedrate' => new external_value(PARAM_FLOAT, 'Never accessed rate (%)'),
                'abandonmentrate' => new external_value(PARAM_FLOAT, 'Abandonment rate (%)'),
            ]),
            'courses' => new external_multiple_structure(
                new external_single_structure([
                    'courseid' => new external_value(PARAM_INT, 'Course ID'),
                    'fullname' => new external_value(PARAM_TEXT, 'Course full name'),
                    'shortname' => new external_value(PARAM_TEXT, 'Course shortname'),
                    'enrolled' => new external_value(PARAM_INT, 'Enrolled'),
                    'withgrade' => new external_value(PARAM_INT, 'With grade'),
                    'approved' => new external_value(PARAM_INT, 'Approved'),
                    'failed' => new external_value(PARAM_INT, 'Failed'),
                    'engaged' => new external_value(PARAM_INT, 'Engaged'),
                    'neveraccessed' => new external_value(PARAM_INT, 'Never accessed'),
                    'abandoned' => new external_value(PARAM_INT, 'Abandoned'),
                    'approvalamonggraded' => new external_value(PARAM_FLOAT, 'Approval among graded (%)'),
                    'failureamonggraded' => new external_value(PARAM_FLOAT, 'Failure among graded (%)'),
                    'engagementrate' => new external_value(PARAM_FLOAT, 'Engagement rate (%)'),
                    'neveraccessedrate' => new external_value(PARAM_FLOAT, 'Never accessed rate (%)'),
                    'abandonmentrate' => new external_value(PARAM_FLOAT, 'Abandonment rate (%)'),
                    'timegenerated' => new external_value(PARAM_INT, 'Snapshot generation timestamp'),
                ])
            ),
        ]);
    }
}
