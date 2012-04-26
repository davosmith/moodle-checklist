CheckList - Moodle module
===========================================================================================
// Modifications by jean.fruitet@univ-nantes.fr

I made additions to original code to get some new functionnalities

CheckList Version : $module->release  = 'JF-2.x (Build: 2012041100)';
This is a fork of master repositoy 


1) Users may comment their Skills and upload files or URL as prove of practice.
----------------------------------------------------------------------------------

a) New DB tables 'checklist_description' and 'checklist_document'

b) New config parameter :
$CFG->checklist_description_display
New script
./mod/checklist/settings.php

c) New scripts
edit_description.php
edit_document.php
delete_description.php
delete_document.php
file_api.php

d) Scripts modification
lib.php :

    // MOODLE 2.0 FILE API
    function checklist_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload) {
    //Serves activite documents and other files.
    function checklist_send_file($course, $cm, $context, $filearea, $args) {
    // Serves activite documents and other files.

locallib.php : many functions added or  modified (Look for "// MODIF JF" tag.)

Class checklist_class {

(...)
 // MODIF JF 2012/03/18
    /* BEGIN OF FUNCTIONS ADDED BY JF ***** */
    (...)
    /* END OF FUNCTIONS ADDED BY JF **      */

}


2) Import of Outcomes file (csv format) to get Outcomes as Items in CheckList
------------------------------------------------------------------------------

Teachers may import Outcomes (outcomes.csv) files in CheckList to get these outcomes as Items.
Furthermore any Item of CheckList may be validated by the way of Moodle activity
(Assignment or Quizz for exemple) which uses the same Outcomes.

a) This does not affect any CheckList DB tables

b) New config parameter :
$CFG->checklist_outcomes_input

c) New scripts :

importexportoutcomes.php
import_outcomes.php
export_outcomes.php
export_selected_outcomes.php
select_export.php
cron_outcomes.php

d) Scripts modification
lib.php ::
// MODIF JF 2012/03/18
define ("USES_OUTCOMES", 1); // Outcomes imported as items

locallib.php ::
function view_import_export() {
(...)
        // MODIF JF 2012/03/18
        if (USES_OUTCOMES && !empty($CFG->checklist_outcomes_input)){
            $importoutcomesurl = new moodle_url('/mod/checklist/import_outcomes.php', array('id' => $this->cm->id));
            $importoutcomesstr = get_string('import_outcomes', 'checklist');
            $exportoutcomesurl = new moodle_url('/mod/checklist/select_export.php', array('id' => $this->cm->id));
            $exportoutcomesstr = get_string('export_outcomes', 'checklist');
            echo "<a href='$importurl'>$importstr</a>&nbsp;&nbsp;<a href='$importoutcomesurl'>$importoutcomesstr</a>&nbsp;&nbsp;<a href='$exporturl'>$exportstr</a>&nbsp;&nbsp;<a href='$exportoutcomesurl'>$exportoutcomesstr</a>";
        }
        else{
            echo "<a href='$importurl'>$importstr</a> &nbsp;&nbsp;&nbsp; <a href='$exporturl'>$exportstr</a>";
        }
(...)
}

autoupdate.php
In function checklist_autoupdate(
    // MODIF JF 2012/03/18
    if ($module == 'referentiel') {
        return 0;
    }

New localisation strings

lang/en/checklist.php   :: new strings
lang/fr/checklist.php   :: new strings and translation

Functions replacement :
In all scripts
error('error_message') -> print_error(get_string('error_code', 'checklist'));


3) Backup / Restore :
------------------------------------------------------------------------------
./mod/checklist/backup/moodle2 scripts completed for new tables

4) Installation
------------------------------------------------------------------------------
./mod/checklist/install.xml
./mod/checklist/upgrade.php


===========================================================================================
