<?php
namespace local_academicpanel\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

class rule_form extends \moodleform {

    public function definition() {
        $mform = $this->_form;

        $mform->addElement('header', 'ruleheader', get_string('managerules', 'local_academicpanel'));

        $mform->addElement('text', 'gradecutoff', get_string('gradecutoff', 'local_academicpanel'));
        $mform->setType('gradecutoff', PARAM_FLOAT);
        $mform->addRule('gradecutoff', get_string('required'), 'required', null, 'client');

        $mform->addElement('text', 'rolesincluded', get_string('rolesincluded', 'local_academicpanel'));
        $mform->setType('rolesincluded', PARAM_TEXT);
        $mform->addRule('rolesincluded', get_string('required'), 'required', null, 'client');

        $mform->addElement('select', 'engagementfallback', get_string('engagementfallback', 'local_academicpanel'), [
            'none' => get_string('engagementfallback_none', 'local_academicpanel'),
            'access' => get_string('engagementfallback_access', 'local_academicpanel'),
            'completion' => get_string('engagementfallback_completion', 'local_academicpanel'),
        ]);

        $this->add_action_buttons(false, get_string('savechanges'));
    }

    public function validation($data, $files) {
        global $DB;

        $errors = parent::validation($data, $files);

        $raw = isset($data['rolesincluded']) ? (string)$data['rolesincluded'] : '';
        $shortnames = array_filter(array_map('trim', explode(',', $raw)));

        if (empty($shortnames)) {
            $errors['rolesincluded'] = get_string('required');
            return $errors;
        }

        list($insql, $params) = $DB->get_in_or_equal($shortnames, SQL_PARAMS_NAMED);
        $existing = $DB->get_fieldset_select('role', 'shortname', 'shortname ' . $insql, $params);
        $missing = array_diff($shortnames, $existing);

        if (!empty($missing)) {
            $errors['rolesincluded'] = get_string('invalidroleshortname', 'local_academicpanel', implode(', ', $missing));
        }

        if (isset($data['gradecutoff'])) {
            $cutoff = (float)$data['gradecutoff'];
            if ($cutoff < 0 || $cutoff > 10) {
                $errors['gradecutoff'] = get_string('invalidgradecutoff', 'local_academicpanel');
            }
        }

        return $errors;
    }
}
