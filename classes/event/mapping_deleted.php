<?php
namespace local_academicpanel\event;

defined('MOODLE_INTERNAL') || die();

class mapping_deleted extends \core\event\base {

    protected function init() {
        $this->data['crud'] = 'd';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_acpanel_category';
    }

    public static function get_name() {
        return get_string('event_mapping_deleted', 'local_academicpanel');
    }

    public function get_description() {
        return "The user with id '{$this->userid}' deleted category mapping with id '{$this->objectid}'.";
    }
}
