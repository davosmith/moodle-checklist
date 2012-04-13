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
 * Add a new document to a description
 * @author  David Smith <moodle@davosmith.co.uk>
 * @author  Jean Fruitet <jean.fruitet@univ-nantes.fr>
 * @package mod/checklist
 */


require_once(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');
require_once(dirname(dirname(__FILE__)).'/lib.php');
require_once(dirname(dirname(__FILE__)).'/locallib.php');

require_once(dirname(__FILE__).'/mahara.php');   // mahara class
require_once(dirname(dirname(dirname(dirname(__FILE__)))).'/repository/lib.php'); // Repository API

global $DB;

$id = optional_param('id', 0, PARAM_INT); // course_module ID, or
$checklistid  = optional_param('checklist', 0, PARAM_INT);  // checklist instance ID
$itemid  = optional_param('itemid', 0, PARAM_INT);  // Item ID
$userid  = optional_param('userid', 0, PARAM_INT);  // userID
$descriptionid  = optional_param('descriptionid', 0, PARAM_INT);  // description ID
$cancel     = optional_param('cancel', 0, PARAM_BOOL);

// POUR MAHARA
$hostid = optional_param('hostid', 0, PARAM_INT);  // le nouveau document recupérer sur Mahara
$view = optional_param('view', 0, PARAM_INT);  // le nouveau document recupérer sur Mahara

$url = new moodle_url('/mod/checklist/mahara/upload_mahara.php');
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


// Description ?
if (!$descriptionid && $itemid && $userid) {
    $description = $DB->get_record('checklist_description', array("itemid"=>$itemid, "userid"=>$userid));
    if (!empty($description)){
        $descriptionid=$description->id;
    }
}

$PAGE->set_url($url);
$returnurl=new moodle_url('/mod/checklist/view.php?checklist='.$checklist->id);

require_login($course, true, $cm);

$context = get_context_instance(CONTEXT_MODULE, $cm->id);

if (empty($userid)){
    if (has_capability('mod/checklist:updateown', $context)) {
        $userid = $USER->id;
    }
}


/// If it's hidden then it's don't show anything.  :)
/// Some capability checks.
if (empty($cm->visible)
    && (
        !has_capability('moodle/course:viewhiddenactivities', $context)
            &&
        !has_capability('mod/checklist:updateown', $context)
        )

    ) {
    print_error('activityiscurrentlyhidden','error',$returnurl);
}


if ($cancel) {
    if (!empty($SESSION->returnpage)) {
            $return = $SESSION->returnpage;
            unset($SESSION->returnpage);
            redirect($return);
    } else {
            redirect($returnurl);
    }
}


if ($chk = new checklist_mahara_class($cm->id, 0, $checklist, $cm, $course)) {
    $chk->add_mahara_document($itemid, $userid, $descriptionid, $hostid, $view);
}

