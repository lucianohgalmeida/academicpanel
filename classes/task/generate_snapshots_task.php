<?php
namespace local_academicpanel\task;

defined('MOODLE_INTERNAL') || die();

use local_academicpanel\local\mapping_repository;
use local_academicpanel\local\snapshot_service;

class generate_snapshots_task extends \core\task\scheduled_task {

    public function get_name() {
        return get_string('task_generate_snapshots', 'local_academicpanel');
    }

    public function execute() {
        global $DB;

        $programs = $DB->get_records(mapping_repository::PROGRAM_TABLE, ['active' => 1]);

        foreach ($programs as $program) {
            $semesters = $DB->get_records_sql(
                'SELECT DISTINCT semester
                   FROM {' . mapping_repository::CATEGORY_TABLE . '}
                  WHERE programid = :programid',
                ['programid' => $program->id]
            );

            foreach ($semesters as $row) {
                if ((string)$row->semester === '') {
                    continue;
                }
                try {
                    snapshot_service::generate_for_program((int)$program->id, $row->semester);
                } catch (\Throwable $e) {
                    mtrace('local_academicpanel snapshot failed for program ' . $program->id .
                        ' semester ' . $row->semester . ': ' . $e->getMessage());
                }
            }
        }
    }
}
