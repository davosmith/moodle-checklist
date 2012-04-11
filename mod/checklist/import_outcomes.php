<?php // $Id:  import_outcome.php,v 1.0 2012/03/18 00:00:00 jfruitet Exp $
///////////////////////////////////////////////////////////////////////////
//                                                                       //
// NOTICE OF COPYRIGHT                                                   //
//                                                                       //
// Moodle - Modular Object-Oriented Dynamic Learning Environment         //
//          http://moodle.org                                            //
//                                                                       //
// Copyright (C) 2005 Martin Dougiamas  http://dougiamas.com             //
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

/**
 * Library of functions and constants for module checklist
 * Adapted from import.php to importSkills Repository outcomes files
 *
 * This script import a Skill Repository Outcome file (exported by 'referentiel' module)
 * as Items in CheckList module
 *
 * ITEMS CSV FILE
 * Separator ','
 * Item text,Indent,Type (0 - normal; 1 - optional; 2 - heading),Due Time (timestamp),Colour (red; orange; green; purple; black)
 * Savoir Installer une Webcam,0,0,0,black
 * Savoir Choisir une WebCam,1,0,0,black
 * Savoir Participer à une Webconférence,0,0,0,black
 * Savoir Organiser et piloter une Webconférence,0,0,0,black
 *
 * OUTCOMES CSV FILE
 * Separator ';'
 * outcome_name;outcome_shortname;outcome_description;scale_name;scale_items;scale_description
 * "C2i2e-2011 A.1-1 :: Identifier les personnes ressources Tic et leurs rôles respectifs (...)";A.1-1;"Identifier les personnes ressources Tic et leurs rôles respectifs au niveau local, régional et national.";"Item référentiel";"Non pertinent,Non validé,Validé";"Ce barème est destiné à évaluer l'acquisition des compétences du module référentiel."
 *
 * Skills repositoy outcome_name has to match  '/(.*)::(.*)/i' regular expression
 * That's mandatory
 *
 * @author  David Smith <moodle@davosmith.co.uk>
 * @package mod/checklist
 *
 * @author Jean.fruitet@univ-nantes.fr
 * @author jfruitet
 * @version $Id:  import_outcome.php,v 1.0 2012/03/18 00:00:00 jfruitet Exp $
 * @package checklist 'JF-2.x (Build: 2012041100)';
 **/


require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
// require_once(dirname(__FILE__).'/importexportfields.php');    // Items format
require_once(dirname(__FILE__).'/importexportoutcomes.php');  // Outcomes format
require_once($CFG->libdir.'/formslib.php');

define('STATE_WAITSTART', 0);
define('STATE_INQUOTES', 1);
define('STATE_ESCAPE', 2);
define('STATE_NORMAL', 3);

$id = required_param('id', PARAM_INT); // course module id

$cm = get_coursemodule_from_id('checklist', $id)){
    print_error('error_cmid', 'checklist'); // 'Course Module ID was incorrect'
}
$course = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);
$checklist = $DB->get_record('checklist', array('id' => $cm->instance), '*', MUST_EXIST);

$url = new moodle_url('/mod/checklist/import_outcomes.php', array('id' => $cm->id));
$PAGE->set_url($url);
require_login($course, true, $cm);

if ($CFG->version < 2011120100) {
    $context = get_context_instance(CONTEXT_MODULE, $cm->id);
} else {
    $context = context_module::instance($cm->id);
}
if (!has_capability('mod/checklist:edit', $context)) {
    print_error(get_string('error_import_items', 'checklist')); // 'You do not have permission to import items to this checklist');
}

$returl = new moodle_url('/mod/checklist/edit.php', array('id' => $cm->id));

class checklist_import_outcomes_form extends moodleform {
    function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('header', 'formheading', get_string('import_outcomes', 'checklist'));

        $mform->addElement('filepicker', 'importfile', get_string('importfile', 'checklist'), null, array('accepted_types'=>array('*.csv')));

        $this->add_action_buttons(true, get_string('import', 'checklist'));
    }
}



function cleanrow_outcomes($separator, $row) {
    // Convert and $separator inside quotes into [!SEPARATOR!] (to skip it during the 'explode')

    $state = STATE_WAITSTART;
    $chars = str_split($row);
    $cleanrow = '';
    $quotes = '"';
    foreach ($chars as $char) {
        switch ($state) {
        case STATE_WAITSTART:
            if ($char == ' ' || $char == $separator) { } // Still in STATE_WAITSTART
            else if ($char == '"') { $quotes = '"'; $state = STATE_INQUOTES; }
            else if ($char == "'") { $quotes = "'"; $state = STATE_INQUOTES; }
            else { $state = STATE_NORMAL; }
            break;
        case STATE_INQUOTES:
            if ($char == $quotes) { $state = STATE_NORMAL; } // End of quotes
            else if ($char == '\\') { $state = STATE_ESCAPE; continue 2; }  // Possible escaped quotes skip (for now)
            else if ($char == $separator) { $cleanrow .= '[!SEPARATOR!]'; continue 2; } // Replace $separator and continue loop
            break;
        case STATE_ESCAPE:
            // Retain escape char, unless escaping a quote character
            if ($char != $quotes) { $cleanrow .= '\\'; }
            $state = STATE_INQUOTES;
            break;
        default:
            if ($char == $separator) { $state = STATE_WAITSTART; }
            break;
        }
        $cleanrow .= $char;
    }

    return $cleanrow;
}

