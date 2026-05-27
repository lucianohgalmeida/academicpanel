<?php
namespace local_academicpanel\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

class coordinator_form extends \moodleform {

    public function definition() {
        $mform = $this->_form;

        $editmode = !empty($this->_customdata['editmode']);
        $headerkey = $editmode ? 'editcoordinator' : 'tab_coordinators';

        $mform->addElement('header', 'coordheader', get_string($headerkey, 'local_academicpanel'));

        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);

        $programs = isset($this->_customdata['programs']) ? $this->_customdata['programs'] : [];
        $mform->addElement('select', 'programid', get_string('program', 'local_academicpanel'), $programs);
        $mform->addRule('programid', get_string('required'), 'required', null, 'client');

        $mform->addElement('autocomplete', 'userids',
            get_string($editmode ? 'coordinator' : 'addcoordinators', 'local_academicpanel'),
            [], [
                'multiple' => !$editmode,
                'ajax' => 'core_user/form_user_selector',
                'noselectionstring' => get_string('searchusers', 'local_academicpanel'),
                'valuehtmlcallback' => function ($value) {
                    global $DB, $OUTPUT;
                    $user = $DB->get_record('user', ['id' => (int)$value, 'deleted' => 0], '*', IGNORE_MISSING);
                    if (!$user) {
                        return false;
                    }
                    return $OUTPUT->user_picture($user, ['size' => 24]) . ' ' . fullname($user);
                },
            ]
        );
        $mform->addRule('userids', get_string('required'), 'required', null, 'client');

        if ($editmode) {
            $this->add_action_buttons(true, get_string('savechanges'));
        } else {
            $this->add_action_buttons(false, get_string('savechanges'));
        }
    }

    public function validation($data, $files) {
        global $DB;

        $errors = parent::validation($data, $files);

        if (empty($data['programid']) || !$DB->record_exists('local_acpanel_program', ['id' => $data['programid']])) {
            $errors['programid'] = get_string('required');
        }

        if (empty($data['userids'])) {
            $errors['userids'] = get_string('required');
        } else {
            foreach ((array)$data['userids'] as $id) {
                if (!$DB->record_exists('user', ['id' => (int)$id, 'deleted' => 0])) {
                    $errors['userids'] = get_string('invaliduser', 'error');
                    break;
                }
            }
        }

        return $errors;
    }
}
