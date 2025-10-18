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
 * Behat step definitions
 *
 * @package   mod_checklist
 * @copyright 2015 Davo Smith
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

use Behat\Gherkin\Node\TableNode;

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

/**
 * Steps definitions related with the checklist module.
 *
 * @package    mod_checklist
 * @copyright  2015 Davo Smith
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_mod_checklist extends behat_base {
    /**
     * View the calendar for a specific course + date
     *
     * @When /^I visit the calendar for course "(?P<course_string>[^"]*)" showing date "(?P<date_string>[^"]*)"$/
     * @param string $coursename
     * @param string $datestring
     */
    public function i_visit_the_calendar_for_course_showing_date($coursename, $datestring) {
        global $DB;

        if (!$courseid = $DB->get_field('course', 'id', ['shortname' => $coursename])) {
            $courseid = $DB->get_field('course', 'id', ['fullname' => $coursename], MUST_EXIST);
        }
        $timestamp = strtotime($datestring);

        $url = '/calendar/view.php?view=month&course=' . $courseid . '&time=' . $timestamp;
        $this->getSession()->visit($this->locate_path($url));
    }

    /**
     * Add the given items to the specified checklist
     *
     * @Given /^the following items exist in checklist "(?P<checklist_string>[^"]*)":$/
     * @param string $checklistname
     * @param TableNode $table
     */
    public function the_following_items_exist_in_checklist($checklistname, TableNode $table) {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/checklist/locallib.php');

        $required = [
            'text',
        ];
        $optional = [
            'required' => CHECKLIST_OPTIONAL_NO,
            'duetime' => 0,
        ];

        // Valid settings for field 'required'.
        $requiredmap = [
            'required' => CHECKLIST_OPTIONAL_NO,
            'optional' => CHECKLIST_OPTIONAL_YES,
            'heading' => CHECKLIST_OPTIONAL_HEADING,
        ];

        $data = $table->getHash();
        $firstrow = reset($data);

        // Check required fields are present.
        foreach ($required as $reqname) {
            if (!isset($firstrow[$reqname])) {
                throw new Exception('Checklist items require the field ' . $reqname . ' to be set');
            }
        }

        // Add each of the items to the checklist.
        $checklist = $DB->get_record('checklist', ['name' => $checklistname], '*', MUST_EXIST);
        [$course, $cm] = get_course_and_cm_from_instance($checklist, 'checklist');
        $chk = new checklist_class($cm->id, 0, $checklist, $cm, $course);

        foreach ($data as $row) {
            $newitem = $optional;
            foreach ($row as $fieldname => $value) {
                switch ($fieldname) {
                    case 'text':
                        if (!$value) {
                            throw new Exception('Checklist item text cannot be empty');
                        }
                        $newitem['displaytext'] = $value;
                        break;
                    case 'required':
                        if (!isset($requiredmap[$value])) {
                            throw new Exception('Invalid \'required\' value in checklist item: \'' . $value . '\'');
                        }
                        $newitem['required'] = $requiredmap[$value];
                        break;
                    case 'duetime':
                        if ($value) {
                            $timestamp = strtotime($value);
                            $dateinfo = usergetdate($timestamp);
                            $newitem['duetime'] = [
                                'year' => $dateinfo['year'],
                                'month' => $dateinfo['mon'],
                                'day' => $dateinfo['mday'],
                            ];
                            $chk->set_editing_dates(true);
                        }
                        break;
                    default:
                        throw new Exception('Unknown field \'' . $fieldname . '\' in checklist item ');
                }
            }
            $chk->additem($newitem['displaytext'], 0, 0, false, $newitem['duetime'], 0, $newitem['required']);
        }
    }

    /**
     * Check off the given items in the checklist for the given user
     *
     * @Given /^the following items are checked off in checklist "(?P<checklist_string>[^"]*)" for user "(?P<user_string>[^"]*)":$/
     * @param string $checklistname
     * @param string $username
     * @param TableNode $table
     */
    public function the_following_items_are_checked_off_in_checklist_for_user($checklistname, $username, TableNode $table) {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/checklist/locallib.php');

        $required = [
            'itemtext',
        ];
        $optional = [
            'studentmark' => 'yes',
            'teachermark' => 'none',
            'teachername' => 'admin',
        ];

        // Valid settings for field 'studentmark'.
        $studentmarkmap = [
            'yes' => 1,
            'no' => 0,
        ];
        // Valid settings for field 'teachermark'.
        $teachermarkmap = [
            'none' => CHECKLIST_TEACHERMARK_UNDECIDED,
            'yes' => CHECKLIST_TEACHERMARK_YES,
            'no' => CHECKLIST_TEACHERMARK_NO,
        ];

        $data = $table->getHash();
        $firstrow = reset($data);

        // Check required fields are present.
        foreach ($required as $reqname) {
            if (!isset($firstrow[$reqname])) {
                throw new Exception('Checklist item updates require the field ' . $reqname . ' to be set');
            }
        }

        // Get the checklist data.
        $checklist = $DB->get_record('checklist', ['name' => $checklistname], '*', MUST_EXIST);
        [$course, $cm] = get_course_and_cm_from_instance($checklist, 'checklist');
        $userid = $DB->get_field('user', 'id', ['username' => $username], MUST_EXIST);
        $chk = new checklist_class($cm->id, $userid, $checklist, $cm, $course);

        $updatestudent = ($checklist->teacheredit != CHECKLIST_MARKING_TEACHER) && isset($firstrow['studentmark']);
        $updateteacher = ($checklist->teacheredit != CHECKLIST_MARKING_STUDENT) && isset($firstrow['teachermark']);
        if (!$updateteacher && !$updatestudent) {
            throw new Exception('Checklist update must specify a teachermark (for teacher/both checklists) or a studentmark ' .
                                '(for student/both checklists)');
        }

        // Gather together all the updated marks.
        $studentupdates = [];
        $teacherupdates = [];
        foreach ($data as $row) {
            $update = $optional;
            foreach ($row as $fieldname => $value) {
                if (!in_array($fieldname, $required) && !isset($optional[$fieldname])) {
                    throw new Exception('Unknown checklist item update field \'', $fieldname . '\'');
                }
                $update[$fieldname] = $value;
            }
            if (!array_key_exists($update['studentmark'], $studentmarkmap)) {
                throw new Exception('Invalid studentmark value \'' . $update['studentmark'] . '\' in checklist update');
            }
            if (!array_key_exists($update['teachermark'], $teachermarkmap)) {
                throw new Exception('Invalid teachermark value \'' . $update['teachermark'] . '\' in checklist update');
            }

            $itemid = $chk->get_itemid_by_name($update['itemtext']);

            if ($updatestudent) {
                $studentupdates[$itemid] = $studentmarkmap[$update['studentmark']];
            }
            if ($updateteacher) {
                if (!isset($teacherupdates[$update['teachername']])) {
                    $teacherupdates[$update['teachername']] = [];
                }
                $teacherupdates[$update['teachername']][$itemid] = $teachermarkmap[$update['teachermark']];
            }
        }

        // Process the updated marks.
        if ($updatestudent) {
            $chk->ajaxupdatechecks($studentupdates);
        }
        if ($updateteacher) {
            foreach ($teacherupdates as $teachername => $checkmarks) {
                $teacherid = $DB->get_field('user', 'id', ['username' => $teachername], MUST_EXIST);
                $chk->update_teachermarks($checkmarks, $teacherid);
            }
        }
    }

    /**
     * Once I'm only supporting Moodle 4.0+, I can use the standard step: And I enable "selfcompletion" "block" plugin
     * @Given /^I enable selfcompletion block plugin for use by mod_checklist$/
     */
    public function i_enable_selfcompletion_block_plugin_for_use_by_mod_checklist() {
        global $DB;
        $DB->set_field('block', 'visible', 1, ['name' => 'selfcompletion']);
    }

    /**
     * Set the given field to the view URL for the given activity
     * @Given /^I set the field "([^"]*)" to the view URL for activity "([^"]*)"$/
     * @param string $fieldname the field to set
     * @param string $activityidnumber the idnumber of the activity to link to
     */
    public function i_set_the_field_to_the_view_u_r_l_for_activity(string $fieldname, string $activityidnumber) {
        global $DB;
        $cmrec = $DB->get_record('course_modules', ['idnumber' => $activityidnumber], 'id, course', MUST_EXIST);
        $modinfo = get_fast_modinfo($cmrec->course);
        $cm = $modinfo->get_cm($cmrec->id);
        $url = $cm->get_url()->out(false);
        $this->execute('behat_forms::i_set_the_field_to', [$fieldname, $url]);
    }

    /**
     * Workaround the different UI for selecting 'automatic completion tracking' in Moodle 4.3.
     * @Given /^I enable automatic completion tracking for the checklist$/
     */
    public function i_enable_automatic_completion_tracking_for_the_checklist() {
        global $CFG;
        // This changed in Moodle 4.3, this is a workaround (which I should drop if I ever stop supporting
        // Moodle 4.2 and below).
        if ($CFG->branch < 403) {
            $this->execute('behat_forms::i_set_the_field_to', ['Completion tracking',
                                                               'Show activity as complete when conditions are met']);
        } else {
            $this->execute('behat_forms::i_set_the_field_to', ['Add requirements', 1]);
        }
    }

    /**
     * Temporary hack, until I drop compatibility for versions before Moodle 4.4, that renames the sections
     * from "Topic N" to "Section N"
     * @Given /^I adjust the section names in course "([^"]*)" to be compatible with Moodle 4.4$/
     * @param string $coursename
     */
    public function i_adjust_the_section_names_in_course_to_be_compatible_with_moodle(string $coursename): void {
        global $DB;
        $courseid = $DB->get_field('course', 'id', ['fullname' => $coursename], MUST_EXIST);
        $sections = $DB->get_records_select(
            'course_sections',
            "course = :course AND section > 0",
            ['course' => $courseid],
            'section',
            'id, section, name'
        );
        foreach ($sections as $section) {
            $section->name = "Section $section->section";
            $DB->update_record('course_sections', $section);
        }
        rebuild_course_cache($courseid);
    }
}
