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

/**
 * This page prints a list of all student's results
 *
 * @copyright Davo Smith <moodle@davosmith.co.uk>
 * @package mod_checklist
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__.'/../../config.php');
global $CFG, $DB, $PAGE;
require_once($CFG->dirroot.'/mod/checklist/lib.php');
require_once($CFG->dirroot.'/mod/checklist/locallib.php');


$id = required_param('id', PARAM_INT); // Course_module ID.
$studentid = optional_param('studentid', false, PARAM_INT);

$url = new moodle_url('/mod/checklist/report.php', array('id' => $id));
if ($studentid) {
    $url->param('studentid', $studentid);
}

$cm = get_coursemodule_from_id('checklist', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$checklist = $DB->get_record('checklist', array('id' => $cm->instance), '*', MUST_EXIST);

$url->param('studentid', $studentid);
$PAGE->set_url($url);
require_login($course, true, $cm);

$chk = new checklist_class($cm->id, $studentid, $checklist, $cm, $course);
echo $chk->report();
