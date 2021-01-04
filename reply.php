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
 * edit a reply in a conversation.
 *
 * @package   mod_dialogue
 * @copyright 2013 Troy Williams
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once($CFG->dirroot . '/mod/dialoguegrade/lib.php');
require_once($CFG->dirroot . '/mod/dialoguegrade/locallib.php');
require_once($CFG->dirroot . '/mod/dialoguegrade/formlib.php');

require_once($CFG->dirroot.'/lib/completionlib.php');

$id             = required_param('id', PARAM_INT);
$conversationid = required_param('conversationid', PARAM_INT);
$replyid        = optional_param('messageid', null, PARAM_INT);
$action         = optional_param('action', 'edit', PARAM_ALPHA);
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
$context = context_module::instance($cm->id, MUST_EXIST);

require_login($course, false, $cm);
require_capability('mod/dialoguegrade:reply', $context);

$pageparams   = array('id' => $id, 'conversationid' => $conversationid, 'action' => $action);
$pageurl      = new moodle_url('/mod/dialoguegrade/reply.php', $pageparams);
$returnurl    = new moodle_url('/mod/dialoguegrade/view.php', array('id' => $id));
if (isset($SESSION->dialoguereturnurl)) {
    $returnurl = $SESSION->dialoguereturnurl;
}
$draftsurl    = new moodle_url('/mod/dialoguegrade/drafts.php', array('id' => $id));

$PAGE->set_pagetype('mod-dialogue-reply');
$PAGE->set_cm($cm, $course, $activityrecord);
$PAGE->set_context($context);
$PAGE->set_cacheable(false);
$PAGE->set_url($pageurl);

$dialogue = new \mod_dialoguegrade\dialogue($cm, $course, $activityrecord);
$conversation = new \mod_dialoguegrade\conversation($dialogue, $conversationid);
$reply = new \mod_dialoguegrade\reply($dialogue, $conversation, $replyid);


if (!$reply->is_author()) {
    throw new \moodle_exception("You do not have permission to view this reply it doesn't
                                belong to you!");
}
// Initialise and check form submission.
$form = $reply->initialise_form();
if ($form->is_submitted()) {
    $formaction = $form->get_submit_action();
    switch ($formaction) {
        case 'cancel':
            $completion = new completion_info($course);
            if ($completion->is_enabled($cm)) {
                $completion->update_state($cm, COMPLETION_COMPLETE);
            }

            redirect($returnurl);
        case 'send':
            if ($form->is_validated()) {
                $reply->save_form_data();
                $reply->send();
                $eventparams = array( 'context' => $context, 'objectid' => $reply->messageid,
                    'other' => array('conversationid' => $conversation->conversationid) );
                $event = \mod_dialoguegrade\event\reply_created::create($eventparams);
                $event->trigger();

                $completion = new completion_info($course);
                if ($completion->is_enabled($cm) ) {
                    $completion->update_state($cm, COMPLETION_COMPLETE);
                }

                redirect($returnurl, get_string('replysent', 'dialoguegrade'));
            }
            break;
        case 'save':
            if ($form->is_validated()) {
                $reply->save_form_data();

                redirect($draftsurl, get_string('changessaved'));
            }
            break;
        case 'trash':
            $reply->trash();
            redirect($draftsurl, get_string('draftreplytrashed', 'dialoguegrade'));
    }
}
$renderer = $PAGE->get_renderer('mod_dialoguegrade');
echo $OUTPUT->header();
// Render conversation.
echo $renderer->render($conversation);
// Render replies.
if ($conversation->replies()) {
    foreach ($conversation->replies() as $reply) {
        echo $renderer->render($reply);
    }
}
// Output form.
$form->display();
echo $OUTPUT->footer($course);
