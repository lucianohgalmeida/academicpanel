<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_local_academicpanel_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026052705) {
        local_academicpanel_ensure_program_table($dbman);
        local_academicpanel_ensure_category_table($dbman);
        local_academicpanel_ensure_coord_table($dbman);
        local_academicpanel_migrate_legacy_coordinator($dbman);
        local_academicpanel_ensure_rule_table($dbman);
        local_academicpanel_ensure_snapshot_table($dbman);
        local_academicpanel_ensure_seed_table($dbman);

        upgrade_plugin_savepoint(true, 2026052705, 'local', 'academicpanel');
    }

    if ($oldversion < 2026052706) {
        \local_academicpanel\local\role_installer::ensure_coordinator_role();

        upgrade_plugin_savepoint(true, 2026052706, 'local', 'academicpanel');
    }

    if ($oldversion < 2026052711) {
        local_academicpanel_backfill_coordinator_role();

        upgrade_plugin_savepoint(true, 2026052711, 'local', 'academicpanel');
    }

    if ($oldversion < 2026052714) {
        // Rebackfill com filtro de user inexistente (caso savepoint 2026052711 tenha falhado antes).
        local_academicpanel_backfill_coordinator_role();

        upgrade_plugin_savepoint(true, 2026052714, 'local', 'academicpanel');
    }

    return true;
}

function local_academicpanel_backfill_coordinator_role() {
    global $CFG, $DB;
    require_once($CFG->libdir . '/accesslib.php');

    \local_academicpanel\local\role_installer::ensure_coordinator_role();

    $roleid = $DB->get_field('role', 'id', [
        'shortname' => \local_academicpanel\local\role_installer::COORDINATOR_SHORTNAME,
    ]);
    if (!$roleid) {
        return;
    }

    $context = \context_system::instance();
    $userids = $DB->get_fieldset_select('local_acpanel_coord', 'DISTINCT userid', 'active = 1');
    foreach ($userids as $userid) {
        if (!$DB->record_exists('user', ['id' => (int)$userid, 'deleted' => 0])) {
            continue;
        }
        role_assign($roleid, (int)$userid, $context->id, 'local_academicpanel');
    }
}

