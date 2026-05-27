<?php
namespace local_academicpanel\local;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/enrollib.php');

class indicator_repository {

    public static function get_courses_for_mappings(array $mappings) {
        global $DB;

        $categoryids = [];
        foreach ($mappings as $mapping) {
            $categoryids[] = (int)$mapping->categoryid;
            $category = \core_course_category::get($mapping->categoryid, IGNORE_MISSING, true);
            if ($category) {
                foreach ($category->get_all_children_ids() as $childid) {
                    $categoryids[] = (int)$childid;
                }
            }
        }

        $categoryids = array_values(array_unique($categoryids));
        if (empty($categoryids)) {
            return [];
        }

        list($insql, $params) = $DB->get_in_or_equal($categoryids, SQL_PARAMS_NAMED);
        $params['siteid'] = SITEID;

        return $DB->get_records_select('course', 'category ' . $insql . ' AND id <> :siteid', $params, 'fullname ASC');
    }

    public static function get_course_students_detail($course, $rule) {
        global $DB;

        $context = \context_course::instance($course->id);
        $users = self::get_student_users_detailed($context, $rule->rolesincluded);
        $accesses = $DB->get_records_menu('user_lastaccess', ['courseid' => $course->id], '', 'userid,timeaccess');
        $grades = self::get_final_grades($course->id);
        $activitymap = self::get_activity_completion_map($course->id);
        $logmap = self::get_activity_log_map($course->id);
        $coursecompletionmap = self::get_course_completion_map($course->id);
        $cutoff = (float)$rule->gradecutoff;

        $rows = [];
        foreach ($users as $user) {
            $accessed = !empty($accesses[$user->id]);
            $hasgrade = array_key_exists($user->id, $grades) && $grades[$user->id] !== null;
            $finalgrade = $hasgrade ? (float)$grades[$user->id] : null;
            $activitycount = isset($activitymap[$user->id]) ? (int)$activitymap[$user->id] : 0;
            $hasactivitylog = !empty($logmap[$user->id]);
            $coursecompleted = !empty($coursecompletionmap[$user->id]);
            $engaged = self::is_engaged($accessed, $activitycount, $hasactivitylog, $coursecompleted, $hasgrade);

            if ($hasgrade) {
                $status = ($finalgrade >= $cutoff) ? 'approved' : 'failed';
            } else {
                $status = 'ungraded';
            }

            $rows[] = (object)[
                'userid' => (int)$user->id,
                'fullname' => fullname($user),
                'email' => $user->email,
                'finalgrade' => $finalgrade,
                'hasgrade' => $hasgrade,
                'accessed' => $accessed,
                'lastaccess' => $accessed ? (int)$accesses[$user->id] : null,
                'activitiescompleted' => $activitycount,
                'engaged' => $engaged,
                'status' => $status,
            ];
        }

        usort($rows, function ($a, $b) {
            return strcmp($a->fullname, $b->fullname);
        });

        return $rows;
    }

    public static function get_course_metrics($course, $rule) {
        global $DB;

        $context = \context_course::instance($course->id);
        $users = self::get_student_users($context, $rule->rolesincluded);
        $accesses = $DB->get_records_menu('user_lastaccess', ['courseid' => $course->id], '', 'userid,timeaccess');
        $grades = self::get_final_grades($course->id);
        $activitymap = self::get_activity_completion_map($course->id);
        $logmap = self::get_activity_log_map($course->id);
        $coursecompletionmap = self::get_course_completion_map($course->id);

        $students = [];
        foreach ($users as $user) {
            $accessed = !empty($accesses[$user->id]);
            $hasgrade = array_key_exists($user->id, $grades) && $grades[$user->id] !== null;
            $activitycount = isset($activitymap[$user->id]) ? (int)$activitymap[$user->id] : 0;
            $hasactivitylog = !empty($logmap[$user->id]);
            $coursecompleted = !empty($coursecompletionmap[$user->id]);
            $engaged = self::is_engaged($accessed, $activitycount, $hasactivitylog, $coursecompleted, $hasgrade);

            $students[] = [
                'finalgrade' => $hasgrade ? $grades[$user->id] : null,
                'hasgrade' => $hasgrade,
                'accessed' => $accessed,
                'engaged' => $engaged,
            ];
        }

        return indicator_calculator::calculate($students, $rule->gradecutoff);
    }

