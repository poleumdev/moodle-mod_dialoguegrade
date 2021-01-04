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

/**
 * Indicates API features that the dialogue supports.
 *
 * @uses FEATURE_GROUPS
 * @uses FEATURE_MOD_INTRO
 * @uses FEATURE_BACKUP_MOODLE2
 * @param string $feature
 * @return mixed True if yes (some features may use other values)
 */
function dialoguegrade_supports($feature) {
    switch($feature) {
        case FEATURE_GROUPS:
            return false;
        case FEATURE_GROUPINGS:
            return false;
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_GRADE_OUTCOMES:
            return true;
        case FEATURE_RATE:
            return false;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        default:
            return null;
    }
}

/**
 * Adds a dialogue instance
 *
 * Given an object containing all the necessary data,
 * (defined by the form in mod.html) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @param stdClass $data
 * @param mod_dialogue_mod_form $form
 * @return int The instance id of the new dialogue or false on failure
 */

function dialoguegrade_add_instance($data) {
    global $DB;

    $data->timecreated = time();
    $data->timemodified = $data->timecreated;

    $result = $DB->insert_record('dialoguegrade', $data);
    $data->id = $result;
    dialoguegrade_grade_item_update($data);
    return $result;
}

/**
 * Updates a dialogue instance
 *
 * Given an object containing all the necessary data, (defined by the form in
 * mod.html) this function will update an existing instance with new data.
 *
 * @param stdClass $data
 * @param mod_dialogue_mod_form $form
 * @return bool true on success
 */
function dialoguegrade_update_instance($data, $mform) {
    global $DB;

    $data->timemodified = time();
    $data->id = $data->instance;

    $DB->update_record('dialoguegrade', $data);

    return true;
}

/**
 * Deletes a dialogue instance
 *
 * Given an ID of an instance of this module, this function will permanently
 * delete the instance and any data that depends on it.
 * @param   int     id of the dialogue object to delete
 * @return  bool    true on success, false if not
 */
function dialoguegrade_delete_instance($id) {
    global $DB;
    $dialogue = $DB->get_record('dialoguegrade', array('id' => $id), '*', MUST_EXIST);

    $cm = get_coursemodule_from_instance('dialoguegrade', $dialogue->id, $dialogue->course, false, MUST_EXIST);

    $context = context_module::instance($cm->id);

    $fs = get_file_storage();

    // Delete files.
    $fs->delete_area_files($context->id);
    // Delete flags.
    $DB->delete_records('dialoguegrade_flags', array('dialogueid' => $dialogue->id));
    // Delete participants.
    $DB->delete_records('dialoguegrade_participants', array('dialogueid' => $dialogue->id));
    // Delete messages.
    $DB->delete_records('dialoguegrade_messages', array('dialogueid' => $dialogue->id));
    // Delete conversations.
    $DB->delete_records('dialoguegrade_conversations', array('dialogueid' => $dialogue->id));
    // Delete dialogue.
    $DB->delete_records('dialoguegrade', array('id' => $dialogue->id));

    return true;
}

/**
 * Adds information about unread messages, that is only required for the course view page (and
 * similar), to the course-module object.
 * @param cm_info $cm Course-module object
 */
function dialoguegrade_cm_info_view(cm_info $cm) {
    global $CFG;
    require_once($CFG->dirroot . '/mod/dialoguegrade/locallib.php');

    // Get tracking status (once per request).
    static $initialised;
    static $usetracking, $strunreadmessagesone;
    if (!isset($initialised)) {
        if ($usetracking = dialoguegrade_can_track_dialogue()) {
            $strunreadmessagesone = get_string('unreadmessagesone', 'dialoguegrade');
        }
        $initialised = true;
    }

    if ($usetracking) {
        $unread = dialoguegrade_cm_unread_total(new \mod_dialoguegrade\dialogue($cm));
        if ($unread) {
            $out = '<span class="unread"> <a href="' . $cm->url . '">';
            if ($unread == 1) {
                $out .= $strunreadmessagesone;
            } else {
                $out .= get_string('unreadmessagesnumber', 'dialoguegrade', $unread);
            }
            $out .= '</a></span>';
            $cm->set_after_link($out);
        }
    }
}

/**
 * Return a small object with summary information about what a user has done
 * with a given particular instance of this module
 *  - $return->time = the time they did it
 *  - $return->info = a short text description
 *
 * Used for user activity reports.
 * @param   object  $course
 * @param   object  $user
 * @param   object  $dialogue
 *
 * @return stdClass|null
 */
