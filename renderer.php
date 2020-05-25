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
 * A custom renderer class that extends the plugin_renderer_base and is used by
 * the dialogue module.
 *
 * @package   mod_dialoguegrade
 * @copyright 2013 Troy Williams
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_dialoguegrade_renderer extends plugin_renderer_base {

    /**
     * Render conversation, just the conversation
     *
     * @global type $PAGE
     * @global type $OUTPUT
     * @global type $USER
     * @param dialogue_conversation $conversation
     * @return string
     */
    public function render_conversation(mod_dialoguegrade\conversation $conversation) {
        global $PAGE, $OUTPUT, $USER;

        $context = $conversation->dialogue->context; // fetch context from parent dialogue
        $cm      = $conversation->dialogue->cm; // fetch course module from parent dialogue

        $today    = strtotime("today");
        $yearago  = strtotime("-1 year");

        $html = '';

        $html .= html_writer::start_div('conversation-heading');
        $html .= html_writer::tag('h3', $conversation->subject, array('class' => 'heading'));

        $html .= html_writer::end_div(); // close header

        $html .= html_writer::start_div('conversation');
        $messageid = 'm' . $conversation->messageid;

        $html .= html_writer::tag('a', '', array('id' => $messageid));
        $avatar = $OUTPUT->user_picture($conversation->author, array('size' => true, 'class' => 'userpicture img-rounded'));

        $html .= html_writer::div($avatar, 'conversation-object pull-left');

        $html .= html_writer::start_div('conversation-body');

        $datestrings = (object) dialoguegrade_get_humanfriendly_dates($conversation->timemodified);
        $datestrings->fullname = fullname($conversation->author); //sneaky
        if ($conversation->timemodified >= $today) {
            $openedbyheader = get_string('openedbytoday', 'dialoguegrade', $datestrings);
        } else if ($conversation->timemodified >= $yearago) {
            $openedbyheader = get_string('openedbyshortyear', 'dialoguegrade', $datestrings);
        } else {
            $openedbyheader = get_string('openedbyfullyear', 'dialoguegrade', $datestrings);
        }

        $html .= html_writer::start_div('conversation-header');
        $html .= html_writer::tag('span', $openedbyheader, array('class' => 'conversation-openedby pull-left'));

        $html .= html_writer::start_tag('ul', array('class' => "message-actions pull-right"));
        $candelete = ((has_capability('mod/dialoguegrade:delete', $context) and $USER->id == $conversation->author->id) or
                       has_capability('mod/dialoguegrade:deleteany', $context));

        if ($candelete) {
            $html .= html_writer::start_tag('li');
            $trashicon = html_writer::tag('i', '', array('class' => "fa fa-trash-o"));
            $deleteurl = new moodle_url('/mod/dialoguegrade/conversation.php');
            $deleteurl->param('id', $cm->id);
            $deleteurl->param('conversationid', $conversation->conversationid);
            $deleteurl->param('action', 'delete');
            $html .= html_writer::link($deleteurl,  get_string('deleteconversation', 'dialoguegrade') . $trashicon);
            $html .= html_writer::end_tag('li');
        }

        $html .= html_writer::end_tag('ul');
        $html .= html_writer::empty_tag('br');
        $html .= html_writer::end_div();

        $html .= html_writer::empty_tag('hr');
        $html .= $conversation->bodyhtml;
        $html .= $this->render_attachments($conversation->attachments);
        $html .= html_writer::end_div();
        
        // This should only display on open and closed conversations @todo - tidy + css
        $participants = $conversation->participants;
        if ($participants) {
            $html .= html_writer::start_div('participants');
            $html .= html_writer::tag('strong', count($participants));
            $html .= '&nbsp;' . get_string('participants', 'dialoguegrade');
            foreach ($participants as $participant) {
                $picture = $OUTPUT->user_picture($participant, array('class' => 'userpicture img-rounded', 'size' => 20));
                $html .= html_writer::tag('span', $picture . '&nbsp;' . fullname($participant), array('class' => 'participant'));
            }
            $html .= html_writer::end_div();
        }
        $html .= html_writer::end_div(); // end of main conversation
        $html .= html_writer::empty_tag('hr');

        return $html;
    }

    /**
     * Render a list of conversations in a dialogue for a particular user.
     *
     * @global global $OUTPUT
     * @global global $PAGE
     * @param mod_dialogue_conversations $conversations
     * @return string
     */
    public function conversation_listing(\mod_dialoguegrade\conversations $conversations) {
        global $OUTPUT, $PAGE;

        $dialogue = $conversations->dialogue;
        $cm       = $conversations->dialogue->cm;

        $list = array();

        $html = '';

        $rowsmatched = $conversations->rows_matched();
        if ($rowsmatched) {
            $list = $conversations->records();
        }

        if (empty($list)) {
            $html .= '<br/><br/>';
            $html .= $OUTPUT->notification(get_string('noconversationsfound', 'dialoguegrade'), 'notifyproblem');
        } else {
            $today    = strtotime("today");
            $yearago  = strtotime("-1 year");

            $rowsreturned = count($list);

            $html .= html_writer::start_div('listing-meta');
            $html .= html_writer::tag('h6', get_string('displaying', 'dialoguegrade'));
            $a         = new stdClass();
            $a->start  = ($conversations->page) ? $conversations->page * $conversations->limit : 1;
            $a->end    = $conversations->page * $conversations->limit + $rowsreturned;
            $a->total  = $rowsmatched;
            $html .= html_writer::tag('h6', get_string('listpaginationheader', 'dialoguegrade', $a), array('class'=>'pull-right'));
            $html .= html_writer::end_div();

            $html .= html_writer::start_tag('table', array('class'=>'conversation-list table table-hover table-condensed'));
            $html .= html_writer::start_tag('tbody');
            foreach ($list as $record) {
                $datattributes = array('data-redirect' => 'conversation',
                                       'data-action'   => 'view',
                                       'data-conversationid' => $record->conversationid);

                $html .= html_writer::start_tag('tr', $datattributes);

                $statelabel = '';
                if ($record->state == \mod_dialoguegrade\dialogue::STATE_CLOSED) {
                    $statelabel = html_writer::tag('span', get_string('closed', 'dialoguegrade'),
                                                   array('class'=>'state-indicator state-closed'));
                }
                $html .= html_writer::tag('td', $statelabel);

                $invite = get_string('getto', 'dialoguegrade');
                if (isset($record->unread)) {
                    $badge = '';
                    $unreadcount = $record->unread;
                    if ($unreadcount > 0) {
                    	$invite = get_string('reply', 'dialoguegrade');
                        $badgeclass = 'badge label-info';
                        $badge = html_writer::span($unreadcount, $badgeclass, array('title'=> get_string('numberunread', 'dialoguegrade', $unreadcount)));
                    }
                    $html .= html_writer::tag('td', $badge . '&nbsp;<div class="btn btn-small">'. $invite .'</div>');
                }

                if (isset($record->userid)) {
                    $displayuser = dialoguegrade_get_user_details($dialogue, $record->userid);
                    $avatar = $OUTPUT->user_picture($displayuser, array('class'=> 'userpicture img-rounded', 'size' => 48));
                    $html .= html_writer::tag('td', $avatar);
                    $html .= html_writer::tag('td', fullname($displayuser));
                }

                if (isset($record->subject) and isset($record->body)) {
                    $subject = empty($record->subject) ? get_string('nosubject', 'dialoguegrade') : $record->subject;
                    $summaryline = dialoguegrade_generate_summary_line($subject, $record->body, $record->bodyformat);
                    $html .= html_writer::start_tag('td');
                    $html .= html_writer::start_div();
                    $html .= $summaryline;

                    $participants = dialoguegrade_get_conversation_participants($dialogue, $record->conversationid);
                    $html .= html_writer::start_div();
                    foreach($participants as $participantid) {
                        $participant = dialoguegrade_get_user_details($dialogue, $participantid);
                        $picture = $OUTPUT->user_picture($participant, array('class'=>'userpicture img-rounded', 'size'=>16));
                        $html .= html_writer::tag('span', $picture.' '.fullname($participant),
                                                    array('class' => 'participant'));
                    }
                    $html .= html_writer::start_div();

                    $html .= html_writer::end_div();
                    $html .= html_writer::end_tag('td');
                }

                if (isset($record->timemodified)) {
                    $datestrings = (object) dialoguegrade_get_humanfriendly_dates($record->timemodified);
                    if ($record->timemodified >= $today) {
                        $datetime = $datestrings->timepast;
                    } else if ($record->timemodified >= $yearago) {
                        $datetime = get_string('dateshortyear', 'dialoguegrade', $datestrings);
                    } else {
                        $datetime = get_string('datefullyear', 'dialoguegrade', $datestrings);
                    }
                    $html .= html_writer::tag('td', $datetime, array('title' => userdate($record->timemodified)));
                }

                $viewurlparams = array('id' => $cm->id, 'conversationid' => $record->conversationid, 'action' => 'view');
                $viewlink = html_writer::link(new moodle_url('conversation.php', $viewurlparams),
                                              get_string('view'));

                $html .= html_writer::tag('td', $viewlink, array('class'=>'nonjs-control'));

                $html .= html_writer::end_tag('tr');
            }

            $html .= html_writer::end_tag('tbody');
            $html .= html_writer::end_tag('table');

            $pagination = new paging_bar($rowsmatched, $conversations->page, $conversations->limit, $PAGE->url);

            $html .= $OUTPUT->render($pagination);
        }

        return $html;
    }

    /**
     * Render a reply related to conversation.
     *
     * @param dialogue_reply $reply
     * @return string
     */
    public function render_reply(\mod_dialoguegrade\reply $reply) {
        global $OUTPUT, $USER;

        $context        = $reply->dialogue->context; // fetch context from parent dialogue
        $cm             = $reply->dialogue->cm; // fetch course module from parent dialogue
        $conversation   = $reply->conversation; // fetch parent conversation

        $today    = strtotime("today");
        $yearago  = strtotime("-1 year");

        $html = '';

        $html .= html_writer::start_div('conversation');
        $messageid = 'm' . $reply->messageid;
        $html .= html_writer::tag('a', '', array('id' => $messageid));

        $avatar = $OUTPUT->user_picture($reply->author, array('size' => true, 'class' => 'userpicture img-rounded'));
        
        $valeurNote = $reply->note;
        $affNote="";
        if (isset($valeurNote)) {
        	$noteMax = $reply->dialogue->activityrecord->grade;
        	$affNote = "<div class='grade'>Note<br><center><small>" . $valeurNote . " / " . $noteMax. " </small></center></div>";
        }
        $html .= html_writer::div($avatar .$affNote, 'conversation-object pull-left');
        

        $html .= html_writer::start_div('conversation-body');

        $datestrings = (object) dialoguegrade_get_humanfriendly_dates($reply->timemodified);
        $datestrings->fullname = fullname($reply->author); //sneaky
        if ($reply->timemodified >= $today) {
            $repliedbyheader = get_string('repliedbytoday', 'dialoguegrade', $datestrings);
        } else if ($reply->timemodified >= $yearago) {
            $repliedbyheader = get_string('repliedbyshortyear', 'dialoguegrade', $datestrings);
        } else {
            $repliedbyheader = get_string('repliedbyfullyear', 'dialoguegrade', $datestrings);
        }
        $html .= html_writer::start_div('reply-header');
        $html .= html_writer::tag('span', $repliedbyheader, array('class' => 'reply-openedby pull-left'));
        $html .= html_writer::empty_tag('br');
        $html .= html_writer::end_div();
        $html .= html_writer::empty_tag('hr');
        $html .= $reply->bodyhtml;
        $html .= $this->render_attachments($reply->attachments);
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();

        return $html;
    }

    /**
     * Render attachments associated with a message - conversation or reply.
     *
     * @global type $OUTPUT
     * @param array $attachments
     * @return string
     */
    public function render_attachments(array $attachments) {
        global $OUTPUT;

        $html = '';

        if ($attachments) {

            $numattachments = count($attachments);
            $attachmentheader = ($numattachments > 1) ? get_string('numberattachments', 'dialoguegrade', $numattachments) :
                                                        get_string('attachment', 'dialoguegrade');

            $html .= html_writer::start_div('attachments');
            $html .= html_writer::tag('h5', $attachmentheader);
            foreach ($attachments as $file) {
                $contextid = $file->get_contextid();
                $itemid = $file->get_itemid();
                $filename = $file->get_filename();
                $filesize = $file->get_filesize();
                $mimetype = $file->get_mimetype();

                $viewurl = new moodle_url('/pluginfile.php/' . $contextid . '/mod_dialoguegrade/attachment/' . $itemid . '/' . $filename);
                $previewurl = clone($viewurl);
                $previewurl->param('preview', 'thumb');
                $downloadurl = clone($viewurl);
                $downloadurl->param('forcedownload', 'true');

                if ($file->is_valid_image()) {
                    $html .= html_writer::start_tag('table');
                    $html .= html_writer::start_tag('tbody');
                    $html .= html_writer::start_tag('tr');
                    $html .= html_writer::start_tag('td');
                    $html .= html_writer::link($viewurl, html_writer::empty_tag('img', array('src' => $previewurl->out(), 'class' => 'thumbnail', 'alt' => $mimetype)));
                    $html .= html_writer::end_tag('td');
                    $html .= html_writer::start_tag('td');
                    $html .= html_writer::tag('b', $filename);
                    $html .= html_writer::empty_tag('br');
                    $html .= html_writer::tag('span', display_size($filesize), array('class' => 'meta-filesize'));
                    $html .= html_writer::link($viewurl, html_writer::tag('span', get_string('view')));
                    $html .= html_writer::link($downloadurl, html_writer::tag('span', get_string('download')));
                    $html .= html_writer::end_tag('td');
                    $html .= html_writer::end_tag('tr');
                    $html .= html_writer::end_tag('tbody');
                    $html .= html_writer::end_tag('table');
                } else {
                    $html .= html_writer::start_tag('table');
                    $html .= html_writer::start_tag('tbody');
                    $html .= html_writer::start_tag('tr');
                    $html .= html_writer::start_tag('td');
                    $html .= html_writer::link($downloadurl, html_writer::empty_tag('img', array('src' => $OUTPUT->pix_url(file_mimetype_icon($mimetype)), 'class' => 'icon', 'alt' => $mimetype)));
                    $html .= html_writer::end_tag('td');
                    $html .= html_writer::start_tag('td');
                    $html .= html_writer::tag('i', $filename);
                    $html .= html_writer::empty_tag('br');
                    $html .= html_writer::tag('span', display_size($filesize), array('class' => 'meta-filesize'));
                    $html .= html_writer::link($downloadurl, html_writer::tag('span', get_string('download')));
                    $html .= html_writer::end_tag('td');
                    $html .= html_writer::end_tag('tr');
                    $html .= html_writer::end_tag('tbody');
                    $html .= html_writer::end_tag('table');
                }
                $html .= html_writer::empty_tag('br'); // break up attachments spacing
            }
            $html .= html_writer::end_div();
        }
        return $html;
    }


    public function state_button_group() {
        global $PAGE;

        $stateurl = clone($PAGE->url);
        $html = '';
        $openlink = '';
        $closedlink = '';

        // get state from url param
        $state = $stateurl->get_param('state');
        return $html;
    }

    /**
     * Builds and returns HTML needed to render the sort by drop down for conversation
     * lists.
     *
     * @global stdClass $PAGE
     * @global stdClass $OUTPUT
     * @param array $options
     * @param string $sort
     * @param string $direction
     * @return string $html
     * @throws moodle_exception
     */
    public function list_sortby($options, $sort, $direction) {
        global $PAGE, $OUTPUT;

        $html = '';
        $nonjsoptions = array();

        if (!in_array($sort, array_keys($options))) {
            throw new moodle_exception("Not a sort option");
        }


        $pageurl = clone($PAGE->url);
        $PAGE->url->param('page', 0); // reset pagination

        $html .= html_writer::start_div('dropdown-group pull-right'); //
        $html .= html_writer::start_div('js-control btn-group pull-right');

        $html .= html_writer::start_tag('button', array('data-toggle' => 'dropdown',
                                                        'class' =>'btn btn-small dropdown-toggle'));

        $html .= get_string('sortedby', 'dialoguegrade', get_string($sort, 'dialoguegrade'));
        $html .= html_writer::end_tag('button');
        $html .= html_writer::start_tag('ul', array('class' => 'dropdown-menu'));
        foreach ($options as $option => $settings) {
            $string = get_string($option, 'dialoguegrade');
            $nonjsoptions[$option] = $string;
            if ($settings['directional'] == false) {
                $url = clone($PAGE->url);
                $url->param('sort', $option);
                $html .= html_writer::start_tag('li');
                $html .= html_writer::link($url, $string);
                $html .= html_writer::end_tag('li');
                continue;
            }
            if ($option == $sort) {
                $sortdirection = ($direction == 'desc') ? 'asc' : 'desc';
            } else {
                $sortdirection = \core_text::strtolower($settings['default']);
            }
            $url = clone($PAGE->url);
            $url->param('sort', $option);
            $url->param('direction', $sortdirection);
            // font awesome icon
            $faclass = "fa fa-sort-{$settings['type']}-{$sortdirection} pull-right";
            $faicon = html_writer::tag('i', '', array('class' => $faclass));
            $html .= html_writer::start_tag('li');
            $html .= html_writer::link($url, $faicon . $string);
            $html .= html_writer::end_tag('li');
        }
        $html .= html_writer::end_tag('ul');
        $html .= html_writer::end_div(); // end of js-control

        // Important: non javascript control must be after javascript control else layout borked in chrome.
        $select = new single_select($pageurl, 'sort', $nonjsoptions, $sort, null, 'orderbyform');
        $select->method = 'post';
        $nonjscontrol = $OUTPUT->render($select);
        $html .= html_writer::div($nonjscontrol, 'nonjs-control');

        $html .= html_writer::end_div(); // end of container
        return $html;

    }

    public function sort_by_dropdown($options) {
        global $PAGE, $OUTPUT;
        $html = '';

        $strings = array();
        foreach ($options as $option) {
            $strings[$option] = get_string($option, 'dialoguegrade');
        }

        $pageurl = clone($PAGE->url);

        $PAGE->url->param('page', 0); // reset pagination

        $sort = $PAGE->url->get_param('sort');
        if (!in_array($sort, $options)) {
            throw new coding_exception('$PAGE sort param is not in options');
        }

        $html .= html_writer::start_div('dropdown-group pull-right'); //
        $html .= html_writer::start_div('js-control btn-group pull-right');

        $html .= html_writer::start_tag('button', array('data-toggle' => 'dropdown',
                                                        'class' =>'btn btn-small dropdown-toggle'));

        $html .= get_string('sortedby', 'dialoguegrade', get_string($sort, 'dialoguegrade'));
        $html .= html_writer::tag('tag', null, array('class' => 'caret'));
        $html .= html_writer::end_tag('button');
        $html .= html_writer::start_tag('ul', array('class' => 'dropdown-menu'));

        foreach ($options as $option) {
            $url = clone($PAGE->url);
            $url->param('sort', $option);
            $html .= html_writer::start_tag('li');
            $html .= html_writer::link($url, ucfirst(get_string($option, 'dialoguegrade')));
            $html .= html_writer::end_tag('li');
        }

        $html .= html_writer::end_tag('ul');
        $html .= html_writer::end_div(); // end of js-control

        // Important: non javascript control must be after javascript control else layout borked in chrome.
        $select = new single_select($pageurl, 'sort', $strings, $sort, null, 'orderbyform');
        $select->method = 'post';
        $nonjscontrol = $OUTPUT->render($select);
        $html .= html_writer::div($nonjscontrol, 'nonjs-control');

        $html .= html_writer::end_div(); // end of container
        return $html;

    }

    public function tab_navigation(\mod_dialoguegrade\dialogue $dialogue) {
        global $PAGE, $DB, $USER;

        $config  = $dialogue->config;
        $context = $dialogue->context;
        $cm      = $dialogue->cm;

        $html = '';
        $currentpage = basename($PAGE->url->out_omit_querystring(), '.php');

        $html .= html_writer::start_tag('ul', array('class'=>'nav nav-tabs'));
        // link main conversation listing
        $active = ($currentpage == 'view') ? array('class'=>'active') : array();
        $html .= html_writer::start_tag('li', $active);
        $viewurl = new moodle_url('view.php', array('id'=>$cm->id));
        $html .= html_writer::link($viewurl, get_string('viewconversations', 'dialoguegrade'));
        $html .= html_writer::end_tag('li');
        // experimental: link conversation by role listing
        if (!empty($config->viewconversationsbyrole) and has_capability('mod/dialoguegrade:viewbyrole', $context)) {
            $active = ($currentpage == 'viewconversationsbyrole') ? array('class'=>'active') : array();
            $html .= html_writer::start_tag('li', $active);
            $viewurl = new moodle_url('viewconversationsbyrole.php', array('id'=>$cm->id));
            $html .= html_writer::link($viewurl, get_string('viewconversationsbyrole', 'dialoguegrade'));
            $html .= html_writer::end_tag('li');
        }
        // link to users draft listing
        $active = ($currentpage == 'drafts') ? array('class'=>'active') : array();
        $html .= html_writer::start_tag('li', $active);
        $draftsurl = new moodle_url('drafts.php', array('id'=>$cm->id));
        $html .= html_writer::link($draftsurl, get_string('drafts', 'dialoguegrade'));
        $html .= html_writer::end_tag('li');
 
        // open discussion button
        if (has_capability('mod/dialoguegrade:open', $context)) {
        	$btnCreate = true;
        	if (!has_capability('mod/dialoguegrade:multipleconversation', $context)) {
        		 $sql = "select conversationid 
				           from {dialoguegrade_participants}
				          where dialogueid = ?
				            and userid = ?";
		         $btnCreate = ! ($DB->record_exists_sql($sql, array ($cm->instance, $USER->id)));
        	}
        	if ($btnCreate) {
                $createurl = new moodle_url('conversation.php', array('id'=>$cm->id, 'action'=>'create'));
                $html .= html_writer::link($createurl, get_string('create', 'dialoguegrade'), array('class'=>'btn-create pull-right'));//array('class'=>'btn btn-primary pull-right')
        	}
        }
        $html .= html_writer::end_tag('ul');

        return $html;
    }

} // end of renderer class