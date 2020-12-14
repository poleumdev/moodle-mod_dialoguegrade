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

/**
 * This file contains the forms to create and edit an instance of this module
 *
 * @package   mod_dialogue
 * @copyright 2013
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');

require_once($CFG->dirroot.'/mod/dialoguegrade/locallib.php');
require_once($CFG->dirroot.'/course/moodleform_mod.php');

class mod_dialoguegrade_mod_form extends moodleform_mod {

    protected function definition() {
        global $CFG, $COURSE, $DB;

        $mform    = $this->_form;

        $pluginconfig = get_config('dialoguegrade');

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('dialoguename', 'dialoguegrade'), array('size' => '64'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        moodleform_mod::standard_intro_elements();

        $choices = get_max_upload_sizes($CFG->maxbytes, $COURSE->maxbytes, $pluginconfig->maxbytes);

        $mform->addElement('select', 'maxbytes', get_string('maxattachmentsize', 'dialoguegrade'), $choices);
        $mform->addHelpButton('maxbytes', 'maxattachmentsize', 'dialoguegrade');
        $mform->setDefault('maxbytes', $pluginconfig->maxbytes);

        $choices = range(0, $pluginconfig->maxattachments);
        $choices[0] = get_string('uploadnotallowed');
        $mform->addElement('select', 'maxattachments', get_string('maxattachments', 'dialoguegrade'), $choices);
        $mform->addHelpButton('maxattachments', 'maxattachments', 'dialoguegrade');
        $mform->setDefault('maxattachments', $pluginconfig->maxattachments);

        $mform->addElement('checkbox', 'usecoursegroups', get_string('usecoursegroups', 'dialoguegrade'));
        $mform->addHelpButton('usecoursegroups', 'usecoursegroups', 'dialoguegrade');
        $mform->setDefault('usecoursegroups', 0);

        $this->standard_grading_coursemodule_elements();

        $this->standard_coursemodule_elements();

        $this->add_action_buttons();
    }

    public function get_data() {
        $data = parent::get_data();
        if (!$data) {
            return false;
        }
        if (!isset($data->usecoursegroups)) {
            $data->usecoursegroups = 0;
        }
        // Turn off completion settings if the checkboxes aren't ticked.
        if (!empty($data->completionunlocked)) {
            $autocompletion = !empty($data->completion) && $data->completion == COMPLETION_TRACKING_AUTOMATIC;

            if (empty($data->completionrepliesenabled) || !$autocompletion) {
                $data->completionreplies = 0;
            }
            if (empty($data->completionsendenabled) || !$autocompletion) {
                $data->completionsend = 0;
            }
        }
        return $data;
    }

    public function data_preprocessing(&$default_values) {
        parent::data_preprocessing($default_values);

        // Set up the completion checkboxes which aren't part of standard data.
        // We also make the default value (if you turn on the checkbox) for those
        // numbers to be 1, this will not apply unless checkbox is ticked.
        $default_values['completionsendenabled']= !empty($default_values['completionsend']) ? 1 : 0;
        if (empty($default_values['completionsend'])) {
            $default_values['completionsend']=1;
        }
        $default_values['completionrepliesenabled']=!empty($default_values['completionreplies']) ? 1 : 0;
        if (empty($default_values['completionreplies'])) {
            $default_values['completionreplies']=1;
        }
    }

    public function add_completion_rules() {
        $mform =& $this->_form;

        $group=array();
        $group[] =& $mform->createElement('checkbox', 'completionsendenabled', '', get_string('completionsend', 'dialoguegrade'));
        $group[] =& $mform->createElement('text', 'completionsend', '', array('size'=>3));
        $mform->setType('completionsend',PARAM_INT);
        $mform->addGroup($group, 'completionsendgroup', get_string('completionsendgroup', 'dialoguegrade'), array(' '), false);
        $mform->disabledIf('completionsend','completionsendenabled','notchecked');

        $group=array();
        $group[] =& $mform->createElement('checkbox', 'completionrepliesenabled', '',  get_string('completionreplies', 'dialoguegrade'));
        $group[] =& $mform->createElement('text', 'completionreplies', '', array('size'=>3));
        $mform->setType('completionreplies',PARAM_INT);
        $mform->addGroup($group, 'completionrepliesgroup', get_string('completionrepliesgroup', 'dialoguegrade'), array(' '), false);
        $mform->disabledIf('completionreplies','completionrepliesenabled','notchecked');

        return array('completionrepliesgroup','completionsendgroup');
    }
     // Validation.
    public function completion_rule_enabled($data) {
        return (!empty($data['completionsendenabled']) && $data['completionsend']!=0) || (!empty($data['completionrepliesenabled']) && $data['completionreplies']!=0) ;
    }
}
