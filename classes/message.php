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

class message implements \renderable {

    protected $_dialogue = null;
    protected $_conversation = null;
    protected $_conversationindex = 0;
    protected $_messageid = null;
    protected $_authorid = null;
    protected $_body = '';
    protected $_bodyformat = null;
    protected $_bodydraftid = null;
    protected $_attachmentsdraftid = null;
    protected $_attachments = null;
    protected $_state = dialogue::STATE_DRAFT;
    protected $_timecreated = null;
    protected $_timemodified = null;
    protected $_form = null;
    protected $_formdatasaved = false;
    protected $_note = null;

    public function __construct(dialogue $dialogue = null, conversation $conversation = null) {
        global $USER;

        $this->_dialogue = $dialogue;
        $this->_conversation = $conversation;

        $this->_authorid = $USER->id;
        $this->_bodyformat = editors_get_preferred_format();
        $this->_timecreated = time();
        $this->_timemodified = time();
    }

    /**
     * PHP overloading magic to make the $dialogue->course syntax work by redirecting
     * it to the corresponding $dialogue->magic_get_course() method if there is one, and
     * throwing an exception if not. Taken from pagelib.php
     *
     * @param string $name property name
     * @return mixed
     */
    public function __get($name) {
        $getmethod = 'magic_get_' . $name;
        if (method_exists($this, $getmethod)) {
            return $this->$getmethod();
        } else {
            throw new \coding_exception('Unknown property: ' . $name);
        }
    }

    /**
     * Returns true/false if current user is the author
     * of this message;
     *
     * @global type $USER
     * @return boolean
     */
    public function is_author() {
        global $USER;
        return ($USER->id == $this->_authorid);
    }

    public function is_participant() {
        global $USER;

        $participants = $this->conversation->participants;
        return in_array($USER->id, array_keys($participants));
    }

    public function delete() {
        global $DB, $USER;

        $context = $this->dialogue->context;
        $fs = get_file_storage();
        // Hasn't been saved yet.
        if (is_null($this->_messageid)) {
            return true;
        }
        // Permission to delete conversation.
        $candelete = ((has_capability('mod/dialoguegrade:delete', $context) and $USER->id == $this->_authorid) or
            has_capability('mod/dialoguegrade:deleteany', $context));

        if (!$candelete) {
            throw new \moodle_exception('nopermissiontodelete', 'dialoguegrade');
        }
        // Delete message and attachment files for message.
        $fs->delete_area_files($context->id, false, false, $this->_messageid);

        // Delete message.
        $DB->delete_records('dialoguegrade_messages', array('id' => $this->_messageid));

        return true;
    }

    protected function magic_get_author() {
        $dialogue = $this->dialogue;
        return dialoguegrade_get_user_details($dialogue, $this->_authorid);
    }

    protected function magic_get_attachments() {
        $fs = get_file_storage();
        $contextid = $this->dialogue->context->id;
        if ($this->_attachments) {
            return $fs->get_area_files($contextid, 'mod_dialoguegrade', 'attachment', $this->messageid, "timemodified", false);
        }
        return array();
    }

    protected function magic_get_messageid() {
        return $this->_messageid;
    }

    protected function magic_get_note() {
        return $this->_note;
    }

    protected function magic_get_conversation() {
        if (is_null($this->_conversation)) {
            throw new \coding_exception('Parent conversation is not set');
        }
        return $this->_conversation;
    }

    protected function magic_get_dialogue() {
        if (is_null($this->_dialogue)) {
            throw new \coding_exception('Parent dialogue is not set');
        }
        return $this->_dialogue;
    }

    protected function magic_get_body() {
        return $this->_body;
    }

    protected function magic_get_bodydraftid() {
        return $this->_bodydraftid;
    }

    protected function magic_get_bodyformat() {
        return $this->_bodyformat;
    }

    protected function magic_get_bodyhtml() {
        $contextid = $this->dialogue->context->id;
        $ret = file_rewrite_pluginfile_urls($this->_body, 'pluginfile.php',
                                            $contextid, 'mod_dialoguegrade', 'message', $this->_messageid);
        return format_text($ret, $this->bodyformat);
    }

