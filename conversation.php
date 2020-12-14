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
 * View, create or edit a conversation in a dialogue. Also displays reply
 * form if open conversation.
 *
 * @package   mod_dialoguegrade
 * @copyright 2018 Le Mans university
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once($CFG->dirroot . '/mod/dialoguegrade/lib.php');
require_once($CFG->dirroot . '/mod/dialoguegrade/locallib.php');
require_once($CFG->dirroot . '/mod/dialoguegrade/formlib.php');

require_once($CFG->dirroot.'/lib/completionlib.php');

$id             = required_param('id', PARAM_INT);
$conversationid = optional_param('conversationid', null, PARAM_INT);
$action         = optional_param('action', 'view', PARAM_ALPHA);
$confirm        = optional_param('confirm', 0, PARAM_INT);

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

$pageparams   = array('id' => $id, 'conversationid' => $conversationid, 'action' => $action);
$pageurl      = new moodle_url('/mod/dialoguegrade/conversation.php', $pageparams);
$returnurl    = new moodle_url('/mod/dialoguegrade/view.php', array('id' => $cm->id));
if (isset($SESSION->dialoguereturnurl)) {
    $returnurl = $SESSION->dialoguereturnurl;
}
$draftsurl    = new moodle_url('/mod/dialoguegrade/drafts.php', array('id' => $cm->id));

$PAGE->set_pagetype('mod-dialogue-conversation');
$PAGE->set_cm($cm, $course, $activityrecord);
$PAGE->set_context($context);
$PAGE->set_cacheable(false);
$PAGE->set_url($pageurl);

$dialogue = new \mod_dialoguegrade\dialogue($cm, $course, $activityrecord);
$conversation = new \mod_dialoguegrade\conversation($dialogue, $conversationid);

// Form actions.
if ($action == 'create' or $action == 'edit') {
    require_capability('mod/dialoguegrade:open', $context);
    $form = $conversation->initialise_form();
    if ($form->is_submitted()) {
        $submitaction = $form->get_submit_action();
        switch ($submitaction) {
            case 'cancel':
                $completion=new completion_info($course);
                if($completion->is_enabled($cm) ) {//&& $forum->completionposts
                    $completion->update_state($cm,COMPLETION_COMPLETE);
                }
                redirect($returnurl);

            case 'send':
                if ($form->is_validated()){
                    $conversation->save_form_data();
                    $conversation->send();
                    $sendmessage = get_string('conversationopened', 'dialoguegrade');
                    // Trigger conversation created event
                    $eventparams = array(
                            'context' => $context,
                            'objectid' => $conversation->conversationid
                    );
                    $event = \mod_dialoguegrade\event\conversation_created::create($eventparams);
                    $event->trigger();

                    $completion=new completion_info($course);
                    if($completion->is_enabled($cm) ) {
                        $completion->update_state($cm,COMPLETION_COMPLETE);
                    }

                    redirect($returnurl, $sendmessage);
                }
                break; // leave switch to display form page
            case 'save':
                $conversation->save_form_data();
                redirect($draftsurl, get_string('changessaved'));
            case 'trash':
                $conversation->trash();
                redirect($draftsurl, get_string('draftconversationtrashed', 'dialoguegrade'));
        }
    }

    // display form page
    echo $OUTPUT->header();
    echo $OUTPUT->heading($activityrecord->name);
    if (!empty($dialogue->activityrecord->intro)) {
        echo $OUTPUT->box(format_module_intro('dialoguegrade', $dialogue->activityrecord, $cm->id), 'generalbox', 'intro');
    }
    $form->display();
    echo $OUTPUT->footer($course);
    exit;
}

// close conversation
if ($action == 'close') {
    if (!empty($confirm) && confirm_sesskey()) {
        $conversation->close();
        // Trigger conversation closed event
        $eventparams = array(
            'context' => $context,
            'objectid' => $conversation->conversationid
        );
        $event = \mod_dialoguegrade\event\conversation_closed::create($eventparams);
        $event->trigger();
        redirect($returnurl, get_string('conversationclosed', 'dialoguegrade',
                                        $conversation->subject));
    }
    echo $OUTPUT->header($activityrecord->name);
    $pageurl->param('confirm', $conversationid);
    $notification = $OUTPUT->notification(get_string('conversationcloseconfirm', 'dialoguegrade', $conversation->subject), 'notifymessage');
    echo $OUTPUT->confirm($notification, $pageurl, $returnurl);
    echo $OUTPUT->footer();
    exit;
}

// delete conversation
if ($action == 'delete') {
    if (!empty($confirm) && confirm_sesskey()) {
        $conversation->delete();
        // Trigger conversation created event
        $eventparams = array(
            'context' => $context,
            'objectid' => $conversation->conversationid
        );
        $event = \mod_dialoguegrade\event\conversation_deleted::create($eventparams);
        $event->trigger();
        // Redirect to the listing page we came from.
        redirect($returnurl, get_string('conversationdeleted', 'dialoguegrade',
                                        $conversation->subject));
    }
    echo $OUTPUT->header($activityrecord->name);
    $pageurl->param('confirm', $conversationid);
    $notification = $OUTPUT->notification(get_string('conversationdeleteconfirm', 'dialoguegrade', $conversation->subject), 'notifyproblem');
    echo $OUTPUT->confirm($notification, $pageurl, $returnurl);
    echo $OUTPUT->footer();
    exit;
}

// Ready for viewing, let's just make sure not a draft, possible url manipulation by user.
if ($conversation->state == \mod_dialoguegrade\dialogue::STATE_DRAFT) {
    redirect($returnurl);
}

if ($conversation->state == \mod_dialoguegrade\dialogue::STATE_OPEN or $conversation->state == \mod_dialoguegrade\dialogue::STATE_CLOSED) {
    if (!has_capability('mod/dialoguegrade:viewany', $context) and !$conversation->is_participant()) {
        throw new moodle_exception('nopermission');
    }
}
// View conversation by default.
$renderer = $PAGE->get_renderer('mod_dialoguegrade');
echo $OUTPUT->header($activityrecord->name);
echo $renderer->render($conversation);
$conversation->mark_read();

// Output reply form if meets criteria.
$hasreplycapability = (has_capability('mod/dialoguegrade:reply', $context) or
                       has_capability('mod/dialoguegrade:replyany', $context));

// conversation is open and user can reply... then output reply form
if ($hasreplycapability and $conversation->state == \mod_dialoguegrade\dialogue::STATE_OPEN) {
    $reply = $conversation->reply();
    $form = $reply->initialise_form();
    $form->display();
}

// render replies
if ($conversation->replies()) {
    foreach ($conversation->replies() as $reply) {
        echo $renderer->render($reply);
        $reply->mark_read();
    }
}

echo $OUTPUT->footer($course);
// Trigger conversation viewed event
$eventparams = array(
    'context' => $context,
    'objectid' => $conversation->conversationid
);
$event = \mod_dialoguegrade\event\conversation_viewed::create($eventparams);
$event->trigger();
