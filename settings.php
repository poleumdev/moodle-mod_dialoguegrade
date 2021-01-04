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
 * Configure site-wide settings specific to the Dialogue modue
 *
 * Note: the only setting currently relates to unread-post tracking - this will only be
 * supported in your courses if you have applied the patch in CONTRIB-1134 which modifies
 * course/lib.php to check and display unread post counts in the course/topic area.
 * If you havent applied that patch this setting will still be stored in Moodle but it
 * will have no effect on the display of your courses, ie users will not see an unread
 * posts count
 *
 * @package mod_dialogue
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */
defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) {
    require_once($CFG->dirroot.'/mod/dialoguegrade/lib.php');

    // Whether to provide unread post count.
    $settings->add(new admin_setting_configcheckbox('dialoguegrade/trackunread',
                    new lang_string('configtrackunread', 'dialoguegrade'), '', 1));
    // Default total maxbytes of attached files.
    if (isset($CFG->maxbytes)) {
        $settings->add(new admin_setting_configselect('dialoguegrade/maxbytes',
                    new lang_string('maxattachmentsize', 'dialoguegrade'),
                    new lang_string('configmaxbytes', 'dialoguegrade'), 512000, get_max_upload_sizes($CFG->maxbytes)));
    }

    $choices = array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 20);
    // Default number of attachments allowed per post in all dialogues.
    $settings->add(new admin_setting_configselect('dialoguegrade/maxattachments',
                new lang_string('maxattachments', 'dialoguegrade'),
                new lang_string('configmaxattachments', 'dialoguegrade'), 5, $choices));

    $settings->add(new admin_setting_configcheckbox('dialoguegrade/viewconversationsbyrole',
                   new lang_string('viewconversationsbyrole', 'dialoguegrade'),
                   new lang_string('configviewconversationsbyrole', 'dialoguegrade'), 0));

}