    protected function magic_get_state() {
        return $this->_state;
    }

    protected function magic_get_timemodified() {
        return $this->_timemodified;
    }

    public function set_flag($flag, $user = null) {
        global $DB, $USER;

        if (is_null($this->_messageid)) {
            throw new \coding_exception('message must be saved before a user flag can be set');
        }

        if (is_null($user)) {
            $user = $USER;
        }

        $dialogueid = $this->dialogue->dialogueid;
        $conversationid = $this->conversation->conversationid;

        $params = array('messageid' => $this->_messageid, 'userid' => $user->id, 'flag' => $flag);

        if (!$DB->record_exists('dialoguegrade_flags', $params)) {

            $messageflag = new \stdClass();
            $messageflag->dialogueid = $dialogueid;
            $messageflag->conversationid = $conversationid;
            $messageflag->messageid = $this->_messageid;
            $messageflag->userid = $user->id;
            $messageflag->flag = $flag;
            $messageflag->timemodified = time();

            $DB->insert_record('dialoguegrade_flags', $messageflag);
        }

        return true;
    }

    public function mark_read($user = null) {
        // Only mark read if in a open or closed state.
        return $this->set_flag(dialogue::FLAG_READ, $user);
    }

    public function mark_sent($user = null) {
        return $this->set_flag(dialogue::FLAG_SENT, $user);
    }

    public function set_body($body, $format, $itemid = null, $grade = null) {
        $this->_body = $body;
        $this->_bodyformat = $format;

        if ($format == FORMAT_HTML and isset($itemid)) {
            $this->_bodydraftid = $itemid;
            $this->_body = file_rewrite_urls_to_pluginfile($this->_body, $this->_bodydraftid);
            $this->_note = $grade;
        }
    }

    public function set_attachmentsdraftid($attachmentsdraftitemid) {
        $fileareainfo = file_get_draft_area_info($attachmentsdraftitemid);
        if ($fileareainfo['filecount']) {
            $this->_attachmentsdraftid = $attachmentsdraftitemid;
        }
    }

    public function set_author($authorid) {
        if (is_object($authorid)) {
            $authorid = $authorid->id;
        }
        $this->_authorid = $authorid;
    }

    public function set_state($state) {
        $this->_state = $state;
    }

    public function save() {
        global $DB, $USER;

        $admin = get_admin(); // Possible cronjob.
        if ($USER->id != $admin->id and $USER->id != $this->_authorid) {
            throw new \moodle_exception("This doesn't belong to you!");
        }

        $context = $this->dialogue->context; // Needed for filelib functions.
        $dialogueid = $this->dialogue->dialogueid;
        $conversationid = $this->conversation->conversationid;

        $record = new \stdClass();
        $record->id = $this->_messageid;
        $record->dialogueid = $dialogueid;
        $record->conversationid = $conversationid;
        $record->conversationindex = $this->_conversationindex;
        $record->authorid = $this->_authorid;
        // Rewrite body now if has embedded files.
        if (dialoguegrade_contains_draft_files($this->_bodydraftid)) {
            $record->body = file_rewrite_urls_to_pluginfile($this->_body, $this->_bodydraftid);
        } else {
            $record->body = $this->_body;
        }
        $record->bodyformat = $this->_bodyformat;
        // Mark atttachments now if has them.
        if (dialoguegrade_contains_draft_files($this->_attachmentsdraftid)) {
            $record->attachments = 1;
        } else {
            $record->attachments = 0;
        }
        $record->state = $this->_state;

        // Ajout de l'eventuel note.
        if (isset($this->_note) && $this->_note != null) {
            $record->grading = $this->_note;
        }
        $record->timecreated = $this->_timecreated;
        $record->timemodified = $this->_timemodified;

        if (is_null($this->_messageid)) {
            // Create new record.
            $this->_messageid = $DB->insert_record('dialoguegrade_messages', $record);
        } else {
            $record->timemodified = time();
            // Update existing record.
            $DB->update_record('dialoguegrade_messages', $record);
        }
        // Deal with embedded files.
        if ($this->_bodydraftid) {
            file_save_draft_area_files($this->_bodydraftid, $context->id, 'mod_dialoguegrade', 'message', $this->_messageid);
        }
        // Deal with attached files.
        if ($this->_attachmentsdraftid) {
            file_save_draft_area_files($this->_attachmentsdraftid, $context->id, 'mod_dialoguegrade',
                                       'attachment', $this->_messageid);
        }

        return true;
    }

