<?php

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');

$id = optional_param('id', 0, PARAM_INT); // course_module ID, or
$checklist  = optional_param('checklist', 0, PARAM_INT);  // checklist instance ID

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

$strchecklists = get_string('modulenameplural', 'checklist');
$strchecklist  = get_string('modulename', 'checklist');

$navlinks = array();
$navlinks[] = array('name' => $strchecklists, 'link' => "index.php?id=$course->id", 'type' => 'activity');
$navlinks[] = array('name' => format_string($checklist->name), 'link' => '', 'type' => 'activityinstance');

$navigation = build_navigation($navlinks);

print_header_simple(format_string($checklist->name), '', $navigation, '', '', true,
              update_module_button($cm->id, $course->id, $strchecklist), navmenu($course, $cm));

if (!has_capability('mod/checklist:edit', $context)) {
    redirect($CFG->wwwroot.'/mod/checklist/view.php?checklist='.$context->id);
}

add_to_log($course->id, "checklist", "edit", "edit.php?id=$cm->id", $checklist->id, $cm->id);

$items = checklist_get_items($checklist->id);

$currenttab = 'edit';
include('tabs.php');

print_heading(format_string($checklist->name));

print_r($items);

print_footer($course);

?>