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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/checklist/backup/moodle2/restore_checklist_stepslib.php'); // Because it exists (must)

/**
 * checklist restore task that provides all the settings and steps to perform one
 * complete restore of the activity
 */
class restore_checklist_activity_task extends restore_activity_task {

    /**
     * Define (add) particular settings this activity can have
     */
    protected function define_my_settings() {
        // No particular settings for this activity
    }

    /**
     * Define (add) particular steps this activity can have
     */
    protected function define_my_steps() {
        // Choice only has one structure step
        $this->add_step(new restore_checklist_activity_structure_step('checklist_structure', 'checklist.xml'));
    }

    /**
     * Define the contents in the activity that must be
     * processed by the link decoder
     */
    static public function define_decode_contents() {
        $contents = array();

        $contents[] = new restore_decode_content('checklist', array('intro'), 'checklist');

        return $contents;
    }

    /**
     * Define the decoding rules for links belonging
     * to the activity to be executed by the link decoder
     */
    static public function define_decode_rules() {
        $rules = array();

        // List of checklists in course
        $rules[] = new restore_decode_rule('CHECKLISTINDEX', '/mod/checklist/index.php?id=$1', 'course');
        // Checklist by cm->id and forum->id
        $rules[] = new restore_decode_rule('CHECKLISTVIEWBYID', '/mod/checklist/view.php?id=$1', 'course_module');
        $rules[] = new restore_decode_rule('CHECKLISTVIEWBYCHECKLIST', '/mod/checklist/view.php?checklist=$1', 'checklist');
        // Checklist report by cm->id and forum->id
        $rules[] = new restore_decode_rule('CHECKLISTREPORTBYID', '/mod/checklist/report.php?id=$1', 'course_module');
        $rules[] = new restore_decode_rule('CHECKLISTREPORTBYCHECKLIST', '/mod/checklist/report.php?checklist=$1', 'checklist');
        // Checklist edit by cm->id and forum->id
        $rules[] = new restore_decode_rule('CHECKLISTEDITBYID', '/mod/checklist/edit.php?id=$1', 'course_module');
        $rules[] = new restore_decode_rule('CHECKLISTEDITBYCHECKLIST', '/mod/checklist/edit.php?checklist=$1', 'checklist');

        return $rules;
    }
}
