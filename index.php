<?php
require_once(__DIR__ . '/../../config.php');

use local_academicpanel\local\indicator_calculator;
use local_academicpanel\local\dashboard_chart_data;
use local_academicpanel\local\mapping_repository;
use local_academicpanel\local\snapshot_service;

require_login();

$context = context_system::instance();
$canviewall = has_capability('local/academicpanel:viewall', $context);
$canviewassigned = has_capability('local/academicpanel:viewassigned', $context);
if (!$canviewall && !$canviewassigned) {
    require_capability('local/academicpanel:viewall', $context);
}

$programid = optional_param('programid', 0, PARAM_INT);
$semester = optional_param('semester', '', PARAM_TEXT);
$comparesemester = optional_param('comparesemester', '', PARAM_TEXT);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/academicpanel/index.php'));
$PAGE->set_title(get_string('pluginname', 'local_academicpanel'));
$PAGE->set_heading(get_string('pluginname', 'local_academicpanel'));
$PAGE->requires->css('/local/academicpanel/styles.css');

$programs = mapping_repository::get_visible_programs($USER->id);
if (!$programid && !empty($programs)) {
    $first = reset($programs);
    $programid = $first->id;
}

if ($programid && !isset($programs[$programid])) {
    throw new required_capability_exception($context, 'local/academicpanel:viewassigned', 'nopermissions', '');
}

if ($programid && $semester === '') {
    $semester = local_academicpanel_first_semester($programid);
}

echo $OUTPUT->header();
echo html_writer::start_div('local-academicpanel-header');
if (has_capability('local/academicpanel:manage', $context)) {
    echo html_writer::div(
        html_writer::link(new moodle_url('/local/academicpanel/manage.php'), get_string('managemappings', 'local_academicpanel'), ['class' => 'btn btn-secondary']) . ' ' .
        html_writer::link(new moodle_url('/local/academicpanel/rules.php'), get_string('managerules', 'local_academicpanel'), ['class' => 'btn btn-secondary']),
        'local-academicpanel-actions'
    );
}
echo html_writer::end_div();

if (empty($programs)) {
    if ($canviewall) {
        $message = get_string('noprogramsyet_admin', 'local_academicpanel');
        if (has_capability('local/academicpanel:manage', $context)) {
            $message .= ' ' . html_writer::link(
                new moodle_url('/local/academicpanel/manage.php'),
                get_string('managemappings', 'local_academicpanel')
            );
        }
        echo $OUTPUT->notification($message, \core\output\notification::NOTIFY_INFO);
    } else {
        echo $OUTPUT->notification(
            get_string('noprogramsyet_coordinator', 'local_academicpanel'),
            \core\output\notification::NOTIFY_WARNING
        );
    }
    echo $OUTPUT->footer();
    exit;
}

local_academicpanel_render_filters($programs, $programid, $semester, $comparesemester);

$snapshots = $programid && $semester !== '' ? snapshot_service::get_snapshots($programid, $semester) : [];
$compare = $programid && $comparesemester !== '' ? snapshot_service::get_snapshots($programid, $comparesemester) : [];
$summary = indicator_calculator::merge(local_academicpanel_metric_rows($snapshots));
$comparesummary = indicator_calculator::merge(local_academicpanel_metric_rows($compare));
$chartcourses = local_academicpanel_courses_for_snapshots($snapshots);
$chartdata = dashboard_chart_data::build($snapshots, $chartcourses);

echo html_writer::start_div('local-academicpanel-cards');
echo local_academicpanel_metric_card(get_string('enrolled', 'local_academicpanel'), $summary['enrolled']);
echo local_academicpanel_metric_card(get_string('approvalamonggraded', 'local_academicpanel'), local_academicpanel_percent($summary['approvalamonggraded']), local_academicpanel_delta($summary['approvalamonggraded'], $comparesummary['approvalamonggraded']));
echo local_academicpanel_metric_card(get_string('approvalamongenrolled', 'local_academicpanel'), local_academicpanel_percent($summary['approvalamongenrolled']), local_academicpanel_delta($summary['approvalamongenrolled'], $comparesummary['approvalamongenrolled']));
echo local_academicpanel_metric_card(get_string('engagementrate', 'local_academicpanel'), local_academicpanel_percent($summary['engagementrate']), local_academicpanel_delta($summary['engagementrate'], $comparesummary['engagementrate']));
echo local_academicpanel_metric_card(get_string('abandonmentrate', 'local_academicpanel'), local_academicpanel_percent($summary['abandonmentrate']), local_academicpanel_delta($summary['abandonmentrate'], $comparesummary['abandonmentrate']));
echo html_writer::end_div();

