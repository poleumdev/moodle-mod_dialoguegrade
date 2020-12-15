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

namespace mod_dialoguegrade;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../../lib/filelib.php');

class conversation extends message {

    protected $_conversationid = null;
    protected $_subject = '';
    protected $_participants = null;
    protected $_replies = array();
    protected $_receivedby = null;

    /**
     *
     * @global type $DB
     * @param dialogue $dialogue
     * @param type $conversationid
     */
    public function __construct(dialogue $dialogue, $conversationid = null) {
        global $DB;

        parent::__construct($dialogue, $this);

        $this->_conversationindex = 1;

        if ($conversationid) {
            if (!is_int($conversationid)) {
                throw new \coding_exception('$conversationid must be an interger');
            }
            $this->_conversationid = $conversationid;
            $this->load();
        }
    }

    public function add_participant($userid) {
        $dialogue = $this->dialogue;
        $participant = dialoguegrade_get_user_details($dialogue, $userid);
        return $this->_participants[$userid] = $participant;
    }

    public function clear_participants() {
        return $this->_participants = null;
    }


    /**
     * Sets up a new conversation based on current conversation and dialogue, and
     * copies:
     *
     *  -   author
     *  -   subject
     *  -   body
     *  -   attachments
     *  -   state
     * any associated files are moved to draft area
     *
     * @return conversation
     */
    public function copy() {
        // Create new conversation.
        $copy = new conversation($this->dialogue);
        $copy->set_author($this->_authorid);
        $copy->set_subject($this->_subject);
        // Prep html linked embedded if html, move to draft area.
        if ($this->bodyformat == FORMAT_HTML) {
            // HTML.
            $context = $this->dialogue->context;
            $body = \file_prepare_draft_area($copy->_bodydraftid, $context->id, 'mod_dialoguegrade', 'message',
                                            $this->messageid, null, $this->body);
        } else {
            // Plaintext.
            $body = $this->body;
        }
        // Set the body up on the conversation.
        $copy->set_body($body, $this->bodyformat);
        // Prep attachments, move to draft area.
        if ($this->attachments) {
            $copy->_attachments = true;
            $context = $this->dialogue->context;
            \file_prepare_draft_area($copy->_attachmentsdraftid, $context->id, 'mod_dialoguegrade', 'attachment', $this->messageid);
        }
        // Must set state to draft as a copy.
        $copy->set_state(dialogue::STATE_DRAFT);
        // Return copied conversation.
        return $copy;
    }

    public function close() {
        global $DB, $USER;

        $context = $this->dialogue->context;
        $cm      = $this->dialogue->cm;
        $course  = $this->dialogue->course;

        // Is this a draft.
        if (is_null($this->_conversationid)) {
            throw new \moodle_exception('cannotclosedraftconversation', 'dialoguegrade');
        }
        // Permission check.
        $canclose = (($this->_authorid == $USER->id) or has_capability('mod/dialoguegrade:closeany', $context));
        if (!$canclose) {
            throw new \moodle_exception('nopermissiontoclose', 'dialoguegrade');
        }

        $openstate = dialogue::STATE_OPEN;
        $closedstate = dialogue::STATE_CLOSED;
        $params = array('conversationid' => $this->conversationid, 'state' => $openstate);

        // Close all messages in conversation that have a open state, we don't worry about drafts etc.
        $DB->set_field('dialoguegrade_messages', 'state', $closedstate, $params);

        return true;
    }

    public function delete() {
        global $DB, $USER;

        $cm      = $this->dialogue->cm;
        $course  = $this->dialogue->course;
        $context = $this->dialogue->context;

        // Hasn't been saved yet.
        if (is_null($this->_conversationid)) {
            return true;
        }
        // Permission to delete conversation.
        $candelete = ((has_capability('mod/dialoguegrade:delete', $context) and $USER->id == $this->_authorid) or
            has_capability('mod/dialoguegrade:deleteany', $context));

        if (!$candelete) {
            throw new \moodle_exception('nopermissiontodelete', 'dialoguegrade');
        }

        // Suppression dans carnet note.
        $participants = $this->__get("participants");
        $userid = -1;
        $nb = 0;
        foreach ($participants as $participant) {
            if ($participant->id != $USER->id) {
                $userid = $participant->id;
                $nb++;
            }
        }
        if ($nb == 1) { // Un seul destinataire en relation avec l'enseignant (USER).
            $dialogue = $this->__get("dialogue");
            $dialoguebdd = $dialogue->__get("module");
            dialoguegrade_grade_item_delete_user($dialoguebdd, $userid,  $this->_conversationid);
        }

        // Delete flags.
        $DB->delete_records('dialoguegrade_flags', array('conversationid' => $this->_conversationid));
        // Delete participants.
        $DB->delete_records('dialoguegrade_participants', array('conversationid' => $this->_conversationid));
        // Delete replies.
        foreach ($this->replies() as $reply) {
            $reply->delete();
        }
        // Delete conversation.
        $DB->delete_records('dialoguegrade_conversations', array('id' => $this->_conversationid));

        parent::delete();
    }

