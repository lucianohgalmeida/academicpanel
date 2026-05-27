<?php
namespace local_academicpanel\privacy;

defined('MOODLE_INTERNAL') || die();

use context;
use context_system;
use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\provider as metadata_provider;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\core_userlist_provider;
use core_privacy\local\request\plugin\provider as plugin_provider;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

class provider implements metadata_provider, plugin_provider, core_userlist_provider {

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_acpanel_coord', [
            'userid' => 'privacy:metadata:local_acpanel_coord:userid',
            'programid' => 'privacy:metadata:local_acpanel_coord:programid',
            'active' => 'privacy:metadata:local_acpanel_coord:active',
            'timecreated' => 'privacy:metadata:local_acpanel_coord:timecreated',
            'timemodified' => 'privacy:metadata:local_acpanel_coord:timemodified',
        ], 'privacy:metadata:local_acpanel_coord');

        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;

        $contextlist = new contextlist();

        if ($DB->record_exists('local_acpanel_coord', ['userid' => $userid])) {
            $contextlist->add_system_context();
        }

        return $contextlist;
    }

    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof context_system) {
            return;
        }

        global $DB;
        $userids = $DB->get_fieldset_select('local_acpanel_coord', 'DISTINCT userid', '1=1');
        if (!empty($userids)) {
            $userlist->add_users($userids);
        }
    }

    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist as $context) {
            if (!$context instanceof context_system) {
                continue;
            }

            $records = $DB->get_records('local_acpanel_coord', ['userid' => $userid]);
            if (empty($records)) {
                continue;
            }

            $data = [];
            foreach ($records as $record) {
                $program = $DB->get_record('local_acpanel_program', ['id' => $record->programid]);
                $data[] = (object)[
                    'programid' => (int)$record->programid,
                    'programname' => $program ? format_string($program->name) : '',
                    'active' => (int)$record->active,
                    'timecreated' => \core_privacy\local\request\transform::datetime($record->timecreated),
                    'timemodified' => \core_privacy\local\request\transform::datetime($record->timemodified),
                ];
            }

            writer::with_context($context)->export_data(
                [get_string('pluginname', 'local_academicpanel'), get_string('coordinators', 'local_academicpanel')],
                (object)['coordinators' => $data]
            );
        }
    }

    public static function delete_data_for_all_users_in_context(context $context) {
        if (!$context instanceof context_system) {
            return;
        }

        global $DB;
        $DB->delete_records('local_acpanel_coord');
    }

    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist as $context) {
            if (!$context instanceof context_system) {
                continue;
            }
            $DB->delete_records('local_acpanel_coord', ['userid' => $userid]);
        }
    }

    public static function delete_data_for_users(approved_userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof context_system) {
            return;
        }

        global $DB;
        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }

        list($insql, $params) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $DB->delete_records_select('local_acpanel_coord', 'userid ' . $insql, $params);
    }
}