    private static function get_student_users($context, $rolesincluded) {
        global $DB;

        $shortnames = array_filter(array_map('trim', explode(',', $rolesincluded)));
        if (empty($shortnames)) {
            $shortnames = ['student'];
        }

        list($insql, $params) = $DB->get_in_or_equal($shortnames, SQL_PARAMS_NAMED);
        $roles = $DB->get_records_select('role', 'shortname ' . $insql, $params, '', 'id,shortname');
        $roleids = array_keys($roles);
        if (empty($roleids)) {
            return [];
        }

        return get_enrolled_users($context, '', 0, 'u.id', null, 0, 0, true, $roleids);
    }

    private static function get_student_users_detailed($context, $rolesincluded) {
        global $DB;

        $shortnames = array_filter(array_map('trim', explode(',', $rolesincluded)));
        if (empty($shortnames)) {
            $shortnames = ['student'];
        }

        list($insql, $params) = $DB->get_in_or_equal($shortnames, SQL_PARAMS_NAMED);
        $roles = $DB->get_records_select('role', 'shortname ' . $insql, $params, '', 'id,shortname');
        $roleids = array_keys($roles);
        if (empty($roleids)) {
            return [];
        }

        $namefields = \core_user\fields::get_name_fields();
        $userfields = array_map(function ($field) {
            return 'u.' . $field;
        }, $namefields);
        $select = 'u.id, u.email, ' . implode(', ', $userfields);

        return get_enrolled_users($context, '', 0, $select, null, 0, 0, true, $roleids);
    }

    private static function get_final_grades($courseid) {
        global $DB;

        $gradeitem = $DB->get_record('grade_items', [
            'courseid' => $courseid,
            'itemtype' => 'course',
        ]);

        if (!$gradeitem) {
            return [];
        }

        return $DB->get_records_menu('grade_grades', ['itemid' => $gradeitem->id], '', 'userid,finalgrade');
    }

    /**
     * Conta atividades completadas por usuário no curso.
     * Engajamento = acessou + fez atividades.
     */
    private static function get_activity_completion_map($courseid) {
        global $DB;

        $sql = "SELECT cmc.userid, COUNT(1) AS completed
                  FROM {course_modules_completion} cmc
                  JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
                 WHERE cm.course = :courseid
                   AND cmc.completionstate > 0
              GROUP BY cmc.userid";

        return $DB->get_records_sql_menu($sql, ['courseid' => $courseid]);
    }

    /**
     * Map de usuários que tiveram atividade (create/update) registrada nos logs do curso.
     * Cobre cursos sem completion configurada — submissões, posts, etc.
     */
    private static function get_activity_log_map($courseid) {
        global $DB;

        if (!$DB->get_manager()->table_exists('logstore_standard_log')) {
            return [];
        }

        $sql = "SELECT userid, COUNT(1) AS active
                  FROM {logstore_standard_log}
                 WHERE courseid = :courseid
                   AND crud IN ('c', 'u')
                   AND userid > 0
              GROUP BY userid";

        return $DB->get_records_sql_menu($sql, ['courseid' => $courseid]);
    }

    /**
     * Map de usuários com curso concluído (course_completions.timecompleted).
     * Sinal forte de engajamento — completar o curso implica ter realizado atividades.
     */
    private static function get_course_completion_map($courseid) {
        global $DB;

        $sql = "SELECT userid, timecompleted
                  FROM {course_completions}
                 WHERE course = :courseid
                   AND timecompleted IS NOT NULL";

        return $DB->get_records_sql_menu($sql, ['courseid' => $courseid]);
    }

    /**
     * Engajado = acessou + algum sinal de atividade (independente de aprovação):
     *  - completou pelo menos uma atividade (course_modules_completion),
     *  - tem ação create/update em logs do curso,
     *  - concluiu o curso (course_completions.timecompleted),
     *  - tem nota lançada (sinal de atividade avaliada).
     */
    private static function is_engaged($accessed, $activitycount, $hasactivitylog, $coursecompleted = false, $hasgrade = false) {
        if (!$accessed) {
            return false;
        }

        return $coursecompleted || $activitycount > 0 || $hasactivitylog || $hasgrade;
    }
}
