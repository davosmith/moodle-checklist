<?php

///////////////////////////////////////////////////////////////////////////
//                                                                       //
// NOTICE OF COPYRIGHT                                                   //
//                                                                       //
// Moodle - Modular Object-Oriented Dynamic Learning Environment         //
//          http://moodle.com                                            //
//                                                                       //
// Copyright (C) 1999 onwards  Martin Dougiamas  http://moodle.com       //
//                                                                       //
// This program is free software; you can redistribute it and/or modify  //
// it under the terms of the GNU General Public License as published by  //
// the Free Software Foundation; either version 2 of the License, or     //
// (at your option) any later version.                                   //
//                                                                       //
// This program is distributed in the hope that it will be useful,       //
// but WITHOUT ANY WARRANTY; without even the implied warranty of        //
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the         //
// GNU General Public License for more details:                          //
//                                                                       //
//          http://www.gnu.org/copyleft/gpl.html                         //
//                                                                       //
///////////////////////////////////////////////////////////////////////////


require_once(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');
require_once($CFG->dirroot.'/grade/lib.php');

$courseid = required_param('id', PARAM_INT);                   // course id

$PAGE->set_url(new moodle_url('/grade/export/checklist/index.php', array('id'=>$courseid)));
if (!$course = $DB->get_record('course', array('id' => $courseid))) {
    print_error('nocourseid');
}

require_login($course->id);
if ($CFG->version < 2011120100) {
    $context = get_context_instance(CONTEXT_COURSE, $course->id);
} else {
    $context = context_course::instance($course->id);
}
$PAGE->set_context($context);

require_capability('gradeexport/checklist:view', $context);
$viewall = has_capability('gradeexport/checklist:viewall', $context);
$viewdistrict = has_capability('gradeexport/checklist:viewdistrict', $context);
if (!$viewall && !$viewdistrict) {
    print_error('nopermission', 'gradeexport_checklist');
}

// Build navigation
$strgrades = get_string('grades');
$strchkgrades = get_string('pluginname', 'gradeexport_checklist');

print_grade_page_head($COURSE->id, 'export', 'checklist', $strchkgrades);

// Get list of appropriate checklists
$checklists = $DB->get_records('checklist', array('course' => $course->id));

if (empty($checklists)) {
    print_error('nochecklists','gradeexport_checklist');
}

// Get list of districts
if ($DB->get_record('user_info_field', array('shortname' => 'district'))) {
    if (!$viewall) {
        $sql = "SELECT ud.data AS district FROM {user_info_data} ud, {user_info_field} uf ";
        $sql .= "WHERE ud.fieldid = uf.id AND uf.shortname = 'district' AND ud.userid = ?";
        $district = $DB->get_record_sql($sql, array($USER->id));

        if ($district) {
            $districts = array($district->district);
        } else {
            $districts = array(get_string('nodistrict','gradeexport_checklist'));
        }

    } else {
        $sql = "SELECT DISTINCT ud.data AS district FROM {user_info_data} ud, {user_info_field} uf ";
        $sql .= "WHERE ud.fieldid = uf.id AND uf.shortname = 'district'";
        $districts = $DB->get_records_sql($sql, array());

        $districts = array_keys($districts);
    }
} else {
    $districts = false;
}

// Get list of groups
$groupsmenu = array();
$groupsmenu[0] = get_string('allparticipants'); // Always available - only exports groups user has access to
if ($course->groupmode == VISIBLEGROUPS or has_capability('moodle/site:accessallgroups', $context)) {
    $allowedgroups = groups_get_all_groups($course->id);
} else {
    $allowedgroups = groups_get_all_groups($course->id, $USER->id);
}

if ($allowedgroups) {
    foreach ($allowedgroups as $group) {
        $groupsmenu[$group->id] = format_string($group->name);
    }
}

echo "<br /><div class=\"checklist_export_options\"><form action='{$CFG->wwwroot}/grade/export/checklist/export.php' method='post'>";

echo '<label for="choosechecklist">'.get_string('choosechecklist','gradeexport_checklist').':</label> <select id="choosechecklist" name="choosechecklist">';
$selected = ' selected="selected" ';
foreach ($checklists as $checklist) {
    echo "<option $selected value='{$checklist->id}'>{$checklist->name}</option>";
    $selected = '';
}
echo '</select><br/>';

if ($districts) {
    echo '<label for="choosedistrict">'.get_string('choosedistrict','gradeexport_checklist').':</label> <select id="choosedistrict" name="choosedistrict">';
    if ($viewall) {
        echo '<option selected="selected" value="ALL">'.get_string('alldistrict','gradeexport_checklist').'</option>';
        $selected = '';
    } else {
        $selected = ' selected="selected" ';
    }
    foreach ($districts as $district) {
        echo "<option $selected value='{$district}'>{$district}</option>";
        $selected = '';
    }
    echo '</select><br/>';
}

if (count($groupsmenu) == 1) {
    $groupname = reset($groupsmenu);
    echo '<input type="hidden" name="group" value="'.key($groupsmenu).'" />';
} else {
    echo '<label for="group">'.get_string('group').':</label> <select id="group" name="group">';
    $selected = ' selected="selected" ';
    foreach ($groupsmenu as $groupid=>$groupname) {
        echo "<option $selected value='{$groupid}'>$groupname</option>";
        $selected = '';
    }
    echo '</select><br/>';
}

echo '<label for="exportoptional">'.get_string('exportoptional', 'gradeexport_checklist').':</label> <select id="exportoptional" name="exportoptional">';
echo '<option selected="selected" value="1">'.get_string('yes').'</option>';
echo '<option value="0">'.get_string('no').'</option>';
echo '</select><br/>';

echo '<label for="percentcol">'.get_string('percentcol', 'gradeexport_checklist').':</label> ';
echo '<input type="checkbox" name="percentcol" id="percentcol" checked="checked" /> ';
echo get_string('percentcol2', 'gradeexport_checklist').'<br/>';
echo '<label for="percentrow">'.get_string('percentrow', 'gradeexport_checklist').':</label> ';
echo '<input type="checkbox" name="percentrow" id="percentrow" /> ';
echo get_string('percentrow2', 'gradeexport_checklist').'<br/>';
echo '<label for="percentheadings">'.get_string('percentheadings', 'gradeexport_checklist').':</label> ';
echo '<input type="checkbox" name="percentheadings" id="percentheadings" /> ';
echo get_string('percentheadings2', 'gradeexport_checklist').'<br/><br/>';

echo '<input type="hidden" name="id" value="'.$course->id.'" />';

echo '<input type="submit" name="export" value="'.get_string('export','gradeexport_checklist').'" />';

echo '</form></div>';

echo $OUTPUT->footer();

