<?php
define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->libdir . '/gradelib.php');

use local_academicpanel\local\mapping_repository;

list($options, $unrecognized) = cli_get_params([
    'reset' => 0,
    'confirm' => 0,
    'help' => false,
], [
    'h' => 'help',
]);

if ($options['help']) {
    echo "Seed local demo data for local_academicpanel.\n";
    echo "Usage: php local/academicpanel/cli/seed_demo.php --reset=1 --confirm=1\n";
    echo "Requires developer debugging enabled OR --confirm=1 to run.\n";
    exit(0);
}

$isdeveloper = debugging('', DEBUG_DEVELOPER);
$isphpunit = defined('PHPUNIT_TEST') && PHPUNIT_TEST;
$isbehat = defined('BEHAT_SITE_RUNNING') && BEHAT_SITE_RUNNING;
$isconfirmed = !empty($options['confirm']);

if (!$isdeveloper && !$isphpunit && !$isbehat && !$isconfirmed) {
    cli_error('Refusing to seed demo data. Enable DEBUG_DEVELOPER or pass --confirm=1 to override.');
}

$component = 'local_academicpanel_demo';

if (!empty($options['reset'])) {
    local_academicpanel_seed_reset($component);
}

$nutritionprogramid = mapping_repository::upsert_program('Nutrição', 'nutricao');
$physioprogramid = mapping_repository::upsert_program('Fisioterapia', 'fisioterapia');
local_academicpanel_seed_rule($nutritionprogramid, $component);
local_academicpanel_seed_rule($physioprogramid, $component);

$coordinator = local_academicpanel_seed_user('coord.nutricao', 'Coordenador', 'Nutrição', $component);
mapping_repository::add_coordinator($nutritionprogramid, $coordinator->id);

$students = [];
for ($i = 1; $i <= 12; $i++) {
    $students[] = local_academicpanel_seed_user('student.ap' . $i, 'Aluno', 'Demo ' . $i, $component);
}

local_academicpanel_seed_semester('2026.1', $nutritionprogramid, $physioprogramid, $students, $component, [
    'nutrition' => [
        ['Anatomia 2026.1', 'NUT-ANA-2026-1', [9, 8, 8, 6, 5, null, null, 10, 4, 9, null, 3], [0, 1, 2, 7, 9], [3, 4, 8]],
        ['Bioquímica 2026.1', 'NUT-BIO-2026-1', [8, 8, 9, 9, 8, 6, 6, 10, 9, null, null, null], [0, 1, 2, 3, 4, 7, 8], [5, 6]],
    ],
    'physio' => [
        ['Fisiologia 2026.1', 'FIS-FIS-2026-1', [5, 5, 6, 6, 8, 8, null, null, 9, 4, 3, null], [4, 5, 8], [0, 1, 2, 3, 9, 10]],
    ],
]);

local_academicpanel_seed_semester('2025.2', $nutritionprogramid, $physioprogramid, $students, $component, [
    'nutrition' => [
        ['Anatomia 2025.2', 'NUT-ANA-2025-2', [8, 8, 9, 8, 6, 6, 5, 9, null, 8, 4, null], [0, 1, 2, 3, 7, 9], [4, 5, 6, 10]],
        ['Bioquímica 2025.2', 'NUT-BIO-2025-2', [6, 8, 8, 8, 9, 9, null, 5, 6, null, 8, 8], [1, 2, 3, 4, 5, 10, 11], [0, 7, 8]],
        ['Histologia 2025.2', 'NUT-HIS-2025-2', [9, 9, 8, 8, 8, 6, null, 10, 8, 5, null, 8], [0, 1, 2, 3, 4, 7, 8, 11], [5, 9]],
    ],
    'physio' => [
        ['Fisiologia 2025.2', 'FIS-FIS-2025-2', [8, 8, 8, 6, 6, 5, 9, null, null, 8, 8, 4], [0, 1, 2, 6, 9, 10], [3, 4, 5, 11]],
        ['Cinesiologia 2025.2', 'FIS-CIN-2025-2', [9, 8, 8, 8, 8, 8, 6, 6, null, 5, 8, null], [0, 1, 2, 3, 4, 5, 10], [6, 7, 9]],
    ],
]);

echo "Seed completed.\n";
echo "Programs: Nutrição, Fisioterapia\n";
echo "Semesters: 2026.1, 2025.2\n";

