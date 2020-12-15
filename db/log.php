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

$logs = array(
    // Dialogue instance log actions.
    array('module' => 'dialoguegrade', 'action' => 'add'         , 'mtable' => 'dialoguegrade', 'field' => 'name'),
    array('module' => 'dialoguegrade', 'action' => 'update'      , 'mtable' => 'dialoguegrade', 'field' => 'name'),
    array('module' => 'dialoguegrade', 'action' => 'view'        , 'mtable' => 'dialoguegrade', 'field' => 'name'),
    array('module' => 'dialoguegrade', 'action' => 'view by role', 'mtable' => 'dialoguegrade', 'field' => 'name'),
    array('module' => 'dialoguegrade', 'action' => 'view all'    , 'mtable' => 'dialoguegrade', 'field' => 'name'),
    // Conversation log actions.
    array('module' => 'dialoguegrade', 'action' => 'close conversation',
          'mtable' => 'dialoguegrade_conversations', 'field' => 'subject'),
    array('module' => 'dialoguegrade', 'action' => 'delete conversation',
          'mtable' => 'dialoguegrade_conversations', 'field' => 'subject'),
    array('module' => 'dialoguegrade', 'action' => 'open conversation',
          'mtable' => 'dialoguegrade_conversations', 'field' => 'subject'),
    array('module' => 'dialoguegrade', 'action' => 'view conversation',
          'mtable' => 'dialoguegrade_conversations', 'field' => 'subject'),
    // Reply log actions.
    array('module' => 'dialoguegrade', 'action' => 'reply', 'mtable' => 'dialoguegrade_conversations', 'field' => 'subject'),
);
