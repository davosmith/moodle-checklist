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
 * Task to update grades for all checklists on the site
 *
 * @package   mod_checklist
 * @copyright 2022 Davo Smith, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_checklist\task;

/**
 * Update all grades
 */
class update_all_grades extends \core\task\adhoc_task {
    /**
     * Update all grades for all users on all checklists on the site.
     * @return void
     */
    public function execute(): void {
        global $CFG;
        require_once($CFG->dirroot.'/mod/checklist/lib.php');
        checklist_update_all_grades();
    }
}