function local_academicpanel_seed_semester($semestername, $nutritionprogramid, $physioprogramid, array $students, $component, array $courses) {
    $semester = local_academicpanel_seed_category($semestername, 0, $component);
    $nutrition = local_academicpanel_seed_category('Nutrição', $semester->id, $component);
    $physio = local_academicpanel_seed_category('Fisioterapia', $semester->id, $component);

    mapping_repository::upsert_category_mapping($nutritionprogramid, $nutrition->id, $semestername, 'manual');
    mapping_repository::upsert_category_mapping($physioprogramid, $physio->id, $semestername, 'manual');

    foreach ($courses['nutrition'] as $courseinfo) {
        $course = local_academicpanel_seed_course($courseinfo[0], $courseinfo[1], $nutrition->id, $component);
        local_academicpanel_seed_enrol_grade_and_participation($course, $students, $courseinfo[2], $courseinfo[3], $courseinfo[4]);
    }

    foreach ($courses['physio'] as $courseinfo) {
        $course = local_academicpanel_seed_course($courseinfo[0], $courseinfo[1], $physio->id, $component);
        local_academicpanel_seed_enrol_grade_and_participation($course, $students, $courseinfo[2], $courseinfo[3], $courseinfo[4]);
    }
}

function local_academicpanel_seed_rule($programid, $component) {
    global $DB;

    $now = time();
    $record = $DB->get_record(mapping_repository::RULE_TABLE, ['programid' => $programid]);
    if ($record) {
        $record->gradecutoff = 7;
        $record->rolesincluded = 'student';
        $record->engagementfallback = 'completion';
        $record->timemodified = $now;
        $DB->update_record(mapping_repository::RULE_TABLE, $record);
        return;
    }

    $id = $DB->insert_record(mapping_repository::RULE_TABLE, (object)[
        'programid' => $programid,
        'gradecutoff' => 7,
        'rolesincluded' => 'student',
        'engagementfallback' => 'completion',
        'timecreated' => $now,
        'timemodified' => $now,
    ]);
    local_academicpanel_seed_mark($component, mapping_repository::RULE_TABLE, $id);
}

function local_academicpanel_seed_category($name, $parentid, $component) {
    global $DB;

    $existing = $DB->get_record('course_categories', ['name' => $name, 'parent' => $parentid]);
    if ($existing) {
        return $existing;
    }

    $category = \core_course_category::create((object)[
        'name' => $name,
        'parent' => $parentid,
        'idnumber' => $component . '_' . $parentid . '_' . strtolower(preg_replace('/[^a-z0-9]+/i', '_', $name)),
    ]);
    local_academicpanel_seed_mark($component, 'course_categories', $category->id);
    return $category;
}

function local_academicpanel_seed_course($fullname, $shortname, $categoryid, $component) {
    global $DB;

    $existing = $DB->get_record('course', ['shortname' => $shortname]);
    if ($existing) {
        return $existing;
    }

    $course = create_course((object)[
        'fullname' => $fullname,
        'shortname' => $shortname,
        'category' => $categoryid,
        'visible' => 1,
    ]);
    local_academicpanel_seed_mark($component, 'course', $course->id);
    return $course;
}

function local_academicpanel_seed_user($username, $firstname, $lastname, $component) {
    global $DB;

    $existing = $DB->get_record('user', ['username' => $username, 'deleted' => 0]);
    if ($existing) {
        return $existing;
    }

    $user = (object)[
        'username' => $username,
        'firstname' => $firstname,
        'lastname' => $lastname,
        'email' => $username . '@example.test',
        'auth' => 'manual',
        'confirmed' => 1,
        'mnethostid' => 1,
        'password' => hash_internal_user_password('ChangeMe123!'),
    ];
    $user->id = user_create_user($user, false, false);
    local_academicpanel_seed_mark($component, 'user', $user->id);
    return $user;
}

function local_academicpanel_seed_enrol_grade_and_participation($course, array $students, array $grades, array $completed, array $abandoned) {
    global $DB;

    local_academicpanel_seed_enable_completion($course);

    $studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
    $instances = enrol_get_instances($course->id, true);
    $manual = null;
    foreach ($instances as $instance) {
        if ($instance->enrol === 'manual') {
            $manual = $instance;
            break;
        }
    }

    if (!$manual) {
        return;
    }

    $plugin = enrol_get_plugin('manual');
    foreach ($students as $index => $student) {
        $plugin->enrol_user($manual, $student->id, $studentrole->id);
        if (array_key_exists($index, $grades) && $grades[$index] !== null) {
            local_academicpanel_seed_final_grade($course->id, $student->id, $grades[$index]);
        }
        local_academicpanel_seed_participation($course->id, $student->id, $index, $completed, $abandoned);
    }
}

