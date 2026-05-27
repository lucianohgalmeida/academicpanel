<?php
namespace local_academicpanel\local;

defined('MOODLE_INTERNAL') || die();

class snapshot_service {
    const SNAPSHOT_TABLE = 'local_acpanel_snapshot';

    public static function generate_for_program($programid, $semester) {
        global $DB;

        $rule = mapping_repository::get_rule($programid);
        $mappings = mapping_repository::get_mappings($programid, $semester);
        $courses = indicator_repository::get_courses_for_mappings($mappings);
        $now = time();
        $snapshots = [];
        $courseids = [];

        foreach ($courses as $course) {
            $courseids[] = $course->id;
            $metrics = indicator_repository::get_course_metrics($course, $rule);
            $record = (object)[
                'programid' => $programid,
                'courseid' => $course->id,
                'semester' => $semester,
                'enrolled' => $metrics['enrolled'],
                'withgrade' => $metrics['withgrade'],
                'approved' => $metrics['approved'],
                'failed' => $metrics['failed'],
                'engaged' => $metrics['engaged'],
                'neveraccessed' => $metrics['neveraccessed'],
                'abandoned' => $metrics['abandoned'],
                'timegenerated' => $now,
            ];

            $existing = $DB->get_record(self::SNAPSHOT_TABLE, [
                'programid' => $programid,
                'courseid' => $course->id,
                'semester' => $semester,
            ]);

            if ($existing) {
                $record->id = $existing->id;
                $DB->update_record(self::SNAPSHOT_TABLE, $record);
            } else {
                $record->id = $DB->insert_record(self::SNAPSHOT_TABLE, $record);
            }

            $snapshots[] = $record;
        }

        self::delete_stale_snapshots($programid, $semester, $courseids);

        return $snapshots;
    }

    public static function get_snapshots($programid, $semester) {
        global $DB;

        return $DB->get_records(self::SNAPSHOT_TABLE, [
            'programid' => $programid,
            'semester' => $semester,
        ], 'courseid ASC');
    }

    public static function invalidate($programid = null, $semester = null) {
        global $DB;

        $params = [];
        $where = '1=1';

        if ($programid !== null) {
            $where .= ' AND programid = :programid';
            $params['programid'] = (int)$programid;
        }

        if ($semester !== null) {
            $where .= ' AND semester = :semester';
            $params['semester'] = $semester;
        }

        $DB->delete_records_select(self::SNAPSHOT_TABLE, $where, $params);
    }

    private static function delete_stale_snapshots($programid, $semester, array $courseids) {
        global $DB;

        if (empty($courseids)) {
            $DB->delete_records(self::SNAPSHOT_TABLE, [
                'programid' => $programid,
                'semester' => $semester,
            ]);
            return;
        }

        list($insql, $params) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'courseid', false);
        $params['programid'] = $programid;
        $params['semester'] = $semester;

        $DB->delete_records_select(
            self::SNAPSHOT_TABLE,
            'programid = :programid AND semester = :semester AND courseid ' . $insql,
            $params
        );
    }
}
