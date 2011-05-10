<?php

// This file is part of the Checklist module for Moodle
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

$id = optional_param('id', 0, PARAM_INT); // course_module ID, or
$checklist  = optional_param('checklist', 0, PARAM_INT);  // checklist instance ID
$items = optional_param('items', false, PARAM_INT);

if ($id) {
    if (! $cm = get_coursemodule_from_id('checklist', $id)) {
        error('Course Module ID was incorrect');
    }

    if (! $course = get_record('course', 'id', $cm->course)) {
        error('Course is misconfigured');
    }

    if (! $checklist = get_record('checklist', 'id', $cm->instance)) {
        error('Course module is incorrect');
    }

} else if ($checklist) {
    if (! $checklist = get_record('checklist', 'id', $checklist)) {
        error('Course module is incorrect');
    }
    if (! $course = get_record('course', 'id', $checklist->course)) {
        error('Course is misconfigured');
    }
    if (! $cm = get_coursemodule_from_instance('checklist', $checklist->id, $course->id)) {
        error('Course Module ID was incorrect');
    }

} else {
    error('You must specify a course_module ID or an instance ID');
}

require_login($course, true, $cm);

$context = get_context_instance(CONTEXT_MODULE, $cm->id);
$userid = $USER->id;
if (!has_capability('mod/checklist:updateown', $context)) {
    echo 'Error: you do not have permission to update this checklist';
    die();
}
if (!confirm_sesskey()) {
    echo 'Error: invalid sesskey';
    die();
}
if (!$items || !is_array($items)) {
    echo 'Error: invalid (or missing) items list';
    die();
}

if (!empty($items)) {
    $chk = new checklist_class($cm->id, $userid, $checklist, $cm, $course);
    $chk->ajaxupdatechecks($items);
}

echo 'OK';