<?php 

/**
 * This page lists all the instances of newmodule in a particular course
 *
 * @author  David Smith <moodle@davosmith.co.uk>
 * @package mod/checklist
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');
require_once(dirname(__FILE__).'/locallib.php');

$id = required_param('id', PARAM_INT);   // course

if (! $course = get_record('course', 'id', $id)) {
    error('Course ID is incorrect');
}

require_course_login($course);

add_to_log($course->id, 'checklist', 'view all', "index.php?id=$course->id", '');


/// Get all required stringsnewmodule

$strchecklists = get_string('modulenameplural', 'checklist');
$strchecklist  = get_string('modulename', 'checklist');


/// Print the header

$navlinks = array();
$navlinks[] = array('name' => $strchecklists, 'link' => '', 'type' => 'activity');
$navigation = build_navigation($navlinks);

print_header_simple($strchecklists, '', $navigation, '', '', true, '', navmenu($course));

/// Get all the appropriate data

if (! $checklists = get_all_instances_in_course('checklist', $course)) {
    notice('There are no instances of checklist', "../../course/view.php?id=$course->id");
    die;
}

/// Print the list of instances (your module will probably extend this)

$timenow  = time();
$strname  = get_string('name');
$strweek  = get_string('week');
$strtopic = get_string('topic');
$strprogress = get_string('progress','checklist');

if ($course->format == 'weeks') {
    $table->head  = array ($strweek, $strname);
    $table->align = array ('center', 'left');
} else if ($course->format == 'topics') {
    $table->head  = array ($strtopic, $strname);
    $table->align = array ('center', 'left', 'left', 'left');
} else {
    $table->head  = array ($strname);
    $table->align = array ('left', 'left', 'left');
}

$context = get_context_instance(CONTEXT_COURSE, $course->id);
$canupdateown = has_capability('mod/checklist:updateown', $context);
if ($canupdateown) {
    $table->head[] = $strprogress;
}

foreach ($checklists as $checklist) {
    if (!$checklist->visible) {
        //Show dimmed if the mod is hidden
        $link = '<a class="dimmed" href="view.php?id='.$checklist->coursemodule.'">'.format_string($checklist->name).'</a>';
    } else {
        //Show normal if the mod is visible
        $link = '<a href="view.php?id='.$checklist->coursemodule.'">'.format_string($checklist->name).'</a>';
    }

    
    if ($course->format == 'weeks' or $course->format == 'topics') {
        $row = array ($checklist->section, $link);
    } else {
        $row = array ($link);
    }

    if ($canupdateown) {
        $row[] = checklist_class::print_user_progressbar($checklist->id, $USER->id, '300px', true, true);
    }

    $table->data[] = $row;
}

print_heading($strchecklists);
print_table($table);

/// Finish the page

print_footer($course);

?>
