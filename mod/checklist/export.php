<?php

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/importexportfields.php');
$id = required_param('id', PARAM_INT); // course module id

if (! $cm = get_coursemodule_from_id('checklist', $id)) {
    error('Course Module ID was incorrect');
}

if (! $course = get_record('course', 'id', $cm->course)) {
    error('Course is misconfigured');
}

if (! $checklist = get_record('checklist', 'id', $cm->instance)) {
    error('Course module is incorrect');
}

require_login($course, true, $cm);

$context = get_context_instance(CONTEXT_MODULE, $cm->id);
if (!has_capability('mod/checklist:edit', $context)) {
    error('You do not have permission to export items from this checklist');
}

$items = get_records_select('checklist_item', "checklist = {$checklist->id} AND userid = 0", 'position');
if (!$items) {
    error(get_string('noitems', 'checklist'));
}

if (strpos($CFG->wwwroot, 'https://') === 0) { //https sites - watch out for IE! KB812935 and KB316431
    @header('Cache-Control: max-age=10');
    @header('Expires: '. gmdate('D, d M Y H:i:s', 0) .' GMT');
    @header('Pragma: ');
} else { //normal http - prevent caching at all cost
    @header('Cache-Control: private, must-revalidate, pre-check=0, post-check=0, max-age=0');
    @header('Expires: '. gmdate('D, d M Y H:i:s', 0) .' GMT');
    @header('Pragma: no-cache');
}

$strchecklist = get_string('checklist', 'checklist');

header("Content-Type: application/download\n");
$downloadfilename = clean_filename("{$course->shortname} $strchecklist {$checklist->name}");
header("Content-Disposition: attachment; filename=\"$downloadfilename.csv\"");

// Output the headings
echo implode($separator, $fields)."\n";

foreach ($items as $item) {
    $output = array();
    foreach ($fields as $field => $title) {
        $output[] = $item->$field;
    }
    echo implode($separator, $output)."\n";
}

exit;