function local_academicpanel_seed_enable_completion($course) {
    global $DB;

    if (empty($course->enablecompletion)) {
        $DB->set_field('course', 'enablecompletion', 1, ['id' => $course->id]);
        rebuild_course_cache($course->id, true);
    }
}

function local_academicpanel_seed_participation($courseid, $userid, $studentindex, array $completed, array $abandoned) {
    $accessed = in_array($studentindex, $completed, true) || in_array($studentindex, $abandoned, true);
    local_academicpanel_seed_last_access($courseid, $userid, $accessed);
    local_academicpanel_seed_course_completion($courseid, $userid, in_array($studentindex, $completed, true));
}

function local_academicpanel_seed_last_access($courseid, $userid, $accessed) {
    global $DB;

    $params = ['courseid' => $courseid, 'userid' => $userid];
    if (!$accessed) {
        $DB->delete_records('user_lastaccess', $params);
        return;
    }

    $now = time();
    $record = $DB->get_record('user_lastaccess', $params);
    if ($record) {
        $record->timeaccess = $now;
        $DB->update_record('user_lastaccess', $record);
        return;
    }

    $DB->insert_record('user_lastaccess', (object)[
        'userid' => $userid,
        'courseid' => $courseid,
        'timeaccess' => $now,
    ]);
}

function local_academicpanel_seed_course_completion($courseid, $userid, $complete) {
    global $DB;

    $now = time();
    $params = ['course' => $courseid, 'userid' => $userid];
    $record = $DB->get_record('course_completions', $params);
    if ($record) {
        $record->timeenrolled = $record->timeenrolled ? $record->timeenrolled : $now;
        $record->timestarted = $record->timestarted ? $record->timestarted : $now;
        $record->timecompleted = $complete ? $now : null;
        $record->reaggregate = 0;
        $DB->update_record('course_completions', $record);
        return;
    }

    $DB->insert_record('course_completions', (object)[
        'userid' => $userid,
        'course' => $courseid,
        'timeenrolled' => $now,
        'timestarted' => $now,
        'timecompleted' => $complete ? $now : null,
        'reaggregate' => 0,
    ]);
}

function local_academicpanel_seed_final_grade($courseid, $userid, $grade) {
    global $DB;

    $gradeitem = \grade_item::fetch_course_item($courseid);

    $now = time();
    $record = $DB->get_record('grade_grades', ['itemid' => $gradeitem->id, 'userid' => $userid]);
    if ($record) {
        $record->rawgrade = $grade;
        $record->finalgrade = $grade;
        $record->timemodified = $now;
        $DB->update_record('grade_grades', $record);
        return;
    }

    $DB->insert_record('grade_grades', (object)[
        'itemid' => $gradeitem->id,
        'userid' => $userid,
        'rawgrade' => $grade,
        'rawgrademax' => 10,
        'rawgrademin' => 0,
        'finalgrade' => $grade,
        'timecreated' => $now,
        'timemodified' => $now,
    ]);
}

function local_academicpanel_seed_mark($component, $itemtype, $itemid) {
    global $DB;

    $DB->insert_record('local_acpanel_seed', (object)[
        'component' => $component,
        'itemtype' => $itemtype,
        'itemid' => $itemid,
        'timecreated' => time(),
    ]);
}

function local_academicpanel_seed_reset($component) {
    global $DB;

    $records = $DB->get_records('local_acpanel_seed', ['component' => $component], 'id DESC');
    foreach ($records as $record) {
        if ($record->itemtype === 'user') {
            delete_user($DB->get_record('user', ['id' => $record->itemid]));
        } else if ($record->itemtype === 'course') {
            delete_course($record->itemid, false);
        } else if ($record->itemtype === 'course_categories') {
            $category = \core_course_category::get($record->itemid, IGNORE_MISSING, true);
            if ($category) {
                $category->delete_full(false);
            }
        } else if ($record->itemtype === mapping_repository::RULE_TABLE) {
            $DB->delete_records(mapping_repository::RULE_TABLE, ['id' => $record->itemid]);
        }
        $DB->delete_records('local_acpanel_seed', ['id' => $record->id]);
    }
}
