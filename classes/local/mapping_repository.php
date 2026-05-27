<?php
namespace local_academicpanel\local;

defined('MOODLE_INTERNAL') || die();

class mapping_repository {
    const PROGRAM_TABLE = 'local_acpanel_program';
    const CATEGORY_TABLE = 'local_acpanel_category';
    const COORD_TABLE = 'local_acpanel_coord';
    const RULE_TABLE = 'local_acpanel_rule';

    public static function upsert_program($name, $shortname) {
        global $DB;

        $now = time();
        $program = $DB->get_record(self::PROGRAM_TABLE, ['shortname' => $shortname]);
        if ($program) {
            $program->name = $name;
            $program->active = 1;
            $program->timemodified = $now;
            $DB->update_record(self::PROGRAM_TABLE, $program);
            \local_academicpanel\event\program_updated::create([
                'context' => \context_system::instance(),
                'objectid' => (int)$program->id,
                'other' => ['action' => 'edited'],
            ])->trigger();
            return $program->id;
        }

        $id = $DB->insert_record(self::PROGRAM_TABLE, (object)[
            'name' => $name,
            'shortname' => $shortname,
            'active' => 1,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        \local_academicpanel\event\program_created::create([
            'context' => \context_system::instance(),
            'objectid' => (int)$id,
        ])->trigger();
        return $id;
    }

    public static function upsert_category_mapping($programid, $categoryid, $semester, $origin) {
        global $DB;

        $now = time();
        $mapping = $DB->get_record(self::CATEGORY_TABLE, ['categoryid' => $categoryid]);
        if ($mapping) {
            $mapping->programid = $programid;
            $mapping->semester = $semester;
            $mapping->origin = $origin;
            $mapping->timemodified = $now;
            $DB->update_record(self::CATEGORY_TABLE, $mapping);
            return $mapping->id;
        }

        $id = $DB->insert_record(self::CATEGORY_TABLE, (object)[
            'programid' => $programid,
            'categoryid' => $categoryid,
            'semester' => $semester,
            'origin' => $origin,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        \local_academicpanel\event\mapping_created::create([
            'context' => \context_system::instance(),
            'objectid' => (int)$id,
            'other' => [
                'programid' => (int)$programid,
                'semester' => (string)$semester,
            ],
        ])->trigger();
        return $id;
    }

    public static function add_coordinator($programid, $userid) {
        global $DB;

        $now = time();
        $record = $DB->get_record(self::COORD_TABLE, ['programid' => $programid, 'userid' => $userid]);
        if ($record) {
            $wasinactive = ((int)$record->active === 0);
            $record->active = 1;
            $record->timemodified = $now;
            $DB->update_record(self::COORD_TABLE, $record);
            self::ensure_coordinator_role_assignment((int)$userid);
            if ($wasinactive) {
                \local_academicpanel\event\coordinator_added::create([
                    'context' => \context_system::instance(),
                    'objectid' => (int)$record->id,
                    'relateduserid' => (int)$userid,
                    'other' => ['programid' => (int)$programid],
                ])->trigger();
            }
            return $record->id;
        }

        $id = $DB->insert_record(self::COORD_TABLE, (object)[
            'programid' => $programid,
            'userid' => $userid,
            'active' => 1,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        self::ensure_coordinator_role_assignment((int)$userid);
        \local_academicpanel\event\coordinator_added::create([
            'context' => \context_system::instance(),
            'objectid' => (int)$id,
            'relateduserid' => (int)$userid,
            'other' => ['programid' => (int)$programid],
        ])->trigger();
        return $id;
    }

    private static function ensure_coordinator_role_assignment($userid) {
        global $CFG, $DB;
        require_once($CFG->libdir . '/accesslib.php');

        $roleid = $DB->get_field('role', 'id', [
            'shortname' => \local_academicpanel\local\role_installer::COORDINATOR_SHORTNAME,
        ]);
        if (!$roleid) {
            return;
        }

        $context = \context_system::instance();
        role_assign($roleid, $userid, $context->id, 'local_academicpanel');
    }

    private static function revoke_coordinator_role_if_orphan($userid) {
        global $CFG, $DB;
        require_once($CFG->libdir . '/accesslib.php');

        $stillactive = $DB->count_records(self::COORD_TABLE, [
            'userid' => $userid,
            'active' => 1,
        ]);
        if ($stillactive > 0) {
            return;
        }

        $roleid = $DB->get_field('role', 'id', [
            'shortname' => \local_academicpanel\local\role_installer::COORDINATOR_SHORTNAME,
        ]);
        if (!$roleid) {
            return;
        }

        $context = \context_system::instance();
        role_unassign($roleid, $userid, $context->id, 'local_academicpanel');
    }

    public static function get_visible_programs($userid) {
        global $DB;

        $context = \context_system::instance();
        if (has_capability('local/academicpanel:viewall', $context)) {
            return $DB->get_records(self::PROGRAM_TABLE, ['active' => 1], 'name ASC');
        }

        if (!has_capability('local/academicpanel:viewassigned', $context)) {
            return [];
        }

        return $DB->get_records_sql(
            'SELECT p.*
               FROM {' . self::PROGRAM_TABLE . '} p
               JOIN {' . self::COORD_TABLE . '} c ON c.programid = p.id
              WHERE p.active = 1 AND c.active = 1 AND c.userid = :userid
           ORDER BY p.name ASC',
            ['userid' => $userid]
        );
    }

    public static function get_mappings($programid, $semester) {
        global $DB;

        $params = ['programid' => $programid];
        $where = 'programid = :programid';
        if ($semester !== '') {
            $where .= ' AND semester = :semester';
            $params['semester'] = $semester;
        }

        return $DB->get_records_select(self::CATEGORY_TABLE, $where, $params, 'semester DESC, categoryid ASC');
    }

    public static function get_program_by_shortname($shortname) {
        global $DB;

        return $DB->get_record(self::PROGRAM_TABLE, ['shortname' => $shortname, 'active' => 1]);
    }

    public static function get_program($id) {
        global $DB;

        return $DB->get_record(self::PROGRAM_TABLE, ['id' => $id]);
    }

    public static function update_program($id, $name, $shortname) {
        global $DB;

        $record = $DB->get_record(self::PROGRAM_TABLE, ['id' => $id], '*', MUST_EXIST);
        $record->name = $name;
        $record->shortname = $shortname;
        $record->timemodified = time();
        $DB->update_record(self::PROGRAM_TABLE, $record);
        \local_academicpanel\event\program_updated::create([
            'context' => \context_system::instance(),
            'objectid' => (int)$id,
            'other' => ['action' => 'edited'],
        ])->trigger();
    }

    public static function deactivate_program($id) {
        global $DB;

        $DB->set_field(self::PROGRAM_TABLE, 'active', 0, ['id' => $id]);
        $DB->set_field(self::PROGRAM_TABLE, 'timemodified', time(), ['id' => $id]);
        \local_academicpanel\event\program_updated::create([
            'context' => \context_system::instance(),
            'objectid' => (int)$id,
            'other' => ['action' => 'deactivated'],
        ])->trigger();
    }

    public static function get_all_programs($includeinactive = false) {
        global $DB;

        if ($includeinactive) {
            return $DB->get_records(self::PROGRAM_TABLE, null, 'active DESC, name ASC');
        }

        return $DB->get_records(self::PROGRAM_TABLE, ['active' => 1], 'name ASC');
    }

    public static function activate_program($id) {
        global $DB;

        $DB->set_field(self::PROGRAM_TABLE, 'active', 1, ['id' => $id]);
        $DB->set_field(self::PROGRAM_TABLE, 'timemodified', time(), ['id' => $id]);
        \local_academicpanel\event\program_updated::create([
            'context' => \context_system::instance(),
            'objectid' => (int)$id,
            'other' => ['action' => 'activated'],
        ])->trigger();
    }

    public static function list_coordinators($programid) {
        global $DB;

        $namefields = \core_user\fields::get_name_fields();
        $userfields = array_map(function ($field) {
            return 'u.' . $field;
        }, $namefields);
        $userselect = implode(', ', $userfields);

        return $DB->get_records_sql(
            "SELECT c.id, c.userid, c.active, c.programid, {$userselect}, u.email
               FROM {" . self::COORD_TABLE . "} c
               JOIN {user} u ON u.id = c.userid AND u.deleted = 0
              WHERE c.programid = :programid AND c.active = 1
           ORDER BY u.lastname ASC, u.firstname ASC",
            ['programid' => $programid]
        );
    }

    public static function get_coordinator($id) {
        global $DB;

        return $DB->get_record(self::COORD_TABLE, ['id' => $id]);
    }

    public static function update_coordinator($id, $programid, $userid) {
        global $DB;

        $record = $DB->get_record(self::COORD_TABLE, ['id' => $id], '*', MUST_EXIST);
        $previoususerid = (int)$record->userid;

        $duplicate = $DB->get_record(self::COORD_TABLE, [
            'programid' => $programid,
            'userid' => $userid,
        ]);
        if ($duplicate && (int)$duplicate->id !== (int)$id) {
            throw new \moodle_exception('coordinatorduplicate', 'local_academicpanel');
        }

        $record->programid = $programid;
        $record->userid = $userid;
        $record->active = 1;
        $record->timemodified = time();
        $DB->update_record(self::COORD_TABLE, $record);

        self::ensure_coordinator_role_assignment((int)$userid);
        if ($previoususerid !== (int)$userid) {
            self::revoke_coordinator_role_if_orphan($previoususerid);
        }

        \local_academicpanel\event\coordinator_added::create([
            'context' => \context_system::instance(),
            'objectid' => (int)$id,
            'relateduserid' => (int)$userid,
            'other' => ['programid' => (int)$programid, 'action' => 'edited'],
        ])->trigger();
    }

    public static function deactivate_coordinator($id) {
        global $DB;

        $record = $DB->get_record(self::COORD_TABLE, ['id' => $id]);
        $DB->set_field(self::COORD_TABLE, 'active', 0, ['id' => $id]);
        $DB->set_field(self::COORD_TABLE, 'timemodified', time(), ['id' => $id]);
        if ($record) {
            self::revoke_coordinator_role_if_orphan((int)$record->userid);
            \local_academicpanel\event\coordinator_removed::create([
                'context' => \context_system::instance(),
                'objectid' => (int)$id,
                'relateduserid' => (int)$record->userid,
                'other' => ['programid' => (int)$record->programid],
            ])->trigger();
        }
    }

    public static function delete_mapping($id) {
        global $DB;

        $record = $DB->get_record(self::CATEGORY_TABLE, ['id' => $id]);
        $DB->delete_records(self::CATEGORY_TABLE, ['id' => $id]);
        if ($record) {
            \local_academicpanel\event\mapping_deleted::create([
                'context' => \context_system::instance(),
                'objectid' => (int)$id,
                'other' => [
                    'programid' => (int)$record->programid,
                    'semester' => (string)$record->semester,
                ],
            ])->trigger();
        }
    }

    public static function get_mapping($id) {
        global $DB;

        return $DB->get_record(self::CATEGORY_TABLE, ['id' => $id]);
    }

    public static function update_mapping_by_id($id, $programid, $categoryid, $semester, $origin = 'manual') {
        global $DB;

        $record = $DB->get_record(self::CATEGORY_TABLE, ['id' => $id], '*', MUST_EXIST);
        $record->programid = $programid;
        $record->categoryid = $categoryid;
        $record->semester = $semester;
        $record->origin = $origin;
        $record->timemodified = time();
        $DB->update_record(self::CATEGORY_TABLE, $record);
        \local_academicpanel\event\mapping_updated::create([
            'context' => \context_system::instance(),
            'objectid' => (int)$id,
            'other' => [
                'programid' => (int)$programid,
                'semester' => (string)$semester,
            ],
        ])->trigger();
    }

    public static function get_rule($programid = null) {
        global $DB;

        if ($programid) {
            $rule = $DB->get_record(self::RULE_TABLE, ['programid' => $programid]);
            if ($rule) {
                return $rule;
            }
        }

        $global = $DB->get_record(self::RULE_TABLE, ['programid' => null]);
        if ($global) {
            return $global;
        }

        return (object)[
            'programid' => null,
            'gradecutoff' => (float)get_config('local_academicpanel', 'gradecutoff'),
            'rolesincluded' => get_config('local_academicpanel', 'rolesincluded'),
            'engagementfallback' => get_config('local_academicpanel', 'engagementfallback'),
        ];
    }
}
