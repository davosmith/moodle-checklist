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
 * External checklist API
 *
 * @copyright  2021 Kristian Ringer <kristian.ringer@gmail.com>
 * @package    mod_checklist
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_checklist\external;

defined('MOODLE_INTERNAL') || die;

global $CFG;
require_once("$CFG->libdir/externallib.php");
require_once("$CFG->dirroot/mod/checklist/locallib.php");

use external_api;
use mod_checklist\local\checklist_comment_student;
use external_function_parameters;
use external_single_structure;
use external_value;
use context_module;
use mod_checklist\local\checklist_item;
use moodle_exception;

/**
 * Checklist functions
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class update_student_comment extends external_api {

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters(
            [
                'comment' => new external_single_structure(
                    [
                        'commenttext' => new external_value(PARAM_TEXT, 'content of the comment'),
                        'checklistitemid' => new external_value(PARAM_INT, 'id of checklist item'),
                        'cmid' => new external_value(PARAM_INT, 'cmid of checklist module')
                    ]
                ),
            ]
        );
    }

    /**
     * Update or create a student comment for a checklist item.
     * @param array $params array with comment data.
     * @return string welcome message
     */
    public static function execute(array $params): string {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), array('comment' => $params));
        $commentdata = $params['comment'];
        $cm = get_coursemodule_from_id('checklist', $commentdata['cmid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        require_capability('mod/checklist:updateown', $context);
        // Validate that this checklist item exists and belongs to the checklist with the given 'cmid'.
        $checklistitem = checklist_item::fetch(['checklist' => $cm->instance, 'id' => $commentdata['checklistitemid']]);
        if (!$checklistitem) {
            throw new moodle_exception(get_string('errorchecklistitemnotvalid', 'mod_checklist'));
        }

        $eventdata = [];
        $eventdata['context'] = $context;
        $eventdata['objectid'] = $commentdata['checklistitemid'];
        $eventdata['other'] = ['commenttext' => $commentdata['commenttext']];
        $existingcomment = checklist_comment_student::get_record([
            'itemid' => $commentdata['checklistitemid'],
            'usermodified' => $USER->id
        ]);
        if ($existingcomment) {
            $event = \mod_checklist\event\student_comment_updated::create($eventdata);
            $event->trigger();
        } else {
            $event = \mod_checklist\event\student_comment_created::create($eventdata);
            $event->trigger();
            $existingcomment = null;
        }

        return checklist_comment_student::update_or_create_student_comment($commentdata['checklistitemid'],
            $commentdata['commenttext'], $existingcomment);
    }

    /** Returns description of method result value.
     * @return external_value
     */
    public static function execute_returns() {
        return new external_value(PARAM_BOOL, 'True if the comment was successfully updated.');
    }
}
