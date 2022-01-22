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
 * Update the grades, due to the checklist definition changing.
 *
 * @package   mod_checklist
 * @copyright 2021 Davo Smith, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_checklist\task;

/**
 * Update grades as an adhoc task.
 */
class update_grades extends \core\task\adhoc_task {
    /**
     * Update the grades for the specified checklist.
     */
    public function execute(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/mod/checklist/lib.php');
        $customdata = $this->get_custom_data();
        $checklist = $DB->get_record('checklist', ['id' => $customdata->checklistid]);
        if ($checklist) {
            checklist_update_grades($checklist);
        }
    }

    /**
     * Request a grade update for the specified checklist.
     * @param int $checklistid
     */
    public static function queue(int $checklistid): void {
        // Create an adhoc task to update the grades.
        $task = new self();
        $task->set_custom_data((object)['checklistid' => $checklistid]);
        // Queue the task, as long as there isn't already an update queued for this checklist.
        \core\task\manager::queue_adhoc_task($task, true);
    }
}