function local_academicpanel_ensure_program_table($dbman) {
    global $DB;

    $table = new xmldb_table('local_acpanel_program');
    if (!$dbman->table_exists($table)) {
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('shortname', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
        $table->add_field('active', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('shortname_uix', XMLDB_INDEX_UNIQUE, ['shortname']);
        $dbman->create_table($table);
        return;
    }

    local_academicpanel_ensure_char_field($dbman, $table, 'name', '255', 'id', 'Academic program');
    local_academicpanel_ensure_char_field($dbman, $table, 'shortname', '100', 'name', 'program');
    local_academicpanel_ensure_int_field($dbman, $table, 'active', '1', '1', 'shortname');
    local_academicpanel_ensure_int_field($dbman, $table, 'timecreated', '10', '0', 'active');
    local_academicpanel_ensure_int_field($dbman, $table, 'timemodified', '10', '0', 'timecreated');

    $records = $DB->get_records('local_acpanel_program', null, 'id ASC');
    $used = [];
    foreach ($records as $record) {
        $name = trim((string)$record->name);
        $shortname = trim((string)$record->shortname);
        if ($name === '') {
            $name = 'Academic program ' . $record->id;
            $DB->set_field('local_acpanel_program', 'name', $name, ['id' => $record->id]);
        }
        if ($shortname === '' || isset($used[$shortname])) {
            $shortname = 'program-' . $record->id;
            $DB->set_field('local_acpanel_program', 'shortname', $shortname, ['id' => $record->id]);
        }
        $used[$shortname] = true;
    }

    local_academicpanel_change_field_notnull($dbman, $table, new xmldb_field(
        'name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, 'id'
    ));
    local_academicpanel_change_field_notnull($dbman, $table, new xmldb_field(
        'shortname', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null, 'name'
    ));

    local_academicpanel_add_index($dbman, $table, new xmldb_index('shortname_uix', XMLDB_INDEX_UNIQUE, ['shortname']));
}

function local_academicpanel_ensure_category_table($dbman) {
    global $DB;

    $table = new xmldb_table('local_acpanel_category');
    if (!$dbman->table_exists($table)) {
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('programid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('categoryid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('semester', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
        $table->add_field('origin', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'manual');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('program_fk', XMLDB_KEY_FOREIGN, ['programid'], 'local_acpanel_program', ['id']);
        $table->add_index('category_uix', XMLDB_INDEX_UNIQUE, ['categoryid']);
        $table->add_index('program_ix', XMLDB_INDEX_NOTUNIQUE, ['programid']);
        $dbman->create_table($table);
        return;
    }

    local_academicpanel_ensure_int_nullable_field($dbman, $table, 'programid', '10', 'id');
    local_academicpanel_ensure_int_nullable_field($dbman, $table, 'categoryid', '10', 'programid');
    local_academicpanel_ensure_nullable_char_field($dbman, $table, 'semester', '100', 'categoryid');
    local_academicpanel_ensure_char_field($dbman, $table, 'origin', '20', 'semester', 'manual');
    local_academicpanel_ensure_int_field($dbman, $table, 'timecreated', '10', '0', 'origin');
    local_academicpanel_ensure_int_field($dbman, $table, 'timemodified', '10', '0', 'timecreated');

    $semesternamefield = new xmldb_field('semestername');
    if ($dbman->field_exists($table, $semesternamefield)) {
        $records = $DB->get_records('local_acpanel_category');
        foreach ($records as $record) {
            $semester = trim((string)$record->semestername);
            if ($semester !== '') {
                $DB->set_field('local_acpanel_category', 'semester', $semester, ['id' => $record->id]);
            }
        }

        $semesternamefield = new xmldb_field('semestername', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'categoryid');
        $dbman->drop_field($table, $semesternamefield);
    }

    $defaultprogramid = null;
    $records = $DB->get_records('local_acpanel_category', null, 'id ASC');
    $usedcategories = [];
    foreach ($records as $record) {
        if (empty($record->programid) || !$DB->record_exists('local_acpanel_program', ['id' => $record->programid])) {
            if ($defaultprogramid === null) {
                $defaultprogramid = local_academicpanel_get_default_programid();
            }
            $DB->set_field('local_acpanel_category', 'programid', $defaultprogramid, ['id' => $record->id]);
            $record->programid = $defaultprogramid;
        }

        if (empty($record->categoryid) || isset($usedcategories[$record->categoryid])) {
            $categoryid = local_academicpanel_next_unique_integer($record->id, $usedcategories);
            $DB->set_field('local_acpanel_category', 'categoryid', $categoryid, ['id' => $record->id]);
            $record->categoryid = $categoryid;
        }
        $usedcategories[$record->categoryid] = true;

        if ((string)$record->semester === '') {
            $DB->set_field('local_acpanel_category', 'semester', '', ['id' => $record->id]);
        }
        if ((string)$record->origin === '') {
            $DB->set_field('local_acpanel_category', 'origin', 'manual', ['id' => $record->id]);
        }
    }

    local_academicpanel_change_field_notnull($dbman, $table, new xmldb_field(
        'programid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'id'
    ));
    local_academicpanel_change_field_notnull($dbman, $table, new xmldb_field(
        'categoryid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'programid'
    ));
    local_academicpanel_change_field_notnull($dbman, $table, new xmldb_field(
        'semester', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null, 'categoryid'
    ));
    local_academicpanel_change_field_notnull($dbman, $table, new xmldb_field(
        'origin', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'manual', 'semester'
    ));

    local_academicpanel_add_index($dbman, $table, new xmldb_index('category_uix', XMLDB_INDEX_UNIQUE, ['categoryid']));
    local_academicpanel_add_index($dbman, $table, new xmldb_index('program_ix', XMLDB_INDEX_NOTUNIQUE, ['programid']));
    local_academicpanel_add_key($dbman, $table, new xmldb_key(
        'program_fk', XMLDB_KEY_FOREIGN, ['programid'], 'local_acpanel_program', ['id']
    ));
}

function local_academicpanel_ensure_coord_table($dbman) {
    global $DB;

    $table = new xmldb_table('local_acpanel_coord');
    if (!$dbman->table_exists($table)) {
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('programid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('active', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('program_fk', XMLDB_KEY_FOREIGN, ['programid'], 'local_acpanel_program', ['id']);
        $table->add_index('program_user_uix', XMLDB_INDEX_UNIQUE, ['programid', 'userid']);
        $table->add_index('userid_ix', XMLDB_INDEX_NOTUNIQUE, ['userid']);
        $dbman->create_table($table);
        return;
    }

    local_academicpanel_ensure_int_nullable_field($dbman, $table, 'programid', '10', 'id');
    local_academicpanel_ensure_int_nullable_field($dbman, $table, 'userid', '10', 'programid');
    local_academicpanel_ensure_int_field($dbman, $table, 'active', '1', '1', 'userid');
    local_academicpanel_ensure_int_field($dbman, $table, 'timecreated', '10', '0', 'active');
    local_academicpanel_ensure_int_field($dbman, $table, 'timemodified', '10', '0', 'timecreated');

    $defaultprogramid = null;
    $used = [];
    $records = $DB->get_records('local_acpanel_coord', null, 'id ASC');
    foreach ($records as $record) {
        if (empty($record->programid) || !$DB->record_exists('local_acpanel_program', ['id' => $record->programid])) {
            if ($defaultprogramid === null) {
                $defaultprogramid = local_academicpanel_get_default_programid();
            }
            $DB->set_field('local_acpanel_coord', 'programid', $defaultprogramid, ['id' => $record->id]);
            $record->programid = $defaultprogramid;
        }

        $key = $record->programid . ':' . $record->userid;
        if (empty($record->userid) || isset($used[$key])) {
            $userid = local_academicpanel_next_unique_integer($record->id, $used, $record->programid . ':');
            $DB->set_field('local_acpanel_coord', 'userid', $userid, ['id' => $record->id]);
            $record->userid = $userid;
            $key = $record->programid . ':' . $record->userid;
        }
        $used[$key] = true;
    }

    local_academicpanel_change_field_notnull($dbman, $table, new xmldb_field(
        'programid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'id'
    ));
    local_academicpanel_change_field_notnull($dbman, $table, new xmldb_field(
        'userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'programid'
    ));
    local_academicpanel_change_field_notnull($dbman, $table, new xmldb_field(
        'active', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1', 'userid'
    ));
    local_academicpanel_change_field_notnull($dbman, $table, new xmldb_field(
        'timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'active'
    ));
    local_academicpanel_change_field_notnull($dbman, $table, new xmldb_field(
        'timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'timecreated'
    ));
    local_academicpanel_change_field_default($dbman, $table, new xmldb_field(
        'active', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1', 'userid'
    ));
    local_academicpanel_change_field_default($dbman, $table, new xmldb_field(
        'timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'active'
    ));
    local_academicpanel_change_field_default($dbman, $table, new xmldb_field(
        'timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'timecreated'
    ));

    local_academicpanel_add_index($dbman, $table, new xmldb_index('program_user_uix', XMLDB_INDEX_UNIQUE, ['programid', 'userid']));
    local_academicpanel_add_index($dbman, $table, new xmldb_index('userid_ix', XMLDB_INDEX_NOTUNIQUE, ['userid']));
    local_academicpanel_add_key($dbman, $table, new xmldb_key(
        'program_fk', XMLDB_KEY_FOREIGN, ['programid'], 'local_acpanel_program', ['id']
    ));
}

function local_academicpanel_migrate_legacy_coordinator($dbman) {
    global $DB;

    $programtable = new xmldb_table('local_acpanel_program');
    $coordinatorfield = new xmldb_field('coordinatorid');
    if (!$dbman->table_exists($programtable) || !$dbman->field_exists($programtable, $coordinatorfield)) {
        return;
    }

    $programs = $DB->get_records_select('local_acpanel_program', 'coordinatorid IS NOT NULL AND coordinatorid <> 0');
    foreach ($programs as $program) {
        if (!$DB->record_exists('local_acpanel_coord', ['programid' => $program->id, 'userid' => $program->coordinatorid])) {
            $coord = (object)[
                'programid' => $program->id,
                'userid' => $program->coordinatorid,
                'active' => 1,
                'timecreated' => $program->timecreated,
                'timemodified' => $program->timemodified,
            ];
            $DB->insert_record('local_acpanel_coord', $coord);
        }
    }

    $index = new xmldb_index('coordinator_ix', XMLDB_INDEX_NOTUNIQUE, ['coordinatorid']);
    if ($dbman->index_exists($programtable, $index)) {
        $dbman->drop_index($programtable, $index);
    }

    $coordinatorfield = new xmldb_field('coordinatorid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'shortname');
    $dbman->drop_field($programtable, $coordinatorfield);
}

function local_academicpanel_ensure_rule_table($dbman) {
    global $DB;

    $table = new xmldb_table('local_acpanel_rule');
    if (!$dbman->table_exists($table)) {
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('programid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('gradecutoff', XMLDB_TYPE_NUMBER, '10, 5', null, XMLDB_NOTNULL, null, '7');
        $table->add_field('rolesincluded', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('engagementfallback', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'access');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('program_ix', XMLDB_INDEX_NOTUNIQUE, ['programid']);
        $dbman->create_table($table);
        return;
    }

    local_academicpanel_ensure_int_nullable_field($dbman, $table, 'programid', '10', 'id');
    local_academicpanel_ensure_number_field($dbman, $table, 'gradecutoff', '10, 5', '7', 'programid');
    local_academicpanel_ensure_nullable_text_field($dbman, $table, 'rolesincluded', 'gradecutoff');
    local_academicpanel_ensure_char_field($dbman, $table, 'engagementfallback', '20', 'rolesincluded', 'access');
    local_academicpanel_ensure_int_field($dbman, $table, 'timecreated', '10', '0', 'engagementfallback');
    local_academicpanel_ensure_int_field($dbman, $table, 'timemodified', '10', '0', 'timecreated');

    $DB->execute("UPDATE {local_acpanel_rule} SET rolesincluded = 'student' WHERE rolesincluded IS NULL OR rolesincluded = ''");
    $DB->execute("UPDATE {local_acpanel_rule} SET engagementfallback = 'access'
                   WHERE engagementfallback IS NULL OR engagementfallback NOT IN ('none', 'access', 'completion')");

    local_academicpanel_change_field_notnull($dbman, $table, new xmldb_field(
        'gradecutoff', XMLDB_TYPE_NUMBER, '10, 5', null, XMLDB_NOTNULL, null, '7', 'programid'
    ));
    local_academicpanel_change_field_notnull($dbman, $table, new xmldb_field(
        'rolesincluded', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null, 'gradecutoff'
    ));
    local_academicpanel_change_field_notnull($dbman, $table, new xmldb_field(
        'engagementfallback', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'access', 'rolesincluded'
    ));
    local_academicpanel_change_field_notnull($dbman, $table, new xmldb_field(
        'timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'engagementfallback'
    ));
    local_academicpanel_change_field_notnull($dbman, $table, new xmldb_field(
        'timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'timecreated'
    ));
    local_academicpanel_change_field_default($dbman, $table, new xmldb_field(
        'gradecutoff', XMLDB_TYPE_NUMBER, '10, 5', null, XMLDB_NOTNULL, null, '7', 'programid'
    ));
    local_academicpanel_change_field_default($dbman, $table, new xmldb_field(
        'engagementfallback', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'access', 'rolesincluded'
    ));
    local_academicpanel_change_field_default($dbman, $table, new xmldb_field(
        'timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'engagementfallback'
    ));
    local_academicpanel_change_field_default($dbman, $table, new xmldb_field(
        'timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'timecreated'
    ));

    local_academicpanel_add_index($dbman, $table, new xmldb_index('program_ix', XMLDB_INDEX_NOTUNIQUE, ['programid']));
}

function local_academicpanel_ensure_snapshot_table($dbman) {
    global $DB;

    $table = new xmldb_table('local_acpanel_snapshot');
    if (!$dbman->table_exists($table)) {
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('programid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('semester', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
        $table->add_field('enrolled', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('withgrade', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('approved', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('failed', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('engaged', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('neveraccessed', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('abandoned', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timegenerated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('program_fk', XMLDB_KEY_FOREIGN, ['programid'], 'local_acpanel_program', ['id']);
        $table->add_index('program_sem_course_uix', XMLDB_INDEX_UNIQUE, ['programid', 'semester', 'courseid']);
        $dbman->create_table($table);
        return;
    }

    local_academicpanel_ensure_int_nullable_field($dbman, $table, 'programid', '10', 'id');
    local_academicpanel_ensure_int_nullable_field($dbman, $table, 'courseid', '10', 'programid');
    local_academicpanel_ensure_nullable_char_field($dbman, $table, 'semester', '100', 'courseid');
    local_academicpanel_ensure_int_field($dbman, $table, 'enrolled', '10', '0', 'semester');
    local_academicpanel_ensure_int_field($dbman, $table, 'withgrade', '10', '0', 'enrolled');
    local_academicpanel_ensure_int_field($dbman, $table, 'approved', '10', '0', 'withgrade');
    local_academicpanel_ensure_int_field($dbman, $table, 'failed', '10', '0', 'approved');
    local_academicpanel_ensure_int_field($dbman, $table, 'engaged', '10', '0', 'failed');
    local_academicpanel_ensure_int_field($dbman, $table, 'neveraccessed', '10', '0', 'engaged');
    local_academicpanel_ensure_int_field($dbman, $table, 'abandoned', '10', '0', 'neveraccessed');
    local_academicpanel_ensure_int_field($dbman, $table, 'timegenerated', '10', '0', 'abandoned');

    $defaultprogramid = null;
    $used = [];
    $records = $DB->get_records('local_acpanel_snapshot', null, 'id ASC');
    foreach ($records as $record) {
        if (empty($record->programid) || !$DB->record_exists('local_acpanel_program', ['id' => $record->programid])) {
            if ($defaultprogramid === null) {
                $defaultprogramid = local_academicpanel_get_default_programid();
            }
            $DB->set_field('local_acpanel_snapshot', 'programid', $defaultprogramid, ['id' => $record->id]);
            $record->programid = $defaultprogramid;
        }
        if (empty($record->courseid)) {
            $DB->set_field('local_acpanel_snapshot', 'courseid', $record->id, ['id' => $record->id]);
            $record->courseid = $record->id;
        }
        if ((string)$record->semester === '') {
            $DB->set_field('local_acpanel_snapshot', 'semester', '', ['id' => $record->id]);
            $record->semester = '';
        }

        $key = $record->programid . ':' . $record->semester . ':' . $record->courseid;
        if (isset($used[$key])) {
            $courseid = local_academicpanel_next_unique_integer(
                $record->id,
                $used,
                $record->programid . ':' . $record->semester . ':'
            );
            $DB->set_field('local_acpanel_snapshot', 'courseid', $courseid, ['id' => $record->id]);
            $record->courseid = $courseid;
            $key = $record->programid . ':' . $record->semester . ':' . $record->courseid;
        }
        $used[$key] = true;
    }

    local_academicpanel_change_snapshot_notnulls($dbman, $table);
    local_academicpanel_change_snapshot_defaults($dbman, $table);

    local_academicpanel_add_index($dbman, $table, new xmldb_index(
        'program_sem_course_uix', XMLDB_INDEX_UNIQUE, ['programid', 'semester', 'courseid']
    ));
    local_academicpanel_add_key($dbman, $table, new xmldb_key(
        'program_fk', XMLDB_KEY_FOREIGN, ['programid'], 'local_acpanel_program', ['id']
    ));
}

function local_academicpanel_ensure_seed_table($dbman) {
    global $DB;

    $table = new xmldb_table('local_acpanel_seed');
    if (!$dbman->table_exists($table)) {
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('component', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
        $table->add_field('itemtype', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
        $table->add_field('itemid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('component_item_ix', XMLDB_INDEX_NOTUNIQUE, ['component', 'itemtype', 'itemid']);
        $dbman->create_table($table);
        return;
    }

    local_academicpanel_ensure_nullable_char_field($dbman, $table, 'component', '100', 'id');
    local_academicpanel_ensure_nullable_char_field($dbman, $table, 'itemtype', '100', 'component');
    local_academicpanel_ensure_int_nullable_field($dbman, $table, 'itemid', '10', 'itemtype');
    local_academicpanel_ensure_int_field($dbman, $table, 'timecreated', '10', '0', 'itemid');

    $records = $DB->get_records('local_acpanel_seed', null, 'id ASC');
    foreach ($records as $record) {
        if ((string)$record->component === '') {
            $DB->set_field('local_acpanel_seed', 'component', 'local_academicpanel', ['id' => $record->id]);
        }
        if ((string)$record->itemtype === '') {
            $DB->set_field('local_acpanel_seed', 'itemtype', 'unknown', ['id' => $record->id]);
        }
        if (empty($record->itemid)) {
            $DB->set_field('local_acpanel_seed', 'itemid', $record->id, ['id' => $record->id]);
        }
    }

    local_academicpanel_change_field_notnull($dbman, $table, new xmldb_field(
        'component', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null, 'id'
    ));
    local_academicpanel_change_field_notnull($dbman, $table, new xmldb_field(
        'itemtype', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null, 'component'
    ));
    local_academicpanel_change_field_notnull($dbman, $table, new xmldb_field(
        'itemid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'itemtype'
    ));
    local_academicpanel_change_field_notnull($dbman, $table, new xmldb_field(
        'timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'itemid'
    ));
    local_academicpanel_change_field_default($dbman, $table, new xmldb_field(
        'timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'itemid'
    ));

    local_academicpanel_add_index($dbman, $table, new xmldb_index(
        'component_item_ix', XMLDB_INDEX_NOTUNIQUE, ['component', 'itemtype', 'itemid']
    ));
}

function local_academicpanel_ensure_char_field($dbman, $table, $name, $length, $previous, $default) {
    $field = new xmldb_field($name, XMLDB_TYPE_CHAR, $length, null, XMLDB_NOTNULL, null, $default, $previous);
    if (!$dbman->field_exists($table, $field)) {
        $dbman->add_field($table, $field);
    }
}

function local_academicpanel_ensure_nullable_char_field($dbman, $table, $name, $length, $previous) {
    $field = new xmldb_field($name, XMLDB_TYPE_CHAR, $length, null, null, null, null, $previous);
    if (!$dbman->field_exists($table, $field)) {
        $dbman->add_field($table, $field);
    }
}

function local_academicpanel_ensure_int_field($dbman, $table, $name, $length, $default, $previous) {
    $field = new xmldb_field($name, XMLDB_TYPE_INTEGER, $length, null, XMLDB_NOTNULL, null, $default, $previous);
    if (!$dbman->field_exists($table, $field)) {
        $dbman->add_field($table, $field);
    }
}

function local_academicpanel_ensure_int_nullable_field($dbman, $table, $name, $length, $previous) {
    $field = new xmldb_field($name, XMLDB_TYPE_INTEGER, $length, null, null, null, null, $previous);
    if (!$dbman->field_exists($table, $field)) {
        $dbman->add_field($table, $field);
    }
}

function local_academicpanel_ensure_number_field($dbman, $table, $name, $length, $default, $previous) {
    $field = new xmldb_field($name, XMLDB_TYPE_NUMBER, $length, null, XMLDB_NOTNULL, null, $default, $previous);
    if (!$dbman->field_exists($table, $field)) {
        $dbman->add_field($table, $field);
    }
}

function local_academicpanel_ensure_nullable_text_field($dbman, $table, $name, $previous) {
    $field = new xmldb_field($name, XMLDB_TYPE_TEXT, null, null, null, null, null, $previous);
    if (!$dbman->field_exists($table, $field)) {
        $dbman->add_field($table, $field);
    }
}

function local_academicpanel_change_field_notnull($dbman, $table, $field) {
    if (local_academicpanel_field_is_notnull($table->getName(), $field->getName())) {
        return;
    }

    $dbman->change_field_notnull($table, $field);
}

function local_academicpanel_change_field_default($dbman, $table, $field) {
    $dbman->change_field_default($table, $field);
}

function local_academicpanel_add_index($dbman, $table, $index) {
    if (!$dbman->index_exists($table, $index)) {
        $dbman->add_index($table, $index);
    }
}

function local_academicpanel_add_key($dbman, $table, $key) {
    try {
        $dbman->add_key($table, $key);
    } catch (ddl_change_structure_exception $exception) {
        // XMLDB does not reliably expose existing FK lookup, so only duplicate-key DDL errors are idempotent here.
        if (!local_academicpanel_is_duplicate_key_exception($exception)) {
            throw $exception;
        }
    }
}

function local_academicpanel_get_default_programid() {
    global $DB;

    $shortname = 'unmapped';
    if ($program = $DB->get_record('local_acpanel_program', ['shortname' => $shortname])) {
        return $program->id;
    }

    $time = time();
    $program = (object)[
        'name' => 'Unmapped academic program',
        'shortname' => $shortname,
        'active' => 1,
        'timecreated' => $time,
        'timemodified' => $time,
    ];

    return $DB->insert_record('local_acpanel_program', $program);
}

function local_academicpanel_next_unique_integer($seed, $used, $prefix = '') {
    $candidate = max(1, (int)$seed);

    while (isset($used[$prefix . $candidate])) {
        $candidate++;
    }

    return $candidate;
}

function local_academicpanel_field_is_notnull($tablename, $fieldname) {
    global $DB;

    $columns = $DB->get_columns($tablename);
    if (!isset($columns[$fieldname])) {
        return false;
    }

    return !empty($columns[$fieldname]->not_null);
}

function local_academicpanel_change_snapshot_notnulls($dbman, $table) {
    $fields = [
        new xmldb_field('programid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'id'),
        new xmldb_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'programid'),
        new xmldb_field('semester', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null, 'courseid'),
        new xmldb_field('enrolled', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'semester'),
        new xmldb_field('withgrade', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'enrolled'),
        new xmldb_field('approved', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'withgrade'),
        new xmldb_field('failed', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'approved'),
        new xmldb_field('engaged', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'failed'),
        new xmldb_field('neveraccessed', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'engaged'),
        new xmldb_field('abandoned', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'neveraccessed'),
        new xmldb_field('timegenerated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'abandoned'),
    ];

    foreach ($fields as $field) {
        local_academicpanel_change_field_notnull($dbman, $table, $field);
    }
}

function local_academicpanel_change_snapshot_defaults($dbman, $table) {
    $fields = [
        new xmldb_field('enrolled', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'semester'),
        new xmldb_field('withgrade', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'enrolled'),
        new xmldb_field('approved', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'withgrade'),
        new xmldb_field('failed', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'approved'),
        new xmldb_field('engaged', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'failed'),
        new xmldb_field('neveraccessed', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'engaged'),
        new xmldb_field('abandoned', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'neveraccessed'),
        new xmldb_field('timegenerated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'abandoned'),
    ];

    foreach ($fields as $field) {
        local_academicpanel_change_field_default($dbman, $table, $field);
    }
}

function local_academicpanel_is_duplicate_key_exception($exception) {
    $message = strtolower($exception->getMessage() . ' ' . $exception->debuginfo);

    return strpos($message, 'already exists') !== false ||
            strpos($message, 'duplicate') !== false ||
            strpos($message, 'duplicate key') !== false ||
            strpos($message, 'errno: 121') !== false ||
            strpos($message, 'errno: 1061') !== false;
}