echo local_academicpanel_render_charts($chartdata);
echo $OUTPUT->heading(get_string('discipline', 'local_academicpanel'), 3);
echo local_academicpanel_snapshot_table($snapshots, $compare);
$PAGE->requires->js_call_amd('local_academicpanel/dashboard', 'init');
echo $OUTPUT->footer();

function local_academicpanel_render_filters($programs, $programid, $semester, $comparesemester) {
    $programoptions = [];
    foreach ($programs as $program) {
        $programoptions[$program->id] = format_string($program->name);
    }

    $semesteroptions = [];
    if ($programid) {
        foreach (local_academicpanel_semesters($programid) as $value) {
            $semesteroptions[$value] = $value;
        }
    }

    $compareoptions = ['' => get_string('comparenone', 'local_academicpanel')];
    foreach ($semesteroptions as $value => $label) {
        if ($value === $semester) {
            continue;
        }
        $compareoptions[$value] = $value;
    }

    echo html_writer::start_tag('form', ['method' => 'get', 'class' => 'local-academicpanel-filters']);

    echo html_writer::start_div('local-academicpanel-filter-group');
    echo html_writer::div(get_string('filtergroup_period', 'local_academicpanel'),
        'local-academicpanel-filter-grouplabel');
    echo html_writer::start_div('local-academicpanel-filter-fields');
    echo html_writer::div(
        html_writer::label(get_string('program', 'local_academicpanel'), 'programid') .
        html_writer::select($programoptions, 'programid', $programid, false, ['id' => 'programid']),
        'local-academicpanel-filter-field'
    );
    echo html_writer::div(
        html_writer::label(get_string('semester', 'local_academicpanel'), 'semester') .
        html_writer::select($semesteroptions, 'semester', $semester, false, ['id' => 'semester']),
        'local-academicpanel-filter-field'
    );
    echo html_writer::end_div();
    echo html_writer::end_div();

    echo html_writer::start_div('local-academicpanel-filter-group local-academicpanel-filter-group-compare');
    echo html_writer::div(get_string('filtergroup_compare', 'local_academicpanel'),
        'local-academicpanel-filter-grouplabel');
    echo html_writer::start_div('local-academicpanel-filter-fields');
    echo html_writer::div(
        html_writer::label(get_string('comparelabel', 'local_academicpanel'), 'comparesemester') .
        html_writer::select($compareoptions, 'comparesemester', $comparesemester, false, ['id' => 'comparesemester']) .
        html_writer::tag('small', get_string('comparehint', 'local_academicpanel'),
            ['class' => 'local-academicpanel-filter-hint']),
        'local-academicpanel-filter-field'
    );
    echo html_writer::end_div();
    echo html_writer::end_div();

    $clearurl = new moodle_url('/local/academicpanel/index.php');
    echo html_writer::div(
        html_writer::empty_tag('input', [
            'type' => 'submit',
            'class' => 'btn btn-primary',
            'value' => get_string('filter'),
        ]) . ' ' .
        html_writer::link($clearurl, get_string('clearfilters', 'local_academicpanel'), [
            'class' => 'btn btn-secondary',
        ]),
        'local-academicpanel-filter-submit'
    );

    echo html_writer::end_tag('form');

    if ($comparesemester !== '' && $semester !== '') {
        echo html_writer::div(
            get_string('compareactive', 'local_academicpanel', (object)[
                'current' => s($semester),
                'previous' => s($comparesemester),
            ]),
            'local-academicpanel-compare-banner alert alert-info'
        );
    }
}

