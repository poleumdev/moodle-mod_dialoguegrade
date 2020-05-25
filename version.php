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
 * Code fragment to define the module version etc.
 * This fragment is called by /admin/index.php
 *
 * @package mod-dialoguegrade
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

$plugin->version   = 2020032601;
$plugin->requires  = 2016111500;        // See http://docs.moodle.org/dev/Moodle_Versions
$plugin->component = 'mod_dialoguegrade';    // Full name of the plugin (used for diagnostics)
$plugin->release   = '1.1.0 (clone of dialogue)';// Ajout reset cours.
$plugin->maturity  = MATURITY_STABLE;   // This version's maturity level
$plugin->dependencies = array();