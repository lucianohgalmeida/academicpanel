<?php
namespace local_academicpanel\event;

defined('MOODLE_INTERNAL') || die();

class program_updated extends \core\event\base {

    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_acpanel_program';
    }

    public static function get_name() {
        return get_string('event_program_updated', 'local_academicpanel');
    }

    public function get_description() {
        $action = isset($this->other['action']) ? $this->other['action'] : 'edited';
        return "The user with id '{$this->userid}' performed action '{$action}' on academic program with id '{$this->objectid}'.";
    }
}
