<?php
namespace local_academicpanel\event;

defined('MOODLE_INTERNAL') || die();

class mapping_updated extends \core\event\base {

    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_acpanel_category';
    }

    public static function get_name() {
        return get_string('event_mapping_updated', 'local_academicpanel');
    }

    public function get_description() {
        $programid = isset($this->other['programid']) ? $this->other['programid'] : 0;
        $semester = isset($this->other['semester']) ? $this->other['semester'] : '';
        return "The user with id '{$this->userid}' updated category mapping with id '{$this->objectid}' (program {$programid}, semester {$semester}).";
    }
}
