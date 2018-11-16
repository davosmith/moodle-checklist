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
 * Extra install steps
 *
 * @package   mod_checklist
 * @copyright 2018 Davo Smith, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_checklist_install() {
    global $CFG;

    // This version includes the extended privacy API only found in M3.4.6, M3.5.3 and M3.6+.
    if ($CFG->version > 2018051700 && $CFG->version < 2018051703) {
        // Main version.php takes care of Moodle below 3.4.6.
        die('You must upgrade to Moodle 3.5.3 (or above) before installing this version of mod_checklist');
    }
}
