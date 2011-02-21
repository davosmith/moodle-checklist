<?php

/**
 * This page prints a list of all student's results
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
$studentid = optional_param('studentid', false, PARAM_INT);

$url = new moodle_url('/mod/checklist/report.php');
if ($id) {
    if (! $cm = get_coursemodule_from_id('checklist', $id)) {
        error('Course Module ID was incorrect');
    }

    if (! $course = $DB->get_record('course', array('id' => $cm->course) )) {
        error('Course is misconfigured');
    }

    if (! $checklist = $DB->get_record('checklist', array('id' => $cm->instance) )) {
        error('Course module is incorrect');
    }

    $url->param('id', $id);

} else if ($checklistid) {
    if (! $checklist = $DB->get_record('checklist', array('id' => $checklistid) )) {
        error('Course module is incorrect');
    }
    if (! $course = $DB->get_record('course', array('id' => $checklist->course) )) {
        error('Course is misconfigured');
    }
    if (! $cm = get_coursemodule_from_instance('checklist', $checklist->id, $course->id)) {
        error('Course Module ID was incorrect');
    }

    $url->param('checklist', $checklistid);

} else {
    error('You must specify a course_module ID or an instance ID');
}

$url->param('studentid', $studentid);
$PAGE->set_url($url);
require_login($course, true, $cm);

$chk = new checklist_class($cm->id, $studentid, $checklist, $cm, $course);

$chk->report();

?>