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
 * Library of functions and constants for module checklist
 * Adapted from export.php to export Skills Repository outcomes files
 *
 * This script export a selected list of Items of CheckList module as a Skill Repository Outcome file
 * exactly like 'referentiel' module

 * ITEMS CSV FILE
 * Separator ','
 * Item text,Indent,Type (0 - normal; 1 - optional; 2 - heading),Due Time (timestamp),Colour (red; orange; green; purple; black)
 * DES_rhumato,0,0,0,purple
 * DES_rhumato UV1.1.1 :: Je sais définir Incidence.,1,0,0,black
 * DES_rhumato UV1.1.2 :: Je sais définir Prévalence.,1,0,0,black
 * DES_rhumato UV1.2.1 :: Savoir interpréter les résultats d'un essai (...),1,0,0,black
 * DES_rhumato UV1.2.2 :: Savoir discuter la pertinence d'un critère d'évaluation.,1,0,0,black
 * DES_rhumato UV2.1.1 :: Connaître l’incidence et la prévalence de la (...),1,0,0,black
 * DES_rhumato UV2.1.2 :: Connaître les facteurs déclenchant de la PR et les (...),1,0,0,black
 * DES_rhumato UV2.1.3 :: Connaître les principaux facteurs d’environnement (...),1,0,0,black
 * DES_rhumato UV2.1.4 :: Connaître les principales causes de morbidité et de (...),1,0,0,black
 * DES_rhumato UV2.2.1 :: Connaître de façon globale les hypothèses pathogéniques (...),1,0,0,black
 *
 * OUTCOMES CSV FILE
 * Separator ';'
 * outcome_name;outcome_shortname;outcome_description;scale_name;scale_items;scale_description
 * "DES_rhumato UV1.1.1 :: Je sais définir Incidence.";UV1.1.1;"Je sais définir Incidence.";"Item référentiel";"Non pertinent,Non validé,Validé";"Ce barème est destiné à évaluer l'acquisition des compétences du module référentiel."
 * "DES_rhumato UV1.1.2 :: Je sais définir Prévalence.";UV1.1.2;"Je sais définir Prévalence.";"Item référentiel";"Non pertinent,Non validé,Validé";"Ce barème est destiné à évaluer l'acquisition des compétences du module référentiel."
 *
 * Skills repositoy outcome_name has to match  '/(.*)::(.*)/i' regular expression
 * That's mandatory
 *
 *
 * @author jfruitet jean.fruitet@univ-nantes.fr
 *
 * @version $Id:  import_outcome.php,v 1.0 2012/03/18 00:00:00 jfruitet Exp $
 * @package checklist 'JF-2.x (Build: 2012041100)';
 **/



require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');
require_once(dirname(__FILE__).'/importexportoutcomes.php');  // Outcomes format
require_once(dirname(__FILE__).'/locallib.php');

global $DB;

$id = optional_param('id', 0, PARAM_INT); // course_module ID, or
$checklistid  = optional_param('checklist', 0, PARAM_INT);  // checklist instance ID
if ($CFG->version < 2011120100) {
    $items = optional_param('items', false, PARAM_INT);
} else {
    $items = optional_param_array('items', false, PARAM_INT);
}
$referentielcode = optional_param('referentielcode', '', PARAM_ALPHANUMEXT);
$useitemid = optional_param('useitemid', '', PARAM_INT);
$quit = optional_param('quit', '', PARAM_ALPHANUM);

$url = new moodle_url('/mod/checklist/export_selected_outcomes.php');
$urlredirect = new moodle_url('/mod/checklist/view.php');

if ($id) {
    if (!$cm = get_coursemodule_from_id('checklist', $id)){
        print_error('error_cmid', 'checklist'); // 'Course Module ID was incorrect'
    }
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $checklist = $DB->get_record('checklist', array('id' => $cm->instance), '*', MUST_EXIST);
    $url->param('id', $id);
    $urlredirect->param('id', $id);
} else if ($checklistid) {
    $checklist = $DB->get_record('checklist', array('id' => $checklistid), '*', MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $checklist->course), '*', MUST_EXIST);
    if (!$cm = get_coursemodule_from_instance('checklist', $checklist->id, $course->id)) {
        print_error('error_cmid', 'checklist'); // 'Course Module ID was incorrect'
    }
    $url->param('checklist', $checklistid);
    $urlredirect->param('checklist', $checklistid);
} else {
    print_error('error_specif_id', 'checklist'); // 'You must specify a course_module ID or an instance ID'
}


if ($quit){
    redirect($urlredirect);
}

$PAGE->set_url($url);
require_login($course, true, $cm);

if ($CFG->version < 2011120100) {
    $context = get_context_instance(CONTEXT_MODULE, $cm->id);
} else {
    $context = context_module::instance($cm->id);
}
$userid = $USER->id;
if (!has_capability('mod/checklist:edit', $context)) {
    print_error(get_string('error_export_items', 'checklist')); // You do not have permission to export items from this checklist'
    die();
}

if (!confirm_sesskey()) {
    echo get_string('error_sesskey', 'checklist'); // 'Error: invalid sesskey';
    die();
}

if (!$items || !is_array($items)) {
    print_error('error_select', 'checklist', $urlredirect); // 'You must specify a course_module ID or an instance ID'
    // redirect($urlredirect);
}
else{
    // Fichier à creer
    $contenu='';
    $chk = new checklist_class($cm->id, $userid, $checklist, $cm, $course);
    $selected_items=$chk->exportchecks($items);
    if (!empty($selected_items)){
        // Creer  le fichier
        $ok_referentiel=false; // flag for Skills repository outcomes

        // Output the headings
        $contenu = implode($separator_outcomes, array_keys($fields_outcomes))."\n";
        foreach ($selected_items as $an_item) {
            if (!empty($an_item->displaytext)){
                $an_outcome=get_outcome_code_from_item($an_item, $referentielcode, $useitemid);
                if (!empty($an_outcome) && !empty($an_outcome->outcome) && !empty($an_outcome->code_competence) && !empty($an_outcome->description)){
                    $ok_referentiel=true;
                    $contenu.=str_replace('""','"', $an_outcome->outcome.$separator_outcomes.$an_outcome->code_competence.$separator_outcomes.$an_outcome->description.$separator_outcomes.get_string('scale_name', 'checklist').$separator_outcomes.get_string('scale_items', 'checklist').$separator_outcomes.get_string('scale_description', 'checklist'))."\n";
                }
            }
        }

        if ($ok_referentiel){
            if (strpos($CFG->wwwroot, 'https://') === 0) { //https sites - watch out for IE! KB812935 and KB316431
                @header('Cache-Control: max-age=10');
                @header('Expires: '. gmdate('D, d M Y H:i:s', 0) .' GMT');
                @header('Pragma: ');
            } else { //normal http - prevent caching at all cost
                @header('Cache-Control: private, must-revalidate, pre-check=0, post-check=0, max-age=0');
                @header('Expires: '. gmdate('D, d M Y H:i:s', 0) .' GMT');
                @header('Pragma: no-cache');
            }
            header("Content-Type: application/download\n");
            $downloadfilename = clean_filename("outcomes_{$course->shortname}_{$checklist->name}_".date("Ymd"));
            header("Content-Disposition: attachment; filename=\"$downloadfilename.csv\"");

            echo $contenu;

            // redirect($urlredirect);
        }
    }
}
exit;
