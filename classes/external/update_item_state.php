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

namespace mod_checklist\external;

defined('MOODLE_INTERNAL') || die;

global $CFG;
require_once("$CFG->libdir/externallib.php");

/**
 * External function to update the state of an item in a checklist
 *
 * @copyright  2023 Davo Smith
 * @package    mod_checklist
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class update_item_state extends \external_api {
    /**
     * Describes the parameters for update_item_state.
     *
     * @return \external_function_parameters
     */
    public static function execute_parameters(): \external_function_parameters {
        return new \external_function_parameters(
            [
                'cmid' => new \external_value(PARAM_INT),
                'itemid' => new \external_value(PARAM_INT),
                'state' => new \external_value(PARAM_BOOL),
            ]
        );
    }

    /**
     * Updates item state.
     *
     * @param int $cmid Checklist course module ID.
     * @param int $itemid Item ID.
     * @param bool $state State to set.
     * @return \stdClass
     */
    public static function execute(int $cmid, int $itemid, bool $state): \stdClass {
        global $DB, $PAGE, $USER;
        $cm = get_coursemodule_from_id('checklist', $cmid, 0, false, MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $checklist = $DB->get_record('checklist', ['id' => $cm->instance], '*', MUST_EXIST);
        require_login($course, true, $cm);
        require_capability('mod/checklist:updateown', $PAGE->context);

        $chk = new \checklist_class($cm->id, $USER->id, $checklist, $cm, $course);
        $chk->ajaxupdatechecks([$itemid => $state]);

        return (object)[
            'status' => true,
            'warnings' => [],
        ];
    }

    /**
     * Describes the update_item_state return value.
     *
     * @return \external_single_structure
     */
    public static function execute_returns(): \external_single_structure {
        return new \external_single_structure([
                                                  'status' => new \external_value(PARAM_BOOL),
                                                  'warnings' => new \external_warnings(),
                                              ]);
    }
}
