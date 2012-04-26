<?php

// This file is part of Moodle - http://moodle.org/
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
 *
 * @package   mod-checklist
 * @author JFRUITET <jean.fruitet@univ-nantes.fr>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');
require_once(dirname(__FILE__).'/locallib.php');
require_once(dirname(__FILE__).'file_api.php');
require_once(dirname(dirname(dirname(__FILE__))).'/repository/lib.php');

global $DB;

$id = optional_param('id', 0, PARAM_INT); // course_module ID, or
$checklistid  = optional_param('checklist', 0, PARAM_INT);  // checklist instance ID
$itemid  = optional_param('itemid', 0, PARAM_INT);  // checklist instance ID
$userid  = optional_param('userid', 0, PARAM_INT);  // checklist instance ID

$url = new moodle_url('/mod/checklist/upload.php');

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

$returnurl=new moodle_url('/mod/checklist/view.php?checklist='.$checklist->id);

$PAGE->set_url($url);
require_login($course, true, $cm);

$context = get_context_instance(CONTEXT_MODULE, $cm->id);

if (empty($userid)){
    if (has_capability('mod/checklist:updateown', $context)) {
        $userid = $USER->id;
    }
}


if ($descriptionid) { // description associee
    if (! $record = $DB->get_record('checklist_activite', array("id" => "$descriptionid"))) {
        print_error('error_description', 'checklist');
    }
}
if ($documentid) { // current document
    if (! $record_document = $DB->get_record('checklist_document', array("id" => "$documentid"))) {
        print_error('Document ID is incorrect');
    }
}

/ Taille des telechargements
if (!empty($checklist->maxbytes)){
    $maxbytes=$checklist->maxbytes;
}
else{
    $maxbytes=0;
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



$PAGE->set_url($url);
$PAGE->set_context($context);
$title = strip_tags($course->fullname.': '.get_string('modulename', 'checklist').': '.format_string($checklist->name,true));
$PAGE->set_title($title);
$PAGE->set_heading($title);

$options = array('subdirs'=>0, 'maxbytes'=>get_max_upload_file_size($CFG->maxbytes, $course->maxbytes, $maxbytes), 'maxfiles'=>1, 'accepted_types'=>'*', 'return_types'=>FILE_INTERNAL);

$mform = new mod_checklist_upload_form(null,
    array('checklist'=>$checklist->id, 'contextid'=>$context->id,
    'userid'=>$USER->id, 'activiteid'=>$activite_id,
    'filearea'=>'document', 'msg' => get_string('document_associe', 'checklist'),
    'mailnow' => $mailnow, 'options'=>$options));

if ($mform->is_cancelled()) {
    redirect($returnurl);
} else if ($mform->get_data()) {
    checklist_upload_document($mform, $checklist->id);
    die();
//    redirect(new moodle_url('/mod/checklist/view.php', array('id'=>$cm->id)));
}

echo $OUTPUT->header();
echo $OUTPUT->box_start('generalbox');
$mform->display();
echo $OUTPUT->box_end();
echo $OUTPUT->footer();
die();

?>