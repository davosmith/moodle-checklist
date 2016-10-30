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

use mod_checklist\local\checklist_item;

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/importexportfields.php');
global $CFG, $PAGE, $OUTPUT, $DB;
require_once($CFG->libdir.'/formslib.php');
require_once($CFG->libdir.'/csvlib.class.php');

$id = required_param('id', PARAM_INT); // Course module id.

$cm = get_coursemodule_from_id('checklist', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$checklist = $DB->get_record('checklist', array('id' => $cm->instance), '*', MUST_EXIST);

$url = new moodle_url('/mod/checklist/import.php', array('id' => $cm->id));
$PAGE->set_url($url);
require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/checklist:edit', $context);

$returl = new moodle_url('/mod/checklist/edit.php', array('id' => $cm->id));

class checklist_import_form extends moodleform {
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('header', 'formheading', get_string('import', 'checklist'));

        $mform->addElement('filepicker', 'importfile', get_string('importfile', 'checklist'), null,
                           array('accepted_types' => array('*.csv')));

        $this->add_action_buttons(true, get_string('import', 'checklist'));
    }
}

$form = new checklist_import_form();
$defaults = new stdClass();
$defaults->id = $cm->id;

$form->set_data($defaults);

if ($form->is_cancelled()) {
    redirect($returl);
}

$errormsg = null;
if ($data = $form->get_data()) {
    $importid = csv_import_reader::get_new_iid('checklistimport');
    $csv = new csv_import_reader($importid, 'checklistimport');
    if (!$csv->load_csv_content($form->get_file_content('importfile'), 'utf-8', 'comma')) {
        die($csv->get_error());
    }
    $position = $DB->count_records('checklist_item', array('checklist' => $checklist->id, 'userid' => 0)) + 1;

    $csv->init();

    $errormsg = null;
    $ok = true;
    $row = 0;
    while ($line = $csv->next()) {
        $row++;
        if (count($line) != count($fields)) {
            $errormsg = "Row has incorrect number of columns in it:<br />$row";
            $ok = false;
        }

        // Fields defined in importexportfields.php.
        $line = (object)array_combine(array_keys($fields), $line);

        $newitem = new checklist_item();
        $newitem->checklist = $checklist->id;
        $newitem->position = $position++;
        $newitem->userid = 0;

        $newitem->displaytext = trim($line->displaytext);
        $newitem->indent = max(min(intval($line->indent), 10), 0);
        $newitem->itemoptional = max(min(intval($line->itemoptional), 2), 0);
        $newitem->duetime = max(intval($line->duetime), 0);
        $newitem->colour = trim(strtolower($line->colour));
        if (!in_array($newitem->colour, ['red', 'orange', 'green', 'purple', 'black'])) {
            $newitem->colour = 'black';
        }

        if ($newitem->displaytext) { // Don't insert items without any text in them.
            if (!$newitem->insert()) {
                $ok = false;
                $errormsg = 'Unable to insert DB record for item';
                break;
            }
        }
    }

    if ($ok) {
        redirect($returl);
    }
}

$strchecklist = get_string('modulename', 'checklist');
$pagetitle = strip_tags($course->shortname.': '.$strchecklist.': '.format_string($checklist->name, true));

$PAGE->set_title($pagetitle);
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();

if ($errormsg) {
    echo '<p class="error">'.$errormsg.'</p>';
}

$form->display();

echo $OUTPUT->footer();

