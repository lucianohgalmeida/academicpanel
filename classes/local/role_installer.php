<?php
namespace local_academicpanel\local;

defined('MOODLE_INTERNAL') || die();

class role_installer {
    const COORDINATOR_SHORTNAME = 'acpanel_coordinator';
    const VIEWASSIGNED_CAPABILITY = 'local/academicpanel:viewassigned';

    public static function ensure_coordinator_role() {
        global $DB;

        $existing = $DB->get_record('role', ['shortname' => self::COORDINATOR_SHORTNAME]);
        if ($existing) {
            $roleid = (int)$existing->id;
        } else {
            $roleid = create_role(
                get_string('coordinatorrolename', 'local_academicpanel'),
                self::COORDINATOR_SHORTNAME,
                get_string('coordinatorroledesc', 'local_academicpanel'),
                ''
            );
        }

        set_role_contextlevels($roleid, [CONTEXT_SYSTEM]);

        $syscontext = \context_system::instance();
        assign_capability(self::VIEWASSIGNED_CAPABILITY, CAP_ALLOW, $roleid, $syscontext->id, true);

        return $roleid;
    }
}
