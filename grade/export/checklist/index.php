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

if (!$course = get_record('course', 'id', $courseid)) {
    print_error('nocourseid');
}

require_login($course->id);
$context = get_context_instance(CONTEXT_COURSE, $course->id);

require_capability('gradeexport/checklist:view', $context);
$viewall = has_capability('gradeexport/checklist:viewall', $context);
$viewdistrict = has_capability('gradeexport/checklist:viewdistrict', $context);
if (!$viewall && !$viewdistrict) {
    error('You do not have permission to do this export');
}

// Build navigation
$strgrades = get_string('grades');
$strchkgrades = get_string('modulename', 'gradeexport_checklist');

$navigation = grade_build_nav(__FILE__, $strchkgrades, $course->id);

/// Print header
$release = explode(' ', $CFG->release);
$relver = explode('.', $release[0]);
if (intval($relver[0]) == 1 && intval($relver[1]) == 9 && intval($relver[2]) < 5) {
    print_header_simple($strgrades.':'.$strchkgrades, ':'.$strgrades, $navigation, '', '', true);
    print_grade_plugin_selector($courseid, 'export', 'checklist');
    print_heading($strchkgrades);
} else {
    print_grade_page_head($COURSE->id, 'export', 'checklist', $strchkgrades, false, null);
}

// Get list of appropriate checklists
$checklists = get_records('checklist', 'course', $course->id);

if (!$checklists) {
    print_error('nochecklists','gradeexport_checklist');
}

// Get list of districts
if (get_record('user_info_field', 'shortname', 'district')) {
    if (!$viewall) {
        $sql = "SELECT ud.data AS district FROM {$CFG->prefix}user_info_data ud, {$CFG->prefix}user_info_field uf ";
        $sql .= "WHERE ud.fieldid = uf.id AND uf.shortname = 'district' AND ud.userid = {$USER->id}";
        $district = get_record_sql($sql);

        if ($district) {
            $districts = array($district->district);
        } else {
            $districts = array(get_string('nodistrict','gradeexport_checklist'));
        }

    } else {
        $sql = "SELECT DISTINCT ud.data AS district FROM {$CFG->prefix}user_info_data ud, {$CFG->prefix}user_info_field uf ";
        $sql .= "WHERE ud.fieldid = uf.id AND uf.shortname = 'district'";
        $districts = get_records_sql($sql);

        $districts = array_keys($districts);
    }
} else {
    $districts = false;
}

// Get list of groups
$groupsmenu = array();
if ($course->groupmode == VISIBLEGROUPS or has_capability('moodle/site:accessallgroups', $context)) {
    $allowedgroups = groups_get_all_groups($course->id);
    $groupsmenu[0] = get_string('allparticipants');
} else {
    $allowedgroups = groups_get_all_groups($course->id, $USER->id);
}

if (!$allowedgroups) {
    $groupsmenu[0] = get_string('allparticipants');
} else {
    foreach ($allowedgroups as $group) {
        $groupsmenu[$group->id] = format_string($group->name);
    }
}


echo "<div style='width: 800px; margin: 0px auto;'><form action='{$CFG->wwwroot}/grade/export/checklist/export.php' method='post'>";

echo '<label for="choosechecklist">'.get_string('choosechecklist','gradeexport_checklist').': <select id="choosechecklist" name="choosechecklist">';
$selected = ' selected="selected" ';
foreach ($checklists as $checklist) {
    echo "<option $selected value='{$checklist->id}'>{$checklist->name}</option>";
    $selected = '';
}
echo '</select>&nbsp;';

if ($districts) {
    echo '<label for="choosedistrict">'.get_string('choosedistrict','gradeexport_checklist').': <select id="choosedistrict" name="choosedistrict">';
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
    echo '</select>&nbsp;';
}

if (count($groupsmenu) == 1) {
    $groupname = reset($groupsmenu);
    echo '<input type="hidden" name="group" value="'.key($groupname).'" />';
} else {
    echo '<label for="group">'.get_string('group').': <select id="group" name="group">';
    $selected = ' selected="selected" ';
    foreach ($groupsmenu as $groupid=>$groupname) {
        echo "<option $selected value='{$groupid}'>$groupname</option>";
        $selected = '';
    }
    echo '</select>&nbsp;';
}

echo '<input type="hidden" name="id" value="'.$course->id.'" />';

echo '<input type="submit" name="export" value="'.get_string('export','gradeexport_checklist').'" />';

echo '</form></div>';

print_footer($course);

