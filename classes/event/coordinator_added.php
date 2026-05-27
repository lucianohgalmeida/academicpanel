<?php
namespace local_academicpanel\event;

defined('MOODLE_INTERNAL') || die();

class coordinator_added extends \core\event\base {

    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_acpanel_coord';
    }

    public static function get_name() {
        return get_string('event_coordinator_added', 'local_academicpanel');
    }

    public function get_description() {
        $programid = isset($this->other['programid']) ? $this->other['programid'] : 0;
        $userid = isset($this->relateduserid) ? $this->relateduserid : 0;
        return "The user with id '{$this->userid}' assigned user '{$userid}' as coordinator for program '{$programid}'.";
    }
}
