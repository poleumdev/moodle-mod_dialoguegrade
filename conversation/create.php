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

require_once(dirname ( __FILE__ ) . '/../../../config.php');
require_once($CFG->dirroot . '/mod/dialoguegrade/locallib.php');

$cmid = required_param ( 'cmid', PARAM_INT );

$cm = get_coursemodule_from_id ( 'dialoguegrade', $cmid );
if (! $cm) {
    print_error ( 'invalidcoursemodule' );
}
$activityrecord = $DB->get_record ( 'dialoguegrade', array (
        'id' => $cm->instance
) );
if (! $activityrecord) {
    print_error ( 'invalidid', 'dialogue' );
}
$course = $DB->get_record ( 'course', array (
        'id' => $activityrecord->course
) );
if (! $course) {
    print_error ( 'coursemisconf' );
}
$context = \context_module::instance ( $cm->id, MUST_EXIST );

require_login ( $course, false, $cm );

$pageparams = array (
        'cmid' => $cm->id
);
$pageurl = new moodle_url ( '/mod/dialoguegrade/conversation/create.php', $pageparams );
$returnurl = new moodle_url ( '/mod/dialoguegrade/view.php', array (
        'id' => $cm->id
) );
$draftsurl = new moodle_url ( '/mod/dialoguegrade/drafts.php', array (
        'id' => $cm->id
) );

$PAGE->set_cm ( $cm, $course, $activityrecord );
$PAGE->set_context ( $context );
$PAGE->set_cacheable ( false );
$PAGE->set_url ( $pageurl );

require_capability ( 'mod/dialoguegrade:open', $context );

$dialogue = new \mod_dialoguegrade\dialogue($cm, $course, $activityrecord);
$conversation = new \mod_dialoguegrade\conversation($dialogue); // New conversation.

$form = $conversation->initialise_form ();
if ($form->is_submitted ()) {
    $submitaction = $form->get_submit_action ();
    switch ($submitaction) {
        case 'cancel' :
            redirect ( $returnurl );
        case 'send' :
            if ($form->is_validated ()) {
                $conversation->save_form_data ();
                $conversation->send ();

                $sendmessage = get_string ( 'conversationopened', 'dialoguegrade' );
                // Trigger conversation created event.
                $eventparams = array (
                        'context' => $context,
                        'objectid' => $conversation->conversationid
                );
                $event = \mod_dialoguegrade\event\conversation_created::create ( $eventparams );
                $event->trigger ();

                redirect ( $returnurl, $sendmessage );
            }
            break; // Leave switch to display form page.
        case 'save' :
            $conversation->save_form_data ();
            redirect ( $draftsurl, get_string ( 'changessaved' ) );
        case 'trash' :
            $conversation->trash ();
            redirect ( $draftsurl, get_string ( 'draftconversationtrashed', 'dialoguegrade' ) );
    }
}

// Display form page.
echo $OUTPUT->header ();
echo $OUTPUT->heading ( $activityrecord->name );
if (! empty ( $dialogue->activityrecord->intro )) {
    echo $OUTPUT->box ( format_module_intro ( 'dialoguegrade', $dialogue->activityrecord, $cm->id ), 'generalbox', 'intro' );
}
$form->display ();
echo $OUTPUT->footer ( $course );