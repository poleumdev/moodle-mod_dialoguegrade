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
 * Redirect the user to the appropriate submission related page
 *
 * @package   mod_newmodule
 * @category  grade
 * @copyright 2016 Your Name <your@email.address>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . "../../../config.php");

$id = required_param('id', PARAM_INT);// Course module ID.

// Ajout.
$cm = get_coursemodule_from_id('dialoguegrade', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
require_login($course, false, $cm);

// Item number may be != 0 for activities that allow more than one grade per user.
$itemnumber = optional_param('itemnumber', 0, PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT); // Graded user ID (optional).

if ($userid != null) {
    $sql = "select conversationid
              from {dialoguegrade_participants}
             where userid = ?
               and dialogueid=?";
    $conversationlist = $DB->get_recordset_sql ( $sql, array ($userid, $cm->instance));
    $data = array ();
    foreach ($conversationlist as $conv) {
        $data [] = $conv;
    }
    if (count($data) == 1) {
        redirect('conversation.php?id='.$id.'&action=view&conversationid='.$data[0]->conversationid);
        return;
    } else if (count($data) == 0) {
        redirect('view.php?id='.$id);
        return;
    }
    // Passer en revue les conversations pour prendre la premiere avec la note du carnet.
    $gradeid = optional_param('gradeid', 0, PARAM_INT);
    $grade = $DB->get_record('grade_grades', array('id' => $gradeid), '*', MUST_EXIST);
    foreach ($data as $conv) {
        $sql = "select conversationid
                  from {dialoguegrade_messages}
                 where conversationid = ?
                   and grading = ?
                   and dialogueid = ?";
        $test = $DB->record_exists_sql($sql, array ($conv->conversationid, $grade->rawgrade, $cm->instance));
        if ($test) {
            redirect('conversation.php?id='.$id.'&action=view&conversationid='.$conv->conversationid);
            return;
        }
    }

}
// In the simplest case just redirect to the view page.
redirect('view.php?id='.$id);
