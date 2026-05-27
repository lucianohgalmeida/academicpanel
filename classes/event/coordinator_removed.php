<?php
namespace local_academicpanel\event;

defined('MOODLE_INTERNAL') || die();

class coordinator_removed extends \core\event\base {

    protected function init() {
        $this->data['crud'] = 'd';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_acpanel_coord';
    }

    public static function get_name() {
        return get_string('event_coordinator_removed', 'local_academicpanel');
    }

    public function get_description() {
        return "The user with id '{$this->userid}' removed the coordinator assignment with id '{$this->objectid}'.";
    }
}