    /**
     * Load DB record data onto Class, conversationid needed.
     *
     * @global stdClass $DB
     * @throws coding_exception
     */
    protected function load() {
        global $DB;

        if (is_null($this->conversationid)) {
            throw new \coding_exception('conversationid not set so cannot load!');
        }

        $sql = "SELECT dc.subject, dm.*
                  FROM {dialoguegrade_conversations} dc
                  JOIN {dialoguegrade_messages} dm ON dm.conversationid = dc.id
                 WHERE dm.conversationindex = 1
                   AND dc.id = :conversationid";

        $record = $DB->get_record_sql($sql, array('conversationid' => $this->conversationid), MUST_EXIST);

        $this->_subject = $record->subject;
        $this->_authorid = $record->authorid;
        $this->_messageid = $record->id;
        $this->_body = $record->body;
        $this->_bodyformat = $record->bodyformat;
        $this->_attachments = $record->attachments;
        $this->_state = $record->state;
        $this->_timemodified = $record->timecreated;
        $this->_timemodified = $record->timemodified;
    }

    protected function load_participants() {
        global $DB;

        $this->_participants = array(); // Clear participants array if previous loaded.
        $dialogue = $this->dialogue;

        $params = array('conversationid' => $this->_conversationid);
        $records = $DB->get_records('dialoguegrade_participants', $params);
        foreach ($records as $record) {
            // Key up on userid and fetch brief details from cache as value (cut down user record).
            $this->_participants[$record->userid] = dialoguegrade_get_user_details($dialogue, $record->userid);
        }
        return $this->_participants;
    }

    /**
     *
     * @global type $CFG
     * @return \mod_dialogue_conversation_form
     * @throws moodle_exception
     */
    public function initialise_form() {
        global $CFG, $USER, $PAGE;
        require_once($CFG->dirroot . '/mod/dialoguegrade/formlib.php');

        // Form can only be initialise if in draft state.
        if ($this->state != dialogue::STATE_DRAFT) {
            throw new \moodle_exception('Oh! Ah, yes... I see that you know your judo well...');
        }

        $cm = $this->dialogue->cm;
        $context = $this->dialogue->context;
        $dialogueid = $this->dialogue->dialogueid;

        require_capability('mod/dialoguegrade:open', $context);

        $form = new \mod_dialoguegrade_conversation_form();
        // Setup important hiddens.
        $form->set_data(array('id' => $cm->id));
        $form->set_data(array('cmid' => $cm->id));
        $form->set_data(array('dialogueid' => $dialogueid));
        $form->set_data(array('conversationid' => $this->_conversationid));
        $form->set_data(array('messageid' => $this->_messageid));
        if (is_null($this->_messageid)) {
            $form->set_data(array('action' => 'create'));
        } else {
            $form->set_data(array('action' => 'edit'));
        }
        $options = array();
        $selected = array();

        $participants = $this->participants; // Insure loaded by using magic.
        if ($participants) {
            foreach ($participants as $participant) {
                $options[$participant->id] = fullname($participant);
                $selected[] = $participant->id;
            }
            $optiongroup = array('' => $options); // Cause formslib selectgroup is stupid.
        } else {
            $optiongroup = array(get_string('usesearch', 'dialoguegrade') => array('' => ''));
        }

        $json = json_encode($participants);

        $PAGE->requires->yui_module('moodle-mod_dialoguegrade-autocomplete',
              'M.mod_dialoguegrade.autocomplete.init', array($cm->id, $json));

        $form->update_selectgroup('p_select', $optiongroup, $selected);

        // Set subject.
        $form->set_data(array('subject' => $this->_subject));
        // Prep draft body.
        $draftbody = \file_prepare_draft_area($this->_bodydraftid, $context->id, 'mod_dialoguegrade',
                                            'message', $this->_messageid, \mod_dialoguegrade_conversation_form::editor_options(),
                                            $this->_body);
        // Set body.
        $form->set_data(array('body' =>
            array('text' => $draftbody,
                'format' => $this->_bodyformat,
                'itemid' => $this->_bodydraftid)));

        // Prep draft attachments.
        \file_prepare_draft_area($this->_attachmentsdraftid, $context->id, 'mod_dialoguegrade',
                                'attachment', $this->_messageid, \mod_dialoguegrade_conversation_form::attachment_options());
        // Set attachments.
        $form->set_data(array('attachments[itemid]' => $this->_attachmentsdraftid));

        // Remove any unecessary buttons.
        if (($USER->id != $this->author->id) or is_null($this->conversationid)) {
            $form->remove_from_group('trash', 'actionbuttongroup');
        }

        // Attach initialised form to conversation class and return.
        return $this->_form = $form;
    }

    protected function magic_get_conversationid() {
        return $this->_conversationid;
    }

    protected function magic_get_participants() {
        if (is_null($this->_participants)) {
            $this->load_participants();
        }
        return $this->_participants;
    }

