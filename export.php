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

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/importexportfields.php');
global $DB, $PAGE, $CFG;
$id = required_param('id', PARAM_INT); // Course module id.

$cm = get_coursemodule_from_id('checklist', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$checklist = $DB->get_record('checklist', array('id' => $cm->instance), '*', MUST_EXIST);

$url = new moodle_url('/mod/checklist/export.php', array('id' => $cm->id));
$PAGE->set_url($url);
require_login($course, true, $cm);

$context = context_module::instance($cm->id);
if (!has_capability('mod/checklist:edit', $context)) {
    error('You do not have permission to export items from this checklist');
}

$items = $DB->get_records_select('checklist_item', "checklist = ? AND userid = 0", array($checklist->id), 'position');
if (!$items) {
    error(get_string('noitems', 'checklist'));
}

if (strpos($CFG->wwwroot, 'https://') === 0) { // Https sites - watch out for IE! KB812935 and KB316431.
    @header('Cache-Control: max-age=10');
    @header('Expires: '.gmdate('D, d M Y H:i:s', 0).' GMT');
    @header('Pragma: ');
} else { // Normal http - prevent caching at all cost.
    @header('Cache-Control: private, must-revalidate, pre-check=0, post-check=0, max-age=0');
    @header('Expires: '.gmdate('D, d M Y H:i:s', 0).' GMT');
    @header('Pragma: no-cache');
}

$strchecklist = get_string('checklist', 'checklist');

header("Content-Type: application/download\n");
$downloadfilename = clean_filename("{$course->shortname} $strchecklist {$checklist->name}");
header("Content-Disposition: attachment; filename=\"$downloadfilename.csv\"");

// Output the headings.
echo implode($separator, $fields)."\n";

foreach ($items as $item) {
    $output = array();
    foreach ($fields as $field => $title) {
        $output[] = $item->$field;
    }
    echo implode($separator, $output)."\n";
}

exit;
