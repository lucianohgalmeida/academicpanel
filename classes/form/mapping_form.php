<?php
namespace local_academicpanel\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

class mapping_form extends \moodleform {

    public function definition() {
        $mform = $this->_form;

        $mform->addElement('header', 'mappingheader', get_string('tab_mappings', 'local_academicpanel'));

        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);

        $programs = isset($this->_customdata['programs']) ? $this->_customdata['programs'] : [];
        $mform->addElement('select', 'programid', get_string('program', 'local_academicpanel'), $programs);
        $mform->addRule('programid', get_string('required'), 'required', null, 'client');

        $mform->addElement('select', 'categoryid', get_string('category', 'local_academicpanel'), self::category_options());
        $mform->addRule('categoryid', get_string('required'), 'required', null, 'client');

        $mform->addElement('text', 'semester', get_string('semester', 'local_academicpanel'));
        $mform->setType('semester', PARAM_TEXT);
        $mform->addRule('semester', get_string('required'), 'required', null, 'client');

        $this->add_action_buttons(true, get_string('savechanges'));
    }

    public function validation($data, $files) {
        global $DB;

        $errors = parent::validation($data, $files);

        if (empty($data['categoryid']) || !$DB->record_exists('course_categories', ['id' => $data['categoryid']])) {
            $errors['categoryid'] = get_string('invalidcategory', 'error');
        }

        if (empty($data['programid']) || !$DB->record_exists('local_acpanel_program', ['id' => $data['programid']])) {
            $errors['programid'] = get_string('required');
        }

        $semester = isset($data['semester']) ? trim((string)$data['semester']) : '';
        if (!preg_match('/^\d{4}\.\d$/', $semester)) {
            $errors['semester'] = get_string('invalidsemesterformat', 'local_academicpanel');
        }

        if (!empty($data['categoryid'])) {
            $existing = $DB->get_record('local_acpanel_category', ['categoryid' => (int)$data['categoryid']]);
            $myid = isset($data['id']) ? (int)$data['id'] : 0;
            if ($existing && (int)$existing->id !== $myid) {
                $errors['categoryid'] = get_string('mappingcategorytaken', 'local_academicpanel');
            }
        }

        return $errors;
    }

    private static function category_options() {
        $options = \core_course_category::make_categories_list();
        if (empty($options)) {
            return [0 => get_string('category', 'local_academicpanel')];
        }

        return $options;
    }
}
