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

define('CLI_SCRIPT', true);

require(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');
require_once($CFG->libdir.'/clilib.php');
require_once($CFG->libdir.'/filelib.php');
require_once($CFG->dirroot.'/mod/dialoguegrade/lib.php');
require_once($CFG->dirroot.'/mod/dialoguegrade/locallib.php');

// we may need a lot of memory here
@set_time_limit(0);
raise_memory_limit(MEMORY_HUGE);

// now get cli options
list($options, $unrecognized) = cli_get_params(
    array(
        'non-interactive'   => false,
        'help'              => false
    ),
    array(
        'h' => 'help'
    )
);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help']) {
    $help =
"Dialogue module: clean up orphaned messages

Please note you must execute this script with the same uid as apache!

Options:
--non-interactive     No interactive questions or confirmations
-h, --help            Print out this help

Example:
\$sudo -u apache /usr/bin/php mod/dialoguegrade/cli/cleanuporphans.php
";

    echo $help;
    die;
}

$interactive = empty($options['non-interactive']);

if ($interactive) {
    $prompt = "Dialogue module: clean up orphaned messages? type y (means yes) or n (means no)";
    $input = cli_input($prompt, '', array('n', 'y'));
    if ($input == 'n') {
        mtrace('Bye bye');
        exit;
    }
}

// Start output log
$starttime = microtime();
mtrace("Server Time: ".date('r')."\n");

// Do work!
$sql =  "SELECT dm.*
         FROM {dialoguegrade_messages} dm
         WHERE NOT EXISTS (SELECT dc.id 
                          FROM {dialoguegrade_conversations} dc 
                          WHERE dc.id = dm.conversationid)
         ORDER BY dm.conversationid, dm.conversationindex";       

$rs = $DB->get_recordset_sql($sql, array());
if ($rs->valid()) {
    // Get file storage
    $fs = get_file_storage();

    foreach ($rs as $record) {
        $cm = get_coursemodule_from_instance('dialoguegrade', $record->dialogueid);
        if (! $cm) {
            mtrace('Course module does not exist! Weird!');
            continue;
        }
        $context = context_module::instance($cm->id, MUST_EXIST);
        // delete message and attachment files for message
        $fs->delete_area_files($context->id, false, false, $record->id);
        // delete message
        $DB->delete_records('dialoguegrade_messages', array('id' => $record->id));

        mtrace("Message#{$record->id} has been cleaned out.");
    }
}
$rs->close();

$pre_dbqueries = null;
$pre_dbqueries = $DB->perf_get_queries();
$pre_time      = microtime(1);
if (isset($pre_dbqueries)) {
    mtrace("... used " . ($DB->perf_get_queries() - $pre_dbqueries) . " dbqueries");
    mtrace("... used " . (microtime(1) - $pre_time) . " seconds");
}

gc_collect_cycles();
mtrace('Completed at ' . date('H:i:s') . '. Memory used ' . display_size(memory_get_usage()) . '.');
$difftime = microtime_diff($starttime, microtime());
mtrace("Execution took ".$difftime." seconds");