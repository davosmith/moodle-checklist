<?php

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/importexportfields.php');
require_once($CFG->libdir.'/formslib.php');

define('STATE_WAITSTART', 0);
define('STATE_INQUOTES', 1);
define('STATE_ESCAPE', 2);
define('STATE_NORMAL', 3);

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
    error('You do not have permission to import items to this checklist');
}

$returl = $CFG->wwwroot.'/mod/checklist/edit.php?id='.$cm->id;

class checklist_import_form extends moodleform {
    function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('header', 'formheading', get_string('import', 'checklist'));

        $mform->addElement('file', 'importfile', get_string('importfile', 'checklist'));

        $this->add_action_buttons(true, get_string('import', 'checklist'));
    }
}

function cleanrow($separator, $row) {
    // Convert and $separator inside quotes into [!SEPARATOR!] (to skip it during the 'explode')
    $state = STATE_WAITSTART;
    $chars = str_split($row);
    $cleanrow = '';
    $quotes = '"';
    foreach ($chars as $char) {
        switch ($state) {
        case STATE_WAITSTART:
            if ($char == ' ' || $char == ',') { } // Still in STATE_WAITSTART
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
            if ($char == ',') { $state = STATE_WAITSTART; }
            break;
        }
        $cleanrow .= $char;
    }

    return $cleanrow;
}

$form = new checklist_import_form();
$defaults = new stdClass;
$defaults->id = $cm->id;

$form->set_data($defaults);

if ($form->is_cancelled()) {
    redirect($returl);
}

$errormsg = '';
if ($data = $form->get_data()) {
    $filename = $form->_upload_manager->files['importfile']['tmp_name'];
    $realname = $form->_upload_manager->files['importfile']['name'];

    if (!file_exists($filename)) {
        $errormsg = "Something went wrong with the file upload";
    } else {
        if (is_readable($filename)) {
            $filearray = file($filename);

            /// Check for Macintosh OS line returns (ie file on one line), and fix
            if (ereg("\r", $filearray[0]) AND !ereg("\n", $filearray[0])) {
                $filearray = explode("\r", $filearray[0]);
            }

            $skipheading = true;
            $ok = true;
            $position = count_records('checklist_item', 'checklist', $checklist->id, 'userid', 0) + 1;

            foreach ($filearray as $row) {
                if ($skipheading) {
                    $skipheading = false;
                    continue;
                }

                // Split $row into array $item, by $separator: ',', but ignore $separator when it occurs within ""
                $row = cleanrow($separator, $row);
                $item = explode($separator, $row);

                if (count($item) != count($fields)) {
                    $errormsg = "Row has incorrect number of columns in it:<br />$row";
                    $ok = false;
                    break;
                }

                $itemfield = reset($item);
                $newitem = new stdClass;
                $newitem->checklist = $checklist->id;
                $newitem->position = $position++;
                $newitem->userid = 0;

                foreach ($fields as $field => $fieldtext) {
                    $itemfield = trim($itemfield);
                    if (substr($itemfield, 0, 1) == '"' && substr($itemfield, -1) == '"') {
                        $itemfield = substr($itemfield, 1, -1);
                    }
                    $itemfield = trim($itemfield);
                    $itemfield = str_replace('[!SEPARATOR!]', $separator, $itemfield);
                    switch ($field) {
                    case 'displaytext':

                        $newitem->displaytext = addslashes(trim($itemfield));
                        break;

                    case 'indent':
                        $newitem->indent = intval($itemfield);
                        if ($newitem->indent < 0) {
                            $newitem->indent = 0;
                        } else if ($newitem->indent > 10) {
                            $newitem->indent = 10;
                        }
                        break;

                    case 'itemoptional':
                        $newitem->itemoptional = intval($itemfield);
                        if ($newitem->itemoptional < 0 || $newitem->itemoptional > 2) {
                            $newitem->itemoptional = 0;
                        }
                        break;

                    case 'duetime':
                        $newitem->duetime = intval($itemfield);
                        if ($newitem->itemoptional < 0) {
                            $newitem->itemoptional = 0;
                        }
                        break;

                    case 'colour':
                        $allowedcolours = array('red', 'orange', 'green', 'purple', 'black');
                        $itemfield = trim(strtolower($itemfield));
                        if (!in_array($itemfield, $allowedcolours)) {
                            $itemfield = 'black';
                        }
                        $newitem->colour = $itemfield;
                        break;
                    }

                    $itemfield = next($item);
                }

                if ($newitem->displaytext) { // Don't insert items without any text in them
                    if (!insert_record('checklist_item', $newitem)) {
                        $ok = false;
                        $errormsg = 'Unable to insert DB record for item';
                        break;
                    }
                }
            }

            if ($ok) {
                redirect($returl);
            }

        } else {
            $errormsg = "Something went wrong with the file upload";
        }
    }
}

$strchecklist = get_string('modulename', 'checklist');
$strchecklists = get_string('modulenameplural', 'checklist');
$pagetitle = strip_tags($course->shortname.': '.$strchecklist.': '.format_string($checklist->name, true));

$navlinks = array();
$navlinks[] = array('name' => $strchecklists, 'link' => "index.php?id={$course->id}", 'type' => 'activity');
$navlinks[] = array('name' => format_string($checklist->name), 'link' => '', 'type' => 'activityinstance');
$navigation = build_navigation($navlinks);

print_header_simple($pagetitle, '', $navigation, '', '', true,
                    update_module_button($cm->id, $course->id, $strchecklist), navmenu($course, $cm));

if ($errormsg) {
    echo '<p class="error">'.$errormsg.'</p>';
}

$form->display();

print_footer($course);

