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
 * Custom completion - used in Moodle 3.11 and above (ignored by earlier versions).
 *
 * @package   mod_checklist
 * @copyright 2021 Davo Smith, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_checklist\completion;

use checklist_class;

/**
 * Class custom_completion
 * @package mod_checklist
 */
class custom_completion extends \core_completion\activity_custom_completion {
    /**
     * Get the completion state for the given rule
     * @param string $rule
     * @return int
     */
    public function get_state(string $rule): int {
        global $DB;

        $this->validate_rule($rule);

        $userid = $this->userid;
        $forumid = $this->cm->instance;

        if (!$checklist = $DB->get_record('checklist', ['id' => $this->cm->instance])) {
            throw new \moodle_exception('Unable to find checklist with id ' . $forumid);
        }

        if ($rule === 'completionpercent') {
            [$ticked, $total] = checklist_class::get_user_progress($checklist->id, $userid);
            if ($checklist->completionpercenttype === 'items') {
                // Completionpercent is the actual number of items that need checking-off.
                $status = $checklist->completionpercent <= $ticked;
            } else {
                // Completionpercent is the percentage of items that need checking-off.
                $status = $total ? ($checklist->completionpercent <= ($ticked * 100 / $total)) : false;
            }
        }

        return $status ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
    }

    /**
     * Get a list of defined rules.
     * @return string[]
     */
    public static function get_defined_custom_rules(): array {
        return [
            'completionpercent',
        ];
    }

    /**
     * Get the descriptions for the rules.
     * @return array
     */
    public function get_custom_rule_descriptions(): array {
        [$amount, $type] = $this->cm->customdata['customcompletionrules']['completionpercent'] ?? [0, 'percent'];

        if ($type !== 'percent') {
            $type = 'items';
        }
        return [
            'completionpercent' => get_string('completiondetail:'.$type, 'mod_checklist', $amount),
        ];
    }

    /**
     * Get the sort order for displaying the rules.
     * @return string[]
     */
    public function get_sort_order(): array {
        return [
            'completionusegrade',
            'completionpercent',
        ];
    }
}
