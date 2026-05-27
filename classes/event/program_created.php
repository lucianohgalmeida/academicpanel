<?php
namespace local_academicpanel\event;

defined('MOODLE_INTERNAL') || die();

class program_created extends \core\event\base {

    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_acpanel_program';
    }

    public static function get_name() {
        return get_string('event_program_created', 'local_academicpanel');
    }

    public function get_description() {
        return "The user with id '{$this->userid}' created the academic program with id '{$this->objectid}'.";
    }
}