    public function send() {
        global $DB;

        // Add author to participants and save.
        $this->conversation->add_participant($this->_authorid);
        $this->conversation->save_participants();

        // Update state to open.
        $this->_state = dialogue::STATE_OPEN;
        $DB->set_field('dialoguegrade_messages', 'state', $this->_state, array('id' => $this->_messageid));

        // Enregistrer la note dans le carnet de note.
        if (isset($this->_note) && $this->_note != null) {
            $targetid = -1; // Rechercher le user cible.
            $participants = $this->conversation->participants;
            foreach ($participants as $participant) {
                if ($participant->id != $this->_authorid) {
                    $targetid = $participant->id;
                    dialoguegrade_update_grades($this->conversation, $targetid);
                }
            }
        }

        // Setup information for messageapi object.
        $cm = $this->dialogue->cm;
        $conversationid = $this->conversation->conversationid;
        $course = $this->dialogue->course;
        $context = $this->dialogue->context;
        $userfrom = $DB->get_record('user', array('id' => $this->_authorid), '*', MUST_EXIST);
        $subject = format_string($this->conversation->subject, true, array('context' => $context));

        $a = new \stdClass();
        $a->userfrom = fullname($userfrom);
        $a->subject = $subject;
        $url = new \moodle_url('/mod/dialoguegrade/view.php', array('id' => $cm->id));
        $a->url = $url->out(false);
        $a->coursename = $course->shortname;

        $posthtml = get_string('messageapibasicmessage', 'dialoguegrade', $a);
        $posttext = html_to_text($posthtml);
        $smallmessage = get_string('messageapismallmessage', 'dialoguegrade', fullname($userfrom));

        $contexturlparams = array('id' => $cm->id, 'conversationid' => $conversationid);
        $contexturl = new \moodle_url('/mod/dialoguegrade/conversation.php', $contexturlparams);
        $contexturl->set_anchor('m' . $this->_messageid);

        // Flags and messaging.
        $participants = $this->conversation->participants;
        foreach ($participants as $participant) {
            if ($participant->id == $this->_authorid) {
                // So unread flag count displays properly for author, they wrote it, they should of read it.
                $this->set_flag(dialogue::FLAG_READ, $this->author);
                continue;
            }
            // Give participant a sent flag.
            $this->set_flag(dialogue::FLAG_SENT, $participant);

            $userto = $DB->get_record('user', array('id' => $participant->id), '*', MUST_EXIST);

            $eventdata = new \core\message\message();
            $eventdata->courseid = $course->id;
            $eventdata->component = 'mod_dialoguegrade';
            $eventdata->name = 'post';
            $eventdata->userfrom = $userfrom;
            $eventdata->userto = $userto;
            $eventdata->subject = $subject;
            $eventdata->fullmessage = $posttext;
            $eventdata->fullmessageformat = FORMAT_HTML;
            $eventdata->fullmessagehtml = $posthtml;
            $eventdata->smallmessage = $smallmessage;
            $eventdata->notification = 1;
            $eventdata->contexturl = $contexturl->out(false);
            $eventdata->contexturlname = $subject;

            message_send($eventdata);
        }

        return true;
    }

    /**
     * Message is marked as trash so can be deleted at a later time.
     *
     * @global stdClass $DB
     * @throws moodle_exception
     */
    public function trash() {
        global $DB;

        // Can only only trash drafts.
        if ($this->state != dialogue::STATE_DRAFT) {
            throw new \moodle_exception('onlydraftscanbetrashed', 'dialoguegrade');
        }

        // Update state to trashed.
        $this->_state = dialogue::STATE_TRASHED;
        $DB->set_field('dialoguegrade_messages', 'state', $this->_state, array('id' => $this->_messageid));
    }
}
