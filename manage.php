<?php
require_once(__DIR__ . '/../../config.php');

use local_academicpanel\form\program_form;
use local_academicpanel\form\mapping_form;
use local_academicpanel\form\coordinator_form;
use local_academicpanel\local\mapping_repository;
use local_academicpanel\local\snapshot_service;

require_login();
$context = context_system::instance();
require_capability('local/academicpanel:manage', $context);

$tab = optional_param('tab', 'programs', PARAM_ALPHA);
$action = optional_param('action', '', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);

if (!in_array($tab, ['programs', 'mappings', 'coordinators'], true)) {
    $tab = 'programs';
}

$baseurl = new moodle_url('/local/academicpanel/manage.php', ['tab' => $tab]);

$PAGE->set_context($context);
$PAGE->set_url($baseurl);
$PAGE->set_title(get_string('managemappings', 'local_academicpanel'));
$PAGE->set_heading(get_string('pluginname', 'local_academicpanel'));
$PAGE->requires->css('/local/academicpanel/styles.css');

if ($action === 'removemapping' && $id) {
    require_sesskey();
    $mapping = $DB->get_record(mapping_repository::CATEGORY_TABLE, ['id' => $id]);
    mapping_repository::delete_mapping($id);
    if ($mapping) {
        snapshot_service::invalidate((int)$mapping->programid, $mapping->semester);
    }
    redirect($baseurl, get_string('mappingremovedmsg', 'local_academicpanel'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

if ($action === 'removecoord' && $id) {
    require_sesskey();
    mapping_repository::deactivate_coordinator($id);
    redirect($baseurl, get_string('coordinatorremovedmsg', 'local_academicpanel'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

if ($action === 'deactivateprogram' && $id) {
    require_sesskey();
    mapping_repository::deactivate_program($id);
    snapshot_service::invalidate($id);
    redirect(new moodle_url('/local/academicpanel/manage.php', ['tab' => 'programs']),
        get_string('programdeactivatedmsg', 'local_academicpanel'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

if ($action === 'activateprogram' && $id) {
    require_sesskey();
    mapping_repository::activate_program($id);
    redirect(new moodle_url('/local/academicpanel/manage.php', [
        'tab' => 'programs',
        'showinactive' => 1,
    ]), get_string('programactivatedmsg', 'local_academicpanel'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

$showinactive = optional_param('showinactive', 0, PARAM_BOOL);
$programs = mapping_repository::get_all_programs($showinactive);
$programoptions = [];
foreach (mapping_repository::get_all_programs(false) as $program) {
    $programoptions[$program->id] = format_string($program->name) . ' (' . s($program->shortname) . ')';
}

echo $OUTPUT->header();
echo html_writer::start_div('local-academicpanel-manage');
echo $OUTPUT->heading(get_string('managemappings', 'local_academicpanel'));
echo html_writer::link(new moodle_url('/local/academicpanel/index.php'),
    get_string('pluginname', 'local_academicpanel'), ['class' => 'btn btn-secondary mb-3']);

$tabs = [
    new tabobject('programs',
        new moodle_url('/local/academicpanel/manage.php', ['tab' => 'programs']),
        get_string('tab_programs', 'local_academicpanel')),
    new tabobject('mappings',
        new moodle_url('/local/academicpanel/manage.php', ['tab' => 'mappings']),
        get_string('tab_mappings', 'local_academicpanel')),
    new tabobject('coordinators',
        new moodle_url('/local/academicpanel/manage.php', ['tab' => 'coordinators']),
        get_string('tab_coordinators', 'local_academicpanel')),
];

echo $OUTPUT->tabtree($tabs, $tab);

switch ($tab) {
    case 'programs':
        local_academicpanel_render_programs_tab($programs, $action, $id, $showinactive);
        break;
    case 'mappings':
        local_academicpanel_render_mappings_tab($programoptions, $action, $id);
        break;
    case 'coordinators':
        local_academicpanel_render_coordinators_tab($programs, $programoptions, $action, $id);
        break;
}

echo html_writer::end_div();
echo $OUTPUT->footer();

function local_academicpanel_render_programs_tab($programs, $action, $id, $showinactive) {
    $editing = null;
    if ($action === 'editprogram' && $id) {
        $editing = mapping_repository::get_program($id);
    }

    $toggleurl = new moodle_url('/local/academicpanel/manage.php', [
        'tab' => 'programs',
        'showinactive' => $showinactive ? 0 : 1,
    ]);
    $togglelabel = $showinactive
        ? get_string('hideinactive', 'local_academicpanel')
        : get_string('showinactive', 'local_academicpanel');
    echo html_writer::div(
        html_writer::link($toggleurl, $togglelabel, ['class' => 'btn btn-sm btn-secondary']),
        'mb-3'
    );

    $form = new program_form();

    if ($form->is_cancelled()) {
        redirect(new moodle_url('/local/academicpanel/manage.php', ['tab' => 'programs']));
    }

    if ($editing && !$form->is_submitted()) {
        $form->set_data((object)[
            'id' => $editing->id,
            'programname' => $editing->name,
            'programshortname' => $editing->shortname,
        ]);
    }

    if ($data = $form->get_data()) {
        if (!empty($data->id)) {
            mapping_repository::update_program((int)$data->id, $data->programname, $data->programshortname);
        } else {
            mapping_repository::upsert_program($data->programname, $data->programshortname);
        }
        redirect(new moodle_url('/local/academicpanel/manage.php', ['tab' => 'programs']),
            get_string('programsavedmsg', 'local_academicpanel'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }

    $form->display();

    if (empty($programs)) {
        echo html_writer::div(get_string('noprogramsregistered', 'local_academicpanel'),
            'alert alert-info');
        return;
    }

    $table = new html_table();
    $table->head = [
        get_string('program', 'local_academicpanel'),
        get_string('programkey', 'local_academicpanel'),
        get_string('status'),
        get_string('actions'),
    ];

    foreach ($programs as $program) {
        $editurl = new moodle_url('/local/academicpanel/manage.php', [
            'tab' => 'programs',
            'action' => 'editprogram',
            'id' => $program->id,
        ]);
        $editlink = html_writer::link($editurl, get_string('edit'),
            ['class' => 'btn btn-sm btn-secondary']);

        if ((int)$program->active === 1) {
            $toggleurl = new moodle_url('/local/academicpanel/manage.php', [
                'tab' => 'programs',
                'action' => 'deactivateprogram',
                'id' => $program->id,
                'sesskey' => sesskey(),
            ]);
            $togglelink = html_writer::link($toggleurl, get_string('deactivate', 'local_academicpanel'), [
                'class' => 'btn btn-sm btn-danger',
                'onclick' => "return confirm('" . get_string('confirmdeactivateprogram', 'local_academicpanel') . "');",
            ]);
            $statuslabel = html_writer::span(get_string('active', 'local_academicpanel'), 'badge badge-success');
        } else {
            $toggleurl = new moodle_url('/local/academicpanel/manage.php', [
                'tab' => 'programs',
                'action' => 'activateprogram',
                'id' => $program->id,
                'sesskey' => sesskey(),
            ]);
            $togglelink = html_writer::link($toggleurl, get_string('activate', 'local_academicpanel'),
                ['class' => 'btn btn-sm btn-success']);
            $statuslabel = html_writer::span(get_string('inactive', 'local_academicpanel'), 'badge badge-secondary');
        }

        $table->data[] = [
            format_string($program->name),
            s($program->shortname),
            $statuslabel,
            $editlink . ' ' . $togglelink,
        ];
    }

    echo html_writer::table($table);
}

function local_academicpanel_render_mappings_tab($programoptions, $action, $id) {
    global $DB, $OUTPUT;

    if (empty($programoptions)) {
        echo $OUTPUT->notification(get_string('createprogramfirst', 'local_academicpanel'),
            \core\output\notification::NOTIFY_WARNING);
        return;
    }

    $editing = null;
    if ($action === 'editmapping' && $id) {
        $editing = mapping_repository::get_mapping($id);
    }

    $form = new mapping_form(null, ['programs' => $programoptions]);

    if ($form->is_cancelled()) {
        redirect(new moodle_url('/local/academicpanel/manage.php', ['tab' => 'mappings']));
    }

    if ($editing && !$form->is_submitted()) {
        $form->set_data((object)[
            'id' => $editing->id,
            'programid' => $editing->programid,
            'categoryid' => $editing->categoryid,
            'semester' => $editing->semester,
        ]);
    }

    if ($data = $form->get_data()) {
        if (!empty($data->id)) {
            $previous = mapping_repository::get_mapping((int)$data->id);
            mapping_repository::update_mapping_by_id(
                (int)$data->id,
                (int)$data->programid,
                (int)$data->categoryid,
                $data->semester,
                'manual'
            );
            if ($previous) {
                snapshot_service::invalidate((int)$previous->programid, $previous->semester);
            }
        } else {
            mapping_repository::upsert_category_mapping(
                (int)$data->programid,
                (int)$data->categoryid,
                $data->semester,
                'manual'
            );
        }
        snapshot_service::invalidate((int)$data->programid, $data->semester);
        redirect(new moodle_url('/local/academicpanel/manage.php', ['tab' => 'mappings']),
            get_string('mappingsavedmsg', 'local_academicpanel'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }

    $form->display();

    $records = $DB->get_records_sql(
        'SELECT c.id, c.categoryid, c.semester, c.origin, p.name, p.shortname
           FROM {' . mapping_repository::CATEGORY_TABLE . '} c
           JOIN {' . mapping_repository::PROGRAM_TABLE . '} p ON p.id = c.programid
       ORDER BY c.semester DESC, p.name ASC'
    );

    if (empty($records)) {
        echo html_writer::div(get_string('nomappings', 'local_academicpanel'), 'alert alert-info');
        return;
    }

    $table = new html_table();
    $table->head = [
        get_string('semester', 'local_academicpanel'),
        get_string('program', 'local_academicpanel'),
        get_string('programkey', 'local_academicpanel'),
        get_string('category', 'local_academicpanel'),
        get_string('mappingoriginmanual', 'local_academicpanel'),
        get_string('actions'),
    ];

    foreach ($records as $record) {
        $category = \core_course_category::get($record->categoryid, IGNORE_MISSING, true);
        $editurl = new moodle_url('/local/academicpanel/manage.php', [
            'tab' => 'mappings',
            'action' => 'editmapping',
            'id' => $record->id,
        ]);
        $removeurl = new moodle_url('/local/academicpanel/manage.php', [
            'tab' => 'mappings',
            'action' => 'removemapping',
            'id' => $record->id,
            'sesskey' => sesskey(),
        ]);
        $editlink = html_writer::link($editurl, get_string('edit'),
            ['class' => 'btn btn-sm btn-secondary']);
        $removelink = html_writer::link($removeurl, get_string('delete'), [
            'class' => 'btn btn-sm btn-danger',
            'onclick' => "return confirm('" . get_string('confirmremovemapping', 'local_academicpanel') . "');",
        ]);

        $table->data[] = [
            s($record->semester),
            format_string($record->name),
            s($record->shortname),
            $category ? $category->get_formatted_name() : s($record->categoryid),
            s($record->origin),
            $editlink . ' ' . $removelink,
        ];
    }

    echo html_writer::table($table);
}

function local_academicpanel_render_coordinators_tab($programs, $programoptions, $action, $id) {
    global $OUTPUT;

    if (empty($programoptions)) {
        echo $OUTPUT->notification(get_string('createprogramfirst', 'local_academicpanel'),
            \core\output\notification::NOTIFY_WARNING);
        return;
    }

    $editing = null;
    if ($action === 'editcoord' && $id) {
        $editing = mapping_repository::get_coordinator($id);
    }

    $form = new coordinator_form(null, [
        'programs' => $programoptions,
        'editmode' => (bool)$editing,
    ]);

    if ($form->is_cancelled()) {
        redirect(new moodle_url('/local/academicpanel/manage.php', ['tab' => 'coordinators']));
    }

    if ($editing && !$form->is_submitted()) {
        $form->set_data((object)[
            'id' => $editing->id,
            'programid' => $editing->programid,
            'userids' => $editing->userid,
        ]);
    }

    if ($data = $form->get_data()) {
        if (!empty($data->id)) {
            try {
                $userid = is_array($data->userids) ? (int)reset($data->userids) : (int)$data->userids;
                mapping_repository::update_coordinator((int)$data->id, (int)$data->programid, $userid);
            } catch (\moodle_exception $e) {
                redirect(new moodle_url('/local/academicpanel/manage.php', ['tab' => 'coordinators']),
                    get_string('coordinatorduplicate', 'local_academicpanel'), null,
                    \core\output\notification::NOTIFY_ERROR);
            }
        } else {
            foreach ((array)$data->userids as $userid) {
                mapping_repository::add_coordinator((int)$data->programid, (int)$userid);
            }
        }
        redirect(new moodle_url('/local/academicpanel/manage.php', ['tab' => 'coordinators']),
            get_string('coordinatoraddedmsg', 'local_academicpanel'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }

    $form->display();

    foreach ($programs as $program) {
        echo $OUTPUT->heading(format_string($program->name), 4);
        $coordinators = mapping_repository::list_coordinators($program->id);

        if (empty($coordinators)) {
            echo html_writer::div(get_string('nocoordinators', 'local_academicpanel'),
                'alert alert-info');
            continue;
        }

        $table = new html_table();
        $table->head = [
            get_string('fullnameuser'),
            get_string('email'),
            get_string('actions'),
        ];

        foreach ($coordinators as $coord) {
            $fullname = fullname($coord);
            $editurl = new moodle_url('/local/academicpanel/manage.php', [
                'tab' => 'coordinators',
                'action' => 'editcoord',
                'id' => $coord->id,
            ]);
            $removeurl = new moodle_url('/local/academicpanel/manage.php', [
                'tab' => 'coordinators',
                'action' => 'removecoord',
                'id' => $coord->id,
                'sesskey' => sesskey(),
            ]);
            $editlink = html_writer::link($editurl, get_string('edit'),
                ['class' => 'btn btn-sm btn-secondary']);
            $removelink = html_writer::link($removeurl, get_string('removecoordinator', 'local_academicpanel'), [
                'class' => 'btn btn-sm btn-danger',
                'onclick' => "return confirm('" . get_string('confirmremovecoordinator', 'local_academicpanel') . "');",
            ]);

            $table->data[] = [
                s($fullname),
                s($coord->email),
                $editlink . ' ' . $removelink,
            ];
        }

        echo html_writer::table($table);
    }
}