function dialoguegrade_user_outline($course, $user, $mod, $dialogue) {
    global $DB;

    $sql = "SELECT COUNT(DISTINCT dm.timecreated) AS count,
                     MAX(dm.timecreated) AS timecreated
              FROM {dialoguegrade_messages} dm
             WHERE dm.dialogueid = :dialogueid
               AND dm.authorid = :userid
               AND dm.state = :state";

    $params = array('dialogueid' => $dialogue->id, 'userid' => $user->id, 'state' => \mod_dialoguegrade\dialogue::STATE_OPEN);
    $record = $DB->get_record_sql($sql, $params);
    if ($record) {
        $result = new stdClass();
        $result->info = $record->count.' '.get_string('messages', 'dialoguegrade');
        $result->time = $record->timecreated;
        return $result;
    }

    return null;
}

/**
 * Print a detailed representation of what a user has done with
 * a given particular instance of this module, for user activity reports.
 *
 * @param object $course
 * @param object $user
 * @param object $mod
 * @param object $dialogue
 * @return bool
 */
function dialoguegrade_user_complete($course, $user, $mod, $dialogue) {
    global $DB, $CFG, $OUTPUT;
    return true;
}

/**
 * Given a course and a date, prints a summary of all the new
 * messages posted in the course since that date
 *
 * @global object
 * @global object
 * @global object
 * @uses CONTEXT_MODULE
 * @uses VISIBLEGROUPS
 * @param object $course
 * @param bool $viewfullnames capability
 * @param int $timestart
 * @return bool success
 */
function dialoguegrade_print_recent_activity($course, $viewfullnames, $timestart) {
    global $CFG, $USER, $DB, $OUTPUT;
    return true;
}


/**
 * Return a list of 'view' actions to be reported on in the participation reports
 * @return  array of view action labels
 */
function dialoguegrade_get_view_actions() {
    return array('view', 'view all', 'view by role', 'view conversation');
}

/**
 * Return a list of 'post' actions to be reported on in the participation reports
 * @return array of post action labels
 */
function dialoguegrade_get_post_actions() {
    return array('open conversation', 'close conversation', 'delete conversation', 'reply');
}

/**
 * Returns all other caps used in module
 * @return array
 */
function dialoguegrade_get_extra_capabilities() {
    return array('moodle/site:accessallgroups', 'moodle/site:viewfullnames', 'moodle/site:trustcontent');
}


/**
 * Determine if a user can track dialogue entries.
 *
 * Checks the site dialogue activity setting and the user's personal preference
 * for trackread which is a similar requirement/preference so we treat them
 * as equals. This is closely modelled on similar function from course/lib.php
 *
 * @todo needs work
 * @param mixed $userid The user object to check for (optional).
 * @return boolean
 */
function dialoguegrade_can_track_dialogue($user = false) {
    global $USER, $CFG;

    $trackunread = get_config('dialoguegrade', 'trackunread');
    // Return unless enabled at site level.
    if (empty($trackunread)) {
        return false;
    }

    // Default to logged if no user passed as param.
    if ($user === false) {
        $user = $USER;
    }

    // Dont allow guests to track.
    if (isguestuser($user) or empty($user->id)) {
        return false;
    }

    return true;
}

/**
 * Serves the dialogue attachments. Implements needed access control ;-)
 *
 * @param object $course
 * @param object $cm
 * @param object $context
 * @param string $filearea
 * @param array $args
 * @param array $options
 * @param bool $forcedownload
 * @return bool false if file not found, does not return if found - justsend the file
 */
function dialoguegrade_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload = true, $options = array()) {
    global $CFG, $DB, $USER;

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    require_course_login($course, true, $cm);

    $fileareas = array('message', 'attachment');
    if (!in_array($filearea, $fileareas)) {
        return false;
    }

    $itemid = (int)array_shift($args);
    if (!$message = $DB->get_record('dialoguegrade_messages', array('id' => $itemid))) {
        return false;
    }

    if (!$conversation = $DB->get_record('dialoguegrade_conversations', array('id' => $message->conversationid))) {
        return false;
    }

    if (!$dialogue = $DB->get_record('dialoguegrade', array('id' => $cm->instance))) {
        return false;
    }

    $fs = get_file_storage();
    $relativepath = implode('/', $args);
    $fullpath = "/$context->id/mod_dialoguegrade/$filearea/$itemid/$relativepath";
    if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
        return false;
    }

    // Force non image formats to be downloaded.
    if (!$file->is_valid_image()) {
        $forcedownload = true;
    }

    // Send the file.
    send_stored_file($file, 0, 0, $forcedownload, $options);
}

/**
 * Create grade item for given dialogue.
 *
 * @param stdClass $dialogue record with extra cmidnumber
 * @param array $grades optional array/object of grade(s); 'reset' means reset grades in gradebook
 * @return int 0 if ok, error code otherwise
 */
