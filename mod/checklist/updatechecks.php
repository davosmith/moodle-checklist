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
 * Used by AJAX calls to update the checklist marks
 *
 * @author  David Smith <moodle@davosmith.co.uk>
 * @package mod/checklist
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');
require_once(dirname(__FILE__).'/locallib.php');

global $DB;

$id = optional_param('id', 0, PARAM_INT); // course_module ID, or
$checklistid  = optional_param('checklist', 0, PARAM_INT);  // checklist instance ID
if ($CFG->version < 2011120100) {
    $items = optional_param('items', false, PARAM_INT);
} else {
    $items = optional_param_array('items', false, PARAM_INT);
}

$url = new moodle_url('/mod/checklist/view.php');
if ($id) {
    if (!$cm = get_coursemodule_from_id('checklist', $id)){
        print_error('error_cmid', 'checklist'); // 'Course Module ID was incorrect'
    }
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $checklist = $DB->get_record('checklist', array('id' => $cm->instance), '*', MUST_EXIST);
    $url->param('id', $id);
} else if ($checklistid) {
    $checklist = $DB->get_record('checklist', array('id' => $checklistid), '*', MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $checklist->course), '*', MUST_EXIST);
    if (!$cm = get_coursemodule_from_instance('checklist', $checklist->id, $course->id)) {
        print_error('error_cmid', 'checklist'); // 'Course Module ID was incorrect'
    }
    $url->param('checklist', $checklistid);
} else {
    print_error('error_specif_id', 'checklist'); // 'You must specify a course_module ID or an instance ID'
}

$PAGE->set_url($url);
require_login($course, true, $cm);

if ($CFG->version < 2011120100) {
    $context = get_context_instance(CONTEXT_MODULE, $cm->id);
} else {
    $context = context_module::instance($cm->id);
}
$userid = $USER->id;
if (!has_capability('mod/checklist:updateown', $context)) {
    echo get_string('error_update', 'checklist'); // 'Error: you do not have permission to update this checklist';
    die();
}
if (!confirm_sesskey()) {
    echo get_string('error_sesskey', 'checklist'); // 'Error: invalid sesskey';
    die();
}
if (!$items || !is_array($items)) {
    echo get_string('error_itemlist', 'checklist'); // 'Error: invalid (or missing) items list';
    die();
}
if (!empty($items)) {
    $chk = new checklist_class($cm->id, $userid, $checklist, $cm, $course);
    $chk->ajaxupdatechecks($items);
}

echo get_string('OK', 'checklist');      // 'OK'