function local_academicpanel_snapshot_table($snapshots, $compare) {
    $courses = local_academicpanel_courses_for_snapshots($snapshots);
    $comparecourses = local_academicpanel_courses_for_snapshots($compare);
    $comparebycourse = [];
    foreach ($compare as $record) {
        $course = isset($comparecourses[$record->courseid]) ? $comparecourses[$record->courseid] : null;
        $comparebycourse[local_academicpanel_course_compare_key($course, $record->courseid)] = indicator_calculator::merge([local_academicpanel_metric_row($record)]);
    }

    $table = new html_table();
    $table->attributes['class'] = 'generaltable local-academicpanel-table';
    $table->head = [
        get_string('discipline', 'local_academicpanel'),
        get_string('enrolled', 'local_academicpanel'),
        get_string('withgrade', 'local_academicpanel'),
        get_string('approvalamonggraded', 'local_academicpanel'),
        get_string('failureamonggraded', 'local_academicpanel'),
        get_string('engagementrate', 'local_academicpanel'),
        get_string('neveraccessedrate', 'local_academicpanel'),
        get_string('abandonmentrate', 'local_academicpanel'),
        get_string('delta', 'local_academicpanel'),
    ];

    foreach ($snapshots as $record) {
        $metrics = indicator_calculator::merge([local_academicpanel_metric_row($record)]);
        $course = isset($courses[$record->courseid]) ? $courses[$record->courseid] : null;
        $comparekey = local_academicpanel_course_compare_key($course, $record->courseid);
        $comparemetrics = isset($comparebycourse[$comparekey]) ? $comparebycourse[$comparekey] : null;
        $coursename = $course ? format_string($course->fullname) : s($record->courseid);

        $detailurl = $course ? new moodle_url('/local/academicpanel/course_detail.php', [
            'courseid' => $course->id,
            'programid' => $record->programid,
            'semester' => $record->semester,
        ]) : null;
        $table->data[] = [
            $detailurl ? html_writer::link($detailurl, $coursename) : $coursename,
            $metrics['enrolled'],
            $metrics['withgrade'],
            local_academicpanel_percent($metrics['approvalamonggraded']),
            local_academicpanel_percent($metrics['failureamonggraded']),
            local_academicpanel_percent($metrics['engagementrate']),
            local_academicpanel_percent($metrics['neveraccessedrate']),
            local_academicpanel_percent($metrics['abandonmentrate']),
            $comparemetrics ? local_academicpanel_delta($metrics['approvalamonggraded'], $comparemetrics['approvalamonggraded']) : '-',
        ];
    }

    if (empty($table->data)) {
        $table->data[] = [get_string('snapshotgenerated', 'local_academicpanel') . ': 0', '', '', '', '', '', '', '', ''];
    }

    return html_writer::table($table);
}

function local_academicpanel_metric_rows($snapshots) {
    $rows = [];
    foreach ($snapshots as $snapshot) {
        $rows[] = local_academicpanel_metric_row($snapshot);
    }
    return $rows;
}

function local_academicpanel_metric_row($record) {
    return [
        'enrolled' => (int)$record->enrolled,
        'withgrade' => (int)$record->withgrade,
        'approved' => (int)$record->approved,
        'failed' => (int)$record->failed,
        'engaged' => (int)$record->engaged,
        'neveraccessed' => (int)$record->neveraccessed,
        'abandoned' => (int)$record->abandoned,
    ];
}