function dialoguegrade_grade_item_update($dialogue, $grades=null) {
    global $CFG, $DB;
    if (!function_exists('grade_update')) {
        require_once($CFG->libdir.'/gradelib.php');
    }

    if (array_key_exists('cmidnumber', $dialogue)) {
        $params = array('itemname' => $dialogue->name, 'idnumber' => $dialogue->cmidnumber);
    } else {
        $params = array('itemname' => $dialogue->name);
    }

    if ($dialogue->grade > 0) {
        $params['gradetype']  = GRADE_TYPE_VALUE;
        $params['grademax']   = $dialogue->grade;
        $params['grademin']   = 0;
        $params['multfactor'] = 1.0;
    } else if ($dialogue->grade < 0) {
        $params['gradetype'] = GRADE_TYPE_SCALE;
        $params['scaleid']   = -$dialogue->grade;
    } else {
        $params['gradetype']  = GRADE_TYPE_NONE;
        $params['multfactor'] = 1.0;
    }

    if ($grades === 'reset') {
        $params['reset'] = true;
        $grades = null;
    }

    return grade_update('mod/dialoguegrade', $dialogue->course, 'mod', 'dialoguegrade', $dialogue->id, 0, $grades, $params);
}


/**
 * Update activity grades.
 *
 * @param stdClass $assign database record
 * @param int $userid specific user only, 0 means all
 * @param bool $nullifnone - not used
 */
function dialoguegrade_update_grades($conversation=null, $userid=0, $nullifnone=true) {
    global $CFG, $DB;
    if (!function_exists('grade_update')) {
        require_once($CFG->libdir.'/gradelib.php');
    }

    if ($conversation != null) {
        // Recherche de l'enregistrement dialogue correspondant.
        $dialogueobj = $conversation->__get("dialogue");
        $dialoguebdd = $dialogueobj->__get("module");

        if ($grades = dialoguegrade_get_user_grades($conversation, $userid)) {
            dialoguegrade_grade_item_update($dialoguebdd, $grades);
        } else if ($userid && $nullifnone) {
            $grade = new object();
            $grade->userid   = $userid;
            $grade->rawgrade = null;
            dialoguegrade_grade_item_update($dialoguebdd, $grade);
        } else {
            dialoguegrade_grade_item_update($dialoguebdd);
        }
    } else {
        $sql = "SELECT j.*, cm.idnumber as cmidnumber
                FROM {course_modules} cm
                JOIN {modules} m ON m.id = cm.module
                JOIN {dialoguegrade} j ON cm.instance = j.id
                WHERE m.name = 'dialogue'";
        if ($recordset = $DB->get_records_sql($sql)) {
            foreach ($recordset as $dial) {
                if ($dialogue->grade != false) {
                    dialoguegrade_update_grades($dial);
                } else {
                    dialoguegrade_grade_item_update($dial);
                }
            }
        }
    }
}

function dialoguegrade_grade_item_delete_all($dialogue) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    return grade_update('mod/dialoguegrade', $dialogue->course, 'mod',
                        'dialoguegrade', $dialogue->id, 0, null, array('deleted' => 1));
}

function dialoguegrade_grade_item_delete($dialogue) {
    dialoguegrade_grade_item_delete_all($dialogue);
}

function dialoguegrade_grade_item_delete_user($dialoguebdd, $userid, $conversationid) {
    global $CFG, $DB, $USER;
    require_once($CFG->libdir.'/gradelib.php');

    $teacherid = $USER->id;
    $userstr = 'AND authorid = '.$USER->id;

    $sql = "SELECT '$userid' as userid, timecreated as datesubmitted, 0 as feedbackformat,
    '' as rawgrade, '' as feedback, '$teacherid' as usermodifier, timecreated as dategraded
    FROM {dialoguegrade_messages}
    WHERE dialogueid = '$dialoguebdd->id' and conversationid= '$conversationid' ".$userstr;

    $grades = $DB->get_records_sql($sql);
    if ($grades) {
        foreach ($grades as $key => $grade) {
            $grades[$key]->id = $userid;
        }
    }

    $params = array('itemname' => $dialoguebdd->name);
    $params['gradetype']  = GRADE_TYPE_VALUE;
    $params['grademax']   = $dialoguebdd->grade;
    $params['grademin']   = 0;
    $params['multfactor'] = 1.0;

    return grade_update('mod/dialoguegrade', $dialoguebdd->course, 'mod', 'dialoguegrade', $dialoguebdd->id, 0, $grades, $params);
}

