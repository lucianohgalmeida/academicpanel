<?php
namespace local_academicpanel\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

class program_form extends \moodleform {

    public function definition() {
        $mform = $this->_form;

        $mform->addElement('header', 'programheader', get_string('tab_programs', 'local_academicpanel'));

        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('text', 'programname', get_string('program', 'local_academicpanel'));
        $mform->setType('programname', PARAM_TEXT);
        $mform->addRule('programname', get_string('required'), 'required', null, 'client');

        $mform->addElement('text', 'programshortname', get_string('programkey', 'local_academicpanel'));
        $mform->setType('programshortname', PARAM_ALPHANUMEXT);
        $mform->addRule('programshortname', get_string('required'), 'required', null, 'client');

        $this->add_action_buttons(true, get_string('savechanges'));
    }

    public function validation($data, $files) {
        global $DB;

        $errors = parent::validation($data, $files);

        $shortname = isset($data['programshortname']) ? trim((string)$data['programshortname']) : '';
        $id = isset($data['id']) ? (int)$data['id'] : 0;

        if ($shortname !== '') {
            $existing = $DB->get_record('local_acpanel_program', ['shortname' => $shortname]);
            if ($existing && (int)$existing->id !== $id) {
                $errors['programshortname'] = get_string('programshortnametaken', 'local_academicpanel');
            }
        }

        return $errors;
    }
}
