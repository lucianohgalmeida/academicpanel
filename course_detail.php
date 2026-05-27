<?php
require_once(__DIR__ . '/../../config.php');

use local_academicpanel\local\mapping_repository;
use local_academicpanel\local\indicator_repository;
use local_academicpanel\local\indicator_calculator;

require_login();

$courseid = required_param('courseid', PARAM_INT);
$programid = required_param('programid', PARAM_INT);
$semester = required_param('semester', PARAM_TEXT);
$export = optional_param('export', '', PARAM_ALPHA);

$context = context_system::instance();
$canviewall = has_capability('local/academicpanel:viewall', $context);
$canviewassigned = has_capability('local/academicpanel:viewassigned', $context);

if (!$canviewall && !$canviewassigned) {
    require_capability('local/academicpanel:viewall', $context);
}

$programs = mapping_repository::get_visible_programs($USER->id);
if (!isset($programs[$programid])) {
    throw new required_capability_exception($context,
        'local/academicpanel:viewassigned', 'nopermissions', '');
}

$mappings = mapping_repository::get_mappings($programid, $semester);
$validcourses = indicator_repository::get_courses_for_mappings($mappings);
if (!isset($validcourses[$courseid])) {
    throw new required_capability_exception($context,
        'local/academicpanel:viewassigned', 'nopermissions', '');
}

$course = $validcourses[$courseid];

$rule = mapping_repository::get_rule($programid);

if ($export === 'xlsx') {
    require_once($CFG->libdir . '/excellib.class.php');

    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    ob_start();

    $students = indicator_repository::get_course_students_detail($course, $rule);

    ob_end_clean();

    $filename = clean_filename($course->shortname . '_' . $semester) . '.xlsx';

    \core\session\manager::write_close();

    $workbook = new MoodleExcelWorkbook($filename);
    $workbook->send($filename);

    $sheetname = clean_param($course->shortname, PARAM_ALPHANUMEXT);
    if ($sheetname === '') {
        $sheetname = 'Sheet1';
    }
    $sheetname = substr($sheetname, 0, 31);
    $sheet = $workbook->add_worksheet($sheetname);

    $headerformat = $workbook->add_format([
        'bold' => 1,
        'bg_color' => '#1e3a5f',
        'color' => '#ffffff',
        'border' => 1,
    ]);
    $cellformat = $workbook->add_format(['border' => 1]);
    $gradeformat = $workbook->add_format(['border' => 1, 'num_format' => '0.00']);

    $headers = [
        get_string('fullnameuser'),
        get_string('email'),
        get_string('finalgrade', 'local_academicpanel'),
        get_string('status'),
        get_string('coursedaccessed', 'local_academicpanel'),
        get_string('lastaccess'),
        get_string('activitiescompleted', 'local_academicpanel'),
        get_string('engagement', 'local_academicpanel'),
    ];

    $col = 0;
    foreach ($headers as $h) {
        $sheet->write_string(0, $col, $h, $headerformat);
        $col++;
    }

    $rowidx = 1;
    foreach ($students as $student) {
        $sheet->write_string($rowidx, 0, $student->fullname, $cellformat);
        $sheet->write_string($rowidx, 1, $student->email, $cellformat);

        if ($student->hasgrade) {
            $sheet->write_number($rowidx, 2, (float)$student->finalgrade, $gradeformat);
        } else {
            $sheet->write_string($rowidx, 2, '-', $cellformat);
        }

        $sheet->write_string($rowidx, 3,
            get_string('status_' . $student->status, 'local_academicpanel'), $cellformat);
        $sheet->write_string($rowidx, 4,
            $student->accessed ? get_string('yes') : get_string('no'), $cellformat);
        $sheet->write_string($rowidx, 5,
            $student->lastaccess
                ? userdate($student->lastaccess, get_string('strftimedatetime', 'langconfig'))
                : '-',
            $cellformat
        );
        $sheet->write_number($rowidx, 6, (int)$student->activitiescompleted, $cellformat);
        $sheet->write_string($rowidx, 7,
            $student->engaged
                ? get_string('engaged', 'local_academicpanel')
                : get_string('notengaged', 'local_academicpanel'),
            $cellformat
        );
        $rowidx++;
    }

    $sheet->set_column(0, 0, 32);
    $sheet->set_column(1, 1, 32);
    $sheet->set_column(2, 2, 12);
    $sheet->set_column(3, 3, 14);
    $sheet->set_column(4, 4, 10);
    $sheet->set_column(5, 5, 22);
    $sheet->set_column(6, 6, 12);
    $sheet->set_column(7, 7, 16);

    $workbook->close();
    exit;
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/academicpanel/course_detail.php', [
    'courseid' => $courseid,
    'programid' => $programid,
    'semester' => $semester,
]));
$PAGE->set_title(format_string($course->fullname));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->requires->css('/local/academicpanel/styles.css');

$students = indicator_repository::get_course_students_detail($course, $rule);

