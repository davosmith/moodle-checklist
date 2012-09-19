<?php

// This file is part of the Checklist plugin for Moodle - http://moodle.org/
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

// This file replaces:
//   * STATEMENTS section in db/install.xml
//   * lib.php/modulename_install() post installation hook
//   * partially defaults.php

function xmldb_checklist_install() {
    global $DB;

/// Install logging support
/*    update_log_display_entry('checklist', 'view', 'checklist', 'name');
    update_log_display_entry('checklist', 'edit', 'checklist', 'name');
    update_log_display_entry('checklist', 'update checks', 'checklist', 'name');
    update_log_display_entry('checklist', 'complete', 'checklist', 'name');
    update_log_display_entry('checklist', 'report', 'checklist', 'name');*/

}