function dialoguegrade_get_user_grades($conversation, $userid=0) {
    global $DB;

    $teacherid = -1;
    if ($userid) {
        $participants = $conversation->participants;
        foreach ($participants as $participant) {
            if ($participant->id != $userid) {
                $teacherid = $participant->id;
            }
        }
        $userstr = 'AND authorid = '.$teacherid;
    } else {
        $userstr = '';
    }

    if (!$conversation) {
        return false;
    } else {
        $dialogue = $conversation->__get("dialogue");
        $dialoguebdd = $dialogue->__get("module");
        $sql = "SELECT '$userid' as userid, timecreated as datesubmitted, 0 as feedbackformat,
                        grading as rawgrade, body as feedback, '$teacherid' as usermodifier, timecreated as dategraded
                FROM {dialoguegrade_messages}
                WHERE dialogueid = '$dialoguebdd->id' and conversationid= '$conversation->conversationid' ".$userstr;

        $grades = $DB->get_records_sql($sql);

        if ($grades) {
            foreach ($grades as $key => $grade) {
                $grades[$key]->id = $userid;
            }
        } else {
            return false;
        }
        return $grades;
    }
}

function dialoguegrade_get_completion_state($course, $cm, $userid, $type) {
    global $DB;

    if (!($dlggrade = $DB->get_record('dialoguegrade', array('id' => $cm->instance)))) {
        throw new Exception("Can't find dialoguegrade {$cm->instance}");
    }

    $sql = "select conversationid
              from {dialoguegrade_participants}
             where userid = ?
               and dialogueid=?";
    $conversationlist = $DB->get_recordset_sql ( $sql, array ($userid, $cm->instance));
    $data = array ();
    foreach ($conversationlist as $conv) {
        $data [] = $conv;
    }
    if (!isset($data[0])) {
        return false;
    }
    $conversation = $data[0]->conversationid;

    $sql = "select * from {dialoguegrade_messages} where conversationid = ?";
    $conversationlist = $DB->get_recordset_sql ( $sql, array ($conversation));
    $nbstudent = 0;
    $nbresponse = 0;
    foreach ($conversationlist as $conv) {
        if ($conv->authorid == $userid) {
            $nbstudent ++;
        } else {
            $nbresponse ++;
        }
    }
    $result = $type;
    if ($dlggrade->completionsend) {
        $value = $nbstudent >= $dlggrade->completionsend;
        if ($type == COMPLETION_AND) {
            $result = $result && $value;
        } else {
            $result = $result || $value;
        }
    }
    if ($dlggrade->completionreplies) {
        $value = $nbresponse >= $dlggrade->completionreplies;
        if ($type == COMPLETION_AND) {
            $result = $result && $value;
        } else {
            $result = $result || $value;
        }
    }
    return $result;
}

// On demande confirmation de supprimer les journaux.
function dialoguegrade_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'dialoguegradeheader', 'Carnets de bord');
    $mform->addElement('advcheckbox', 'reset_cbord', 'Supprimer les entrées');
}

// Oui par défaut.
function dialoguegrade_reset_course_form_defaults($course) {
    return array('reset_cbord' => 1);
}

/**
 * On supprime toutes les instances du carnet de bord dans le cours
 * si reset_cbord definit (coché)
 * donc on supprime tout les dialogueid pour course = $data->courseid
 */
function dialoguegrade_reset_userdata($data) {
    global $DB;
    $status = array();
    if (!empty($data->reset_cbord)) {
        // Rechercher les instances dialoguegrade du cours.
        $req = "select id from {course_modules}
             where module in (select id from {modules} where name='dialoguegrade')
             and course = ?";
        $cmidlist = $DB->get_recordset_sql ($req, array ($data->courseid));
        // Delete files.
        $fs = get_file_storage();
        foreach ($cmidlist as $cmid) {
            $context = context_module::instance($cmid->id);
            $fs->delete_area_files($context->id);
        }

        // Delete flags.
        $req = "delete from {dialoguegrade_flags}
                  where dialogueid in (select id from {dialoguegrade}
                                        where course = ?)";
        $DB->execute($req, array($data->courseid));

        // Delete participants.
        $req = "delete from {dialoguegrade_participants}
                  where dialogueid in (select id from {dialoguegrade}
                                        where course = ?)";
        $DB->execute($req, array($data->courseid));

        // Delete messages.
        $req = "delete from {dialoguegrade_messages}
                  where dialogueid in (select id from {dialoguegrade}
                                        where course = ?)";
        $DB->execute($req, array($data->courseid));

        // Delete conversations.
        $req = "delete from {dialoguegrade_conversations}
                  where dialogueid in (select id from {dialoguegrade}
                                        where course = ?)";
        $DB->execute($req, array($data->courseid));

        $status[] = array('component' => 'Carnets de bord',
                          'item' => 'Supprimer les entrées',
                          'error' => false);
    }
    return $status;
}

