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

//UT

global $DB;

$id = optional_param('id', 0, PARAM_INT); // course_module ID, or
$checklist  = optional_param('checklist', 0, PARAM_INT);  // checklist instance ID
$studentid = optional_param('studentid', false, PARAM_INT);

if ($id) {
    // UT
    if (! $cm = get_coursemodule_from_id('checklist', $id)) {
        error('Course Module ID was incorrect');
    }

    if (! $course = $DB->get_record('course', array('id' => $cm->course) )) {
        error('Course is misconfigured');
    }

    if (! $checklist = $DB->get_record('checklist', array('id' => $cm->instance) )) {
        error('Course module is incorrect');
    }

} else if ($checklist) {
    //UT
    if (! $checklist = $DB->get_record('checklist', array('id' => $checklist) )) {
        error('Course module is incorrect');
    }
    if (! $course = $DB->get_record('course', array('id' => $checklist->course) )) {
        error('Course is misconfigured');
    }
    if (! $cm = get_coursemodule_from_instance('checklist', $checklist->id, $course->id)) {
        error('Course Module ID was incorrect');
    }

} else {
    error('You must specify a course_module ID or an instance ID');
}

require_login($course, true, $cm);

//UT
$chk = new checklist_class($cm->id, $studentid, $checklist, $cm, $course);

$chk->report();

?>