$form = new checklist_import_outcomes_form();
$defaults = new stdClass;
$defaults->id = $cm->id;

$form->set_data($defaults);

if ($form->is_cancelled()) {
    redirect($returl);
}

$errormsg = '';

$debug=false; // Histoire d'avoir un affichage

if ($data = $form->get_data()) {
    $filename = $form->save_temp_file('importfile');

    if (!file_exists($filename)) {
        $errormsg = get_string('error_file_upload', 'checklist');// "Something went wrong with the file upload";
    } else {
        if (is_readable($filename)) {
            $filearray = file($filename);
            unlink($filename);

            /// Check for Macintosh OS line returns (ie file on one line), and fix
            if (ereg("\r", $filearray[0]) AND !ereg("\n", $filearray[0])) {
                $filearray = explode("\r", $filearray[0]);
            }

            $skipheading = true;
            $ok = true;
            $position = $DB->count_records('checklist_item', array('checklist' => $checklist->id, 'userid' => 0)) + 1;

            $ok_referentiel=false; // flag for Skills repository outcomes

            foreach ($filearray as $row) {
                if ($skipheading) {
                    $skipheading = false;
                    continue;
                }

                // Separator defined in importexportoutcomes.php (currently ';')
                // Split $row into array $outcomes, by $separator, but ignore $separator when it occurs within ""
                $row = cleanrow_outcomes($separator_outcomes, $row);
                $outcome = explode($separator_outcomes, $row);

                if (count($outcome) != count($fields_outcomes)) {
                    $errormsg = get_string('error_number_columns_outcomes', 'checklist', $row);// "Row Outcome has incorrect number of columns in it:<br />$row";
                    $ok = false;
                    break;
                }

                $outcomefield = reset($outcome);

                // $fields defined in importexportfields.php
                foreach ($fields_outcomes as $field => $fieldtext) {
                    $outcomefield = trim($outcomefield);
                    if (substr($outcomefield, 0, 1) == '"' && substr($outcomefield, -1) == '"') {
                        $outcomefield = substr($outcomefield, 1, -1);
                    }
                    $outcomefield = trim($outcomefield);
                    $outcomefield = str_replace('[!SEPARATOR!]', $separator_outcomes, $outcomefield);

                    switch ($field) {

                    case 'outcome_name':
                        $an_outcome=get_outcome_code(trim($outcomefield));

                        if (!empty($an_outcome)){

                            if (!empty($an_outcome->code_referentiel)){
                                if (!empty($an_outcome->code_referentiel) && !$ok_referentiel){
                                    $newitem_ref = new stdClass;
                                    $newitem_ref->checklist = $checklist->id;
                                    $newitem_ref->position = $position++;
                                    $newitem_ref->userid = 0;
                                    $newitem_ref->displaytext = $an_outcome->code_referentiel;
                                    $newitem_ref->indent = 0;
                                    $newitem_ref->itemoptional = 0;
                                    $newitem_ref->duetime = 0;
                                    $newitem_ref->colour = 'purple';
                                    $ok_referentiel=true;

                                    if (!empty($newitem_ref->displaytext)) { // Don't insert items without any text in them
                                        if (!$DB->insert_record('checklist_item', $newitem_ref)) {
                                            $ok = false;
                                            $errormsg = get_string('error_insert_db', 'checklist'); // 'Unable to insert DB record for item';
                                            break;
                                        }
                                    }
                                }
                                $newitem = new stdClass;
                                $newitem->checklist = $checklist->id;
                                $newitem->position = $position++;
                                $newitem->userid = 0;

                                $newitem->displaytext = trim($outcomefield);
                                $newitem->indent = 1;
                                $newitem->itemoptional = 0;
                                $newitem->duetime = 0;
                                $newitem->colour = 0;
                            }
                        }
                        else{
                            $newitem = new stdClass;
                            $newitem->checklist = $checklist->id;
                            $newitem->position = $position++;
                            $newitem->userid = 0;

                            $newitem->displaytext = trim($outcomefield);
                            $newitem->indent = 1;
                            $newitem->itemoptional = 0;
                            $newitem->duetime = 0;
                            $newitem->colour = 0;
                        }
                        break;
                    default:
                        break;
                    }
                    $outcomefield = next($outcome);
                }

                if ($newitem->displaytext) { // Don't insert items without any text in them
                    if (!$DB->insert_record('checklist_item', $newitem)) {
                        $ok = false;
                        $errormsg = get_string('error_insert_db', 'checklist'); // 'Unable to insert DB record for item';
                        break;
                    }
                }
            }

            if ($ok) {
                redirect($returl);
            }

        } else {
            $errormsg = get_string('error_file_upload', 'checklist'); // "Something went wrong with the file upload";
        }
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