$summaryrows = [];
foreach ($students as $student) {
    $summaryrows[] = [
        'enrolled' => 1,
        'withgrade' => $student->hasgrade ? 1 : 0,
        'approved' => $student->status === 'approved' ? 1 : 0,
        'failed' => $student->status === 'failed' ? 1 : 0,
        'engaged' => $student->engaged ? 1 : 0,
        'neveraccessed' => !$student->accessed ? 1 : 0,
        'abandoned' => (!$student->engaged && $student->accessed) ? 1 : 0,
    ];
}
$summary = indicator_calculator::merge($summaryrows);

echo $OUTPUT->header();
echo html_writer::start_div('local-academicpanel-coursedetail');

echo html_writer::start_div('local-academicpanel-detail-actions mb-3');
echo html_writer::link(new moodle_url('/local/academicpanel/index.php', [
    'programid' => $programid,
    'semester' => $semester,
]), '← ' . get_string('pluginname', 'local_academicpanel'),
    ['class' => 'btn btn-secondary']);

echo ' ';

echo html_writer::link(new moodle_url($PAGE->url, ['export' => 'xlsx']),
    get_string('exportexcel', 'local_academicpanel'),
    ['class' => 'btn btn-primary']);
echo html_writer::end_div();

echo $OUTPUT->heading(format_string($course->fullname));

echo html_writer::div(
    html_writer::span(get_string('program', 'local_academicpanel') . ': ' .
        format_string($programs[$programid]->name), 'mr-3') . ' ' .
    html_writer::span(get_string('semester', 'local_academicpanel') . ': ' . s($semester), 'mr-3') . ' ' .
    html_writer::span(get_string('gradecutoff', 'local_academicpanel') . ': ' .
        format_float($rule->gradecutoff, 2), 'mr-3'),
    'local-academicpanel-course-meta mb-3 text-muted'
);

echo html_writer::start_div('local-academicpanel-cards');
echo local_academicpanel_summary_card(get_string('enrolled', 'local_academicpanel'), $summary['enrolled']);
echo local_academicpanel_summary_card(get_string('withgrade', 'local_academicpanel'), $summary['withgrade']);
echo local_academicpanel_summary_card(get_string('approved', 'local_academicpanel'), $summary['approved']);
echo local_academicpanel_summary_card(get_string('failed', 'local_academicpanel'), $summary['failed']);
echo local_academicpanel_summary_card(get_string('engaged', 'local_academicpanel'), $summary['engaged']);
echo local_academicpanel_summary_card(get_string('neveraccessed', 'local_academicpanel'), $summary['neveraccessed']);
echo html_writer::end_div();

if (empty($students)) {
    echo $OUTPUT->notification(get_string('nostudentsenrolled', 'local_academicpanel'),
        \core\output\notification::NOTIFY_INFO);
    echo html_writer::end_div();
    echo $OUTPUT->footer();
    exit;
}

$table = new html_table();
$table->attributes['class'] = 'generaltable local-academicpanel-table';
$table->head = [
    get_string('fullnameuser'),
    get_string('email'),
    get_string('finalgrade', 'local_academicpanel'),
    get_string('status'),
    get_string('coursedaccessed', 'local_academicpanel'),
    get_string('lastaccess'),
    get_string('activitiescompleted', 'local_academicpanel'),
    get_string('engagement', 'local_academicpanel'),
];

$statusclasses = [
    'approved' => 'badge badge-success',
    'failed' => 'badge badge-danger',
    'ungraded' => 'badge badge-secondary',
];

foreach ($students as $student) {
    $grade = $student->hasgrade ? format_float($student->finalgrade, 2) : '-';
    $statuslabel = get_string('status_' . $student->status, 'local_academicpanel');
    $statushtml = html_writer::span($statuslabel, $statusclasses[$student->status]);

    $accessed = $student->accessed
        ? html_writer::span(get_string('yes'), 'badge badge-success')
        : html_writer::span(get_string('no'), 'badge badge-danger');

    $lastaccess = $student->lastaccess
        ? userdate($student->lastaccess, get_string('strftimedatetime', 'langconfig'))
        : '-';

    $engagement = $student->engaged
        ? html_writer::span(get_string('engaged', 'local_academicpanel'), 'badge badge-success')
        : html_writer::span(get_string('notengaged', 'local_academicpanel'), 'badge badge-secondary');

    $table->data[] = [
        s($student->fullname),
        s($student->email),
        $grade,
        $statushtml,
        $accessed,
        $lastaccess,
        (int)$student->activitiescompleted,
        $engagement,
    ];
}

echo html_writer::table($table);

echo html_writer::end_div();
echo $OUTPUT->footer();

function local_academicpanel_summary_card($label, $value) {
    return html_writer::div(
        html_writer::div(s($label), 'local-academicpanel-card-label') .
        html_writer::div((string)(int)$value, 'local-academicpanel-card-value'),
        'local-academicpanel-card'
    );
}
