<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

defined('MOODLE_INTERNAL') || die();

// Load repository lib, will load filelib and formslib !
require_once($CFG->dirroot . '/repository/lib.php');

class changeparamsform extends moodleform {

    /**
     * Method automagically called when the form is instanciated. It defines
     * all the elements (inputs, titles, buttons, ...) in the form.
     */
    protected function definition() {
        $mform    = $this->_form;

        $mform->addElement('text', 'subject', get_string('subject', 'dialoguegrade'), array("size" => 45));
        $mform->setType('subject', PARAM_NOTAGS);

        $lstteacher = $this->_customdata['my_array']['teachers'];
        $mform->addElement('select', 'teacher', get_string('proofreader', 'dialoguegrade'), $lstteacher, null);
        // Hiddens fields.
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'conversationid');
        $mform->setType('conversationid', PARAM_INT);

        $mform->addElement('header', 'actionssection', get_string('actions', 'dialoguegrade'));

        $actionbuttongroup = array();
        $actionbuttongroup[] =& $mform->createElement('submit', 'save', get_string('savechanges'),
                                                      array('class' => 'send-button'));
        $actionbuttongroup[] =& $mform->createElement('submit', 'cancel', get_string('cancel'),
                                                      array('class' => 'cancel-button'));

        $mform->addGroup($actionbuttongroup, 'actionbuttongroup', '', ' ', false);

        $mform->setExpanded('actionssection', true);
    }

    /**
     * Intercept the display of form so can format errors as notifications.
     */
    public function display() {
        global $OUTPUT;

        if ($this->_form->_errors) {
            foreach ($this->_form->_errors as $error) {
                echo $OUTPUT->notification($error, 'notifyproblem');
            }
            unset($this->_form->_errors);
        }

        parent::display();
    }

    /**
     * validate the form.
     * If name no lock, then name must be not null.
     * @param stdClass $data of form
     * @param string $files list of the form files
     * @return array of error.
     */
    public function validation($data, $files) {
        global $DB;
        $errors = parent::validation($data, $files);
        if (isset($data['cancel'])) {
            return $errors;
        }
        return $errors;
    }

    public function get_submit_action() {
        $submitactions = array('save', 'cancel');
        foreach ($submitactions as $submitaction) {
            if (optional_param($submitaction, false, PARAM_BOOL)) {
                return $submitaction;
            }
        }
        return null;
    }
}