function local_academicpanel_render_charts(array $chartdata) {
    if (empty($chartdata['bars'])) {
        return '';
    }

    return html_writer::div(
        html_writer::div(local_academicpanel_bar_chart($chartdata['bars']), 'local-academicpanel-chart-panel local-academicpanel-chart-panel-wide') .
        html_writer::div(local_academicpanel_donut_chart(get_string('participationstatus', 'local_academicpanel'), [
            ['label' => get_string('engaged', 'local_academicpanel'), 'value' => $chartdata['participation']['engaged'], 'class' => 'local-academicpanel-swatch-engaged'],
            ['label' => get_string('abandoned', 'local_academicpanel'), 'value' => $chartdata['participation']['abandoned'], 'class' => 'local-academicpanel-swatch-abandoned'],
            ['label' => get_string('neveraccessed', 'local_academicpanel'), 'value' => $chartdata['participation']['neveraccessed'], 'class' => 'local-academicpanel-swatch-never'],
        ]), 'local-academicpanel-chart-panel') .
        html_writer::div(local_academicpanel_donut_chart(get_string('academicresult', 'local_academicpanel'), [
            ['label' => get_string('approved', 'local_academicpanel'), 'value' => $chartdata['outcomes']['approved'], 'class' => 'local-academicpanel-swatch-approved'],
            ['label' => get_string('failed', 'local_academicpanel'), 'value' => $chartdata['outcomes']['failed'], 'class' => 'local-academicpanel-swatch-failed'],
            ['label' => get_string('ungraded', 'local_academicpanel'), 'value' => $chartdata['outcomes']['ungraded'], 'class' => 'local-academicpanel-swatch-ungraded'],
        ]), 'local-academicpanel-chart-panel'),
        'local-academicpanel-charts'
    );
}

function local_academicpanel_bar_chart(array $bars) {
    $buttons = [
        'approval' => get_string('approvalamonggraded', 'local_academicpanel'),
        'failure' => get_string('failureamonggraded', 'local_academicpanel'),
        'engagement' => get_string('engagementrate', 'local_academicpanel'),
        'abandonment' => get_string('abandonmentrate', 'local_academicpanel'),
    ];

    $html = html_writer::div(
        html_writer::span(get_string('disciplinecomparison', 'local_academicpanel'), 'local-academicpanel-chart-title') .
        local_academicpanel_chart_buttons($buttons),
        'local-academicpanel-chart-heading'
    );
    $html .= html_writer::start_div('local-academicpanel-bar-chart', ['data-academicpanel-bar-chart' => '1']);

    foreach ($bars as $bar) {
        $value = (float)$bar['approval'];
        $html .= html_writer::div(
            html_writer::div(s($bar['label']), 'local-academicpanel-bar-label') .
            html_writer::div(
                html_writer::div('', 'local-academicpanel-bar-fill', ['style' => '--bar-value: ' . $value . '%;']) .
                html_writer::span(local_academicpanel_percent($value), 'local-academicpanel-bar-value'),
                'local-academicpanel-bar-track'
            ),
            'local-academicpanel-bar-item',
            [
                'data-approval' => local_academicpanel_chart_number($bar['approval']),
                'data-failure' => local_academicpanel_chart_number($bar['failure']),
                'data-engagement' => local_academicpanel_chart_number($bar['engagement']),
                'data-abandonment' => local_academicpanel_chart_number($bar['abandonment']),
            ]
        );
    }

    $html .= html_writer::end_div();
    return $html;
}

function local_academicpanel_chart_buttons(array $buttons) {
    $html = html_writer::start_div('local-academicpanel-chart-tabs');
    foreach ($buttons as $key => $label) {
        $attributes = [
            'type' => 'button',
            'class' => 'local-academicpanel-chart-tab' . ($key === 'approval' ? ' is-active' : ''),
            'data-academicpanel-series' => $key,
        ];
        $html .= html_writer::tag('button', s($label), $attributes);
    }
    $html .= html_writer::end_div();
    return $html;
}

