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
 * changes the subject and the recipient of the conversation, Use the form changeparamsform.php.
 *
 * @package   mod_dialoguegrade
 * @copyright 2020 Le Mans university
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(dirname(dirname(dirname(__FILE__))).'/config.php');

require_once($CFG->dirroot . '/mod/dialoguegrade/lib.php');
require_once($CFG->dirroot . '/mod/dialoguegrade/locallib.php');
require_once($CFG->dirroot . '/mod/dialoguegrade/formlib.php');
require_once($CFG->dirroot . '/lib/completionlib.php');

require_once('changeparamsform.php');

global $DB;
$id             = required_param('id', PARAM_INT);
$conversationid = optional_param('conversationid', null, PARAM_INT);

$cm = get_coursemodule_from_id('dialoguegrade', $id);
if (! $cm) {
    print_error('invalidcoursemodule');
}
$activityrecord = $DB->get_record('dialoguegrade', array('id' => $cm->instance));
if (! $activityrecord) {
    print_error('invalidid', 'dialoguegrade');
}
$course = $DB->get_record('course', array('id' => $activityrecord->course));
if (! $course) {
    print_error('coursemisconf');
}
$context = \context_module::instance($cm->id, MUST_EXIST);

require_login($course, false, $cm);

$pageparams   = array('id' => $id, 'conversationid' => $conversationid);
$pageurl      = new moodle_url('/mod/dialoguegrade/changeconversationparams.php', $pageparams);
$returnurl    = new moodle_url('/mod/dialoguegrade/view.php', array('id' => $cm->id));
if (isset($SESSION->dialoguereturnurl)) {
    $returnurl = $SESSION->dialoguereturnurl;
}

$PAGE->set_pagetype('mod-dialogue-conversation');
$PAGE->set_cm($cm, $course, $activityrecord);
$PAGE->set_context($context);
$PAGE->set_cacheable(false);
$PAGE->set_url($pageurl);

$dialogue = new \mod_dialoguegrade\dialogue($cm, $course, $activityrecord);
$conversation = new \mod_dialoguegrade\conversation($dialogue, $conversationid);

$PAGE->set_title(get_string('updatedialoguegrade', 'dialoguegrade'));
$PAGE->set_heading(get_string('updatedialoguegrade', 'dialoguegrade'));

// Obtenir la liste des enseignants.
list($esql, $eparams) = get_enrolled_sql($context, 'mod/dialoguegrade:bepotentialteacher', null, true);
$result = $DB->get_records_sql($esql, $eparams);
$teachers = array();
$teachersid = array();
foreach ($result as $teacher) {
    $enreg = $DB->get_record('user', array('id' => $teacher->id));
    $teachers[$enreg->id] = $enreg->lastname . " " . $enreg->firstname;
    $teachersid[] = $enreg->id;
}
$toform = array('my_array' => array('teachers' => $teachers));
$mform = new changeparamsform(null, $toform);

$valdefault = array(); // Valeurs par défaut pour le formulaire.
$valdefault['subject'] = $conversation->__get("subject");
$valdefault['conversationid'] = $conversationid;
$valdefault['id'] = $id;

// Recherche du correcteur (parmis la liste inscrit au cours).
$particip = $conversation->__get("participants");
$participantsid = array();
foreach ($particip as $participant) {
    $participantsid[] = intval($participant->id);
}
$teachersparticipants = array_intersect($teachersid, $participantsid); // Valeurs présentent dans les 2 tableaux.
foreach ($particip as $participant) {
    $valid = intval($participant->id);
    if (in_array($valid, $teachersid)) {
        $valdefault['teacher'] = $valid;
        break;
    }
}

