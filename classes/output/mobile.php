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

namespace mod_checklist\output;

/**
 * Functions to support mobile app
 *
 * @package   mod_checklist
 * @copyright 2023 Davo Smith
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mobile {

    /**
     * Returns the checklist template and JS for the mobile app.
     *
     * @param  array $args Arguments from tool_mobile_get_content WS
     * @return array HTML, javascript and otherdata
     */
    public static function mobile_course_view($args) {
        global $OUTPUT, $CFG, $USER;
        require_once($CFG->dirroot.'/mod/checklist/locallib.php');

        $args = (object)$args;
        $cm = get_coursemodule_from_id('checklist', $args->cmid);

        require_login($args->courseid, false, $cm, true, true);
        $context = \context_module::instance($cm->id);
        if (!has_capability('mod/checklist:preview', $context)) {
            require_capability('mod/checklist:updateown', $context);
        }

        $checklist = new \checklist_class($cm->id, $USER->id, null, $cm);
        $data = $checklist->get_items_for_template();

        return [
            'templates' => [
                [
                    'id' => 'main',
                    'html' => $OUTPUT->render_from_template('mod_checklist/mobile_view_checklist', $data),
                ],
            ],
            'javascript' => file_get_contents($CFG->dirroot.'/mod/checklist/mobileapp/mobile.js'),
            'otherdata' => [],
            'files' => [],
        ];
    }

}