    protected function magic_get_receivedby() {
        global $DB;

        if (is_null($this->_receivedby)) {
            $params = array('conversationid' => $this->conversationid,
                'flag' => dialogue::FLAG_SENT);

            $this->_receivedby = $DB->get_records('dialoguegrade_flags', $params, null, 'userid, timemodified');
        }
        return $this->_receivedby;
    }
    /**
     * Do not call this method directly
     *
     * @return string subject
     */
    protected function magic_get_subject() {
        return $this->_subject;
    }

    /**
     * Return a reply mapped to current dialogue.
     *
     * @return \dialogue_reply
     * @throws moodle_exception
     */
    public function reply() {
        if ($this->state != dialogue::STATE_OPEN) {
            throw new \moodle_exception('a reply can only be started when a conversation is open');
        }
        return new reply($this->_dialogue, $this);
    }

    public function replies($index = null) {
        global $DB;

        if (empty($this->_replies)) {
            // Only all replies in an open or close state, a reply should never be automated
            // and drafts are no in the line of published conversation.
            $items = array(dialogue::STATE_OPEN, dialogue::STATE_CLOSED);

            list($insql, $inparams) = $DB->get_in_or_equal($items, SQL_PARAMS_NAMED, 'viewstate');

            $sql = "SELECT dm.*
                      FROM {dialoguegrade_messages} dm
                     WHERE dm.conversationindex > 1
                       AND dm.state $insql
                       AND dm.conversationid = :conversationid
                  ORDER BY dm.conversationindex DESC";

            $params = array('conversationid' => $this->conversationid) + $inparams;

            $records = $DB->get_records_sql($sql, $params);
            foreach ($records as $record) {
                $reply = new reply($this->_dialogue, $this);
                $reply->load($record);
                $this->_replies[$record->id] = $reply;
            }
        }
        if ($index) {
            if (!isset($this->_replies[$index])) {
                throw new \coding_exception('index not defined');
            }
            return $this->_replies[$index];
        }
        return $this->_replies;
    }

    public function save() {
        global $DB, $USER;

        $admin = get_admin(); // Possible cronjob.
        if ($USER->id != $admin->id and $USER->id != $this->_authorid) {
            throw new \moodle_exception("This conversation doesn't belong to you!");
        }

        $course = $this->dialogue->course;
        $dialogueid = $this->dialogue->dialogueid;

        // Conversation record.
        $record = new \stdClass();
        $record->id = $this->_conversationid;
        $record->course = $course->id;
        $record->dialogueid = $dialogueid;
        $record->subject = $this->_subject;

        // We need a conversationid.
        if (is_null($this->_conversationid)) {
            // Create new record.
            $this->_conversationid = $DB->insert_record('dialoguegrade_conversations', $record);
        } else {
            $record->timemodified = time();
            // Update existing record.
            $DB->update_record('dialoguegrade_conversations', $record);
        }

        $this->save_participants();

        // Now let dialogue_message do it's thing.
        parent::save();
    }

    public function save_form_data() {
        // Incoming form data.
        $data = $this->_form->get_submitted_data();

        // Shortcut set of participants for now @todo - make better.
        $this->clear_participants();
        if (!empty($data->people)) {
            $participants = (array) $data->people; // May be single value.
            foreach ($participants as $userid) {
                $this->add_participant($userid);
            }
        }

        $this->set_subject($data->subject);
        $this->set_body($data->body['text'], $data->body['format'], $data->body['itemid']);
        if (isset($data->attachments)) {
            $this->set_attachmentsdraftid($data->attachments['itemid']);
        }
        $this->save();

        $this->_formdatasaved = true;
    }

    protected function save_participants() {
        global $DB;

        $dialogueid = $this->dialogue->dialogueid;
        $conversationid = $this->_conversationid;

        if (is_null($conversationid)) {
            throw new \coding_exception("conversation must exist before participants can be saved!");
        }

        $participants = $this->_participants;
        if ($participants) {
            foreach ($participants as $userid => $participant) {
                $params = array('conversationid' => $conversationid, 'userid' => $userid);
                if (!$DB->record_exists('dialoguegrade_participants', $params)) {
                    $record = new \stdClass();
                    $record->dialogueid = $dialogueid;
                    $record->conversationid = $conversationid;
                    $record->userid = $userid;
                    $DB->insert_record('dialoguegrade_participants', $record);
                }
            }
        } else {
            $DB->delete_records('dialoguegrade_participants', array('conversationid' => $conversationid));
        }
        // Refresh.
        $this->load_participants();
    }

    public function send() {
        global $USER, $DB;

        $cm      = $this->dialogue->cm;
        $course  = $this->dialogue->course;

        $incomplete = (empty($this->_participants) or
            empty($this->_subject) or empty($this->_body));

        if ($incomplete) {
            throw new \moodle_exception("Incomplete conversation cannot send!");
        }

        parent::send();
    }

    public function set_subject($subject) {
        $this->_subject = format_string($subject);
    }

}
