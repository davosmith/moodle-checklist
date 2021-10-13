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
 * The mod_checklist student comment created event.
 *
 * @package    mod_checklist
 * @copyright  2021 Kristian Ringer <kristian.ringer@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_checklist\event;

defined('MOODLE_INTERNAL') || die();

/**
 * The mod_checklist student comment created class.
 *
 * @package    mod_checklist
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class student_comment_created extends \core\event\base {

    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'checklist_comment_student';
    }

    /**
     * Returns localised general event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('student_comment_created', 'mod_checklist');
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        $commenttext = $this->other['commenttext'];
        return "The user with id $this->userid has created a comment in the checklist with course module id" .
            " $this->contextinstanceid with text '$commenttext'";
    }

    /**
     * Get URL related to the action
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/checklist/report.php', array('id' => $this->contextinstanceid, 'studentid' => $this->userid));
    }

    /**
     * Get the mapping to use when restoring logs from backup
     * @return string[]
     */
    public static function get_objectid_mapping() {
        return ['db' => 'checklist', 'restore' => 'checklist'];
    }
}