function local_academicpanel_donut_chart($title, array $segments) {
    $total = 0;
    foreach ($segments as $segment) {
        $total += (int)$segment['value'];
    }

    $angle = 0;
    $colors = [
        'local-academicpanel-swatch-engaged' => '#2f855a',
        'local-academicpanel-swatch-abandoned' => '#d97706',
        'local-academicpanel-swatch-never' => '#64748b',
        'local-academicpanel-swatch-approved' => '#2563eb',
        'local-academicpanel-swatch-failed' => '#dc2626',
        'local-academicpanel-swatch-ungraded' => '#7c3aed',
    ];
    $gradient = [];

    foreach ($segments as $segment) {
        $degrees = $total > 0 ? round(((int)$segment['value'] / $total) * 360, 2) : 0;
        $next = $angle + $degrees;
        $color = isset($colors[$segment['class']]) ? $colors[$segment['class']] : '#64748b';
        $gradient[] = $color . ' ' . $angle . 'deg ' . $next . 'deg';
        $angle = $next;
    }

    if ($total === 0) {
        $gradient[] = '#e5e7eb 0deg 360deg';
    }

    $legend = '';
    foreach ($segments as $segment) {
        $rate = $total > 0 ? round(((int)$segment['value'] / $total) * 100, 2) : 0;
        $legend .= html_writer::div(
            html_writer::span('', 'local-academicpanel-swatch ' . $segment['class']) .
            html_writer::span(s($segment['label']), 'local-academicpanel-donut-label') .
            html_writer::span((int)$segment['value'] . ' (' . local_academicpanel_percent($rate) . ')', 'local-academicpanel-donut-value'),
            'local-academicpanel-donut-legend-row'
        );
    }

    return html_writer::div(s($title), 'local-academicpanel-chart-title') .
        html_writer::div(
            html_writer::div((string)$total, 'local-academicpanel-donut-total') .
            html_writer::div(get_string('enrolled', 'local_academicpanel'), 'local-academicpanel-donut-caption'),
            'local-academicpanel-donut',
            ['style' => 'background: conic-gradient(' . implode(', ', $gradient) . ');']
        ) .
        html_writer::div($legend, 'local-academicpanel-donut-legend');
}

function local_academicpanel_metric_card($label, $value, $delta = '') {
    return html_writer::div(
        html_writer::div($label, 'local-academicpanel-card-label') .
        html_writer::div($value, 'local-academicpanel-card-value') .
        html_writer::div($delta, 'local-academicpanel-card-delta'),
        'local-academicpanel-card'
    );
}

function local_academicpanel_percent($value) {
    return format_float($value, 2) . '%';
}

function local_academicpanel_chart_number($value) {
    return str_replace(',', '.', format_float($value, 2, true, false));
}

function local_academicpanel_delta($current, $previous) {
    if ($previous === null || (float)$previous === 0.0) {
        return '-';
    }

    $delta = round((float)$current - (float)$previous, 2);
    $class = $delta >= 0 ? 'local-academicpanel-positive' : 'local-academicpanel-negative';

    return html_writer::span(($delta >= 0 ? '+' : '') . local_academicpanel_percent($delta), $class);
}

function local_academicpanel_first_semester($programid) {
    $semesters = local_academicpanel_semesters($programid);
    if (empty($semesters)) {
        return '';
    }

    return reset($semesters);
}

function local_academicpanel_semesters($programid) {
    global $DB;

    $records = $DB->get_records_sql(
        'SELECT DISTINCT semester
           FROM {local_acpanel_category}
          WHERE programid = :programid
       ORDER BY semester DESC',
        ['programid' => $programid]
    );

    $semesters = [];
    foreach ($records as $record) {
        if ($record->semester !== '') {
            $semesters[$record->semester] = $record->semester;
        }
    }

    return $semesters;
}

function local_academicpanel_courses_for_snapshots($snapshots) {
    global $DB;

    $courseids = [];
    foreach ($snapshots as $snapshot) {
        $courseids[] = (int)$snapshot->courseid;
    }

    if (empty($courseids)) {
        return [];
    }

    list($insql, $params) = $DB->get_in_or_equal(array_unique($courseids), SQL_PARAMS_NAMED);

    return $DB->get_records_select('course', 'id ' . $insql, $params);
}

function local_academicpanel_course_compare_key($course, $fallback) {
    if (!$course) {
        return 'course:' . $fallback;
    }

    $key = strtolower($course->shortname);
    $key = preg_replace('/-\d{4}-\d$/', '', $key);

    if ($key === strtolower($course->shortname)) {
        $key = strtolower($course->fullname);
        $key = preg_replace('/\s+\d{4}\.\d$/', '', $key);
    }

    return $key;
}
