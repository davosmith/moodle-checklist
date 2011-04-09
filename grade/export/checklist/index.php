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
$context = get_context_instance(CONTEXT_COURSE, $course->id);
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

echo "<br /><div style='width: 800px; margin: 0px auto;'><form action='{$CFG->wwwroot}/grade/export/checklist/export.php' method='post'>";

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

echo '<input type="hidden" name="id" value="'.$course->id.'" />';

echo '<input type="submit" name="export" value="'.get_string('export','gradeexport_checklist').'" />';

echo '</form></div>';

echo $OUTPUT->footer();