if ($fromform = $mform->get_data()) {
    // In this case you process validated data. $mform->get_data() returns data posted in form. !
    $datas = $mform->get_data();

    $redirecturl = new \moodle_url('/mod/dialoguegrade/conversation.php',
                              array('id' => $id, 'conversationid' => $conversationid));

    if (isset($datas->cancel)) {
        redirect($redirecturl);
        return;
    }

    // Traitement des modifs.
    $dialogueid = $dialogue->__get("dialogueid");
    if ($datas->subject != $valdefault['subject']) {
        $request = " UPDATE {dialoguegrade_conversations}
                        SET subject = ?
                      WHERE course = ? and dialogueid = ?";

        $DB->execute($request, array($datas->subject, $course->id, $dialogueid));
    }

    $messageid = $conversation->__get('messageid');
    if (count($particip) == 2) {
        if ($datas->teacher != $valdefault['teacher']) {
            chgt_teacher($conversationid, $dialogueid, $messageid, $datas->teacher, $valdefault['teacher']);
        }
    } else {
        $otherteacher = "";
        foreach ($teachersparticipants as $teach) {
            if ($teach != $datas->teacher) {
                if (!empty($otherteacher)) {
                    $otherteacher .= ',';
                }
                $otherteacher .= $teach;
            }
        }
        // Test si le correcteur choisi fait déjà partie des participants.
        if (!in_array($datas->teacher, $teachersparticipants)) {
            // Insertion nouveau participant.
            $dataobject = new \stdClass();
            $dataobject->conversationid = $conversationid;
            $dataobject->dialogueid = $dialogueid;
            $dataobject->userid = $datas->teacher;

            $DB->insert_record('dialoguegrade_participants', $dataobject);
        }

        // Suppression des autres enseignants participants.
        $request = "DELETE from {dialoguegrade_participants}
                     WHERE conversationid = ? and dialogueid = ? and userid in (" . $otherteacher . ")";
        $DB->execute($request, array($conversationid, $dialogueid));

        // Maj Auteur du dialog.
        $request = "UPDATE {dialoguegrade_messages}
                       SET authorid = ?
                     WHERE conversationid = ? and dialogueid = ? and authorid in (" . $otherteacher . ")";
        $DB->execute($request, array($datas->teacher, $conversationid, $dialogueid));

        // MaJ de la table dialoguegrade_flags.
        $request = "UPDATE {dialoguegrade_flags}
                       SET userid = ?
                     WHERE conversationid = ? and dialogueid = ? and messageid = ?
                       and userid in (" . $otherteacher . ")";
        $DB->execute($request, array($datas->teacher, $conversationid,
                                    $dialogueid, $messageid));
    }

    \core\notification::success(get_string('updateok', 'dialoguegrade'));
    redirect($redirecturl);
}

$formdata = $valdefault;
$mform->set_data($formdata);
// Display form page.
echo $OUTPUT->header();
echo $OUTPUT->heading($activityrecord->name);
if (!empty($dialogue->activityrecord->intro)) {
    echo $OUTPUT->box(format_module_intro('dialoguegrade', $dialogue->activityrecord, $cm->id), 'generalbox', 'intro');
}
echo (html_writer::tag('h2', ' '));
$mform->display();
echo $OUTPUT->footer($course);

function chgt_teacher($conversationid, $dialogueid, $messageid, $newteacher, $oldteacher) {
    global $DB;
    // M.a.J de la table dialoguegrade_participants.
    $request = "UPDATE {dialoguegrade_participants}
                    SET userid = ?
                  WHERE conversationid = ? and dialogueid = ? and userid = ?";
    $DB->execute($request, array($newteacher, $conversationid, $dialogueid, $oldteacher));

    // MaJ de la table de dialoguegrade_messages (cas ou l'enseignant à initier le dialogue).
    $request = " UPDATE {dialoguegrade_messages}
                    SET authorid = ?
                  WHERE authorid = ? and conversationid = ? and dialogueid = ?";
    $DB->execute($request, array($newteacher, $oldteacher, $conversationid, $dialogueid));

    // MaJ de la table dialoguegrade_flags.
    $request = " UPDATE {dialoguegrade_flags}
                    SET userid = ?
                  WHERE userid = ? and conversationid = ? and dialogueid = ? and messageid = ?";
    $DB->execute($request, array($newteacher, $oldteacher, $conversationid,
                                    $dialogueid, $messageid));
}
