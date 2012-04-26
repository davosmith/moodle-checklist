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

// MODIF JF 2012/03/18 //////////////////////////////////////////////////////////////

// Mahara portfolio
$string['mustprovideinstanceid'] = 'You must provide an instance id';
$string['mustprovideexportformat'] = 'You must provide an export format';
$string['mustprovideuser'] = 'You must provide an user id';
$string['pluginname'] = 'Mahara portfolio';
$string['clicktopreview'] = 'click to preview in full-size popup';
$string['clicktoselect'] = 'click to select page';
$string['nomaharahostsfound'] = 'No mahara hosts found.';
$string['noviewscreated'] = 'You have not created any pages in {$a}.';
$string['noviewsfound'] = 'No matching pages found in {$a}.';
$string['previewmahara'] = 'Preview';
$string['site'] = 'Site';
$string['site_help'] = 'This setting lets you select which Mahara site your students should submit their pages from. (The Mahara site must already be configured for mnet networking with this Moodle site.)';
$string['selectedview'] = 'Submitted Page';
$string['selectmaharaview'] = 'Select one of your {$a->name} portfolio pages from this complete list, or <a href="{$a->jumpurl}">click here</a> to visit {$a->name} and create a page right now.';
$string['titlemahara'] = 'Title';
$string['typemahara'] = 'Mahara portfolio';
$string['views'] = 'Pages';
$string['viewsby'] = 'Pages by {$a}';
$string['viewmahara'] = 'Mahara view';
$string['select'] = 'Select';

$string['upload_portfolio'] = 'Link to a page of my portfolio';
$string['timecreated'] = 'Created the ';
$string['timemodified'] = 'Modified the ';
$string['id'] = 'ID# ';
$string['checklist_check'] = 'Evaluation ';
$string['teachermark'] = 'Appreciation ';
$string['usertimestamp'] = 'Demanded the ';
$string['teachertimestamp'] = 'Evaluated the ';
$string['commentby'] = 'Comment by ';
$string['argumentation'] = 'Argumentation';

// Config
$string['outcomes_input'] = 'Activate Outcomes files';
$string['config_outcomes_input'] = 'Allows teachers to import in Checklist the outcomes checked in course\'s activities';
$string['checklist_description'] = 'Allows students to upload files as prove of practice';
$string['config_description'] = 'Permet aux utilisateurs de déposer des documents comme trace de pratique.';

// error strings
$string['error_cmid'] = 'Course Module ID was incorrect';
$string['error_cm'] = 'Course Module is incorrect';
$string['error_course'] = 'Course is misconfigured';
$string['error_specif_id'] = 'You must specify a course_module ID or an instance ID';

$string['error_checklist_id'] = 'Checklist ID was incorrect';
$string['error_user'] = 'No such user!';
$string['error_sesskey'] = 'Error: Invalid sesskey';
$string['error_action'] = 'Error: Invalid action - "{a}"';
$string['error_itemlist'] = 'Error: invalid (or missing) items list';

$string['error_import_items'] = 'You do not have permission to import items to this checklist';
$string['error_export_items'] = 'You do not have permission to export items from this checklist';
$string['error_file_upload'] = 'Something went wrong with the file upload';
$string['error_number_columns'] = 'Row has incorrect number of columns in it:<br />{$a}';
$string['error_insert_db'] = 'Unable to insert DB record for item';
$string['error_update'] = 'Error: you do not have permission to update this checklist';
$string['error_select'] = 'Error: At least one Item has to be selected';
$string['OK'] = 'OK';
$string['quit'] = 'Quit';

// Outcomes
$string['useitemid'] = 'Use Item ID as key ';
$string['a_completer'] = 'TO COMPLETE';
$string['selectexport'] = 'Export Outcomes';
$string['addreferentielname'] = 'Skill repository code name';
$string['confirmreferentielname'] = 'Confirm skills repository code name ';
$string['referentiel_codeh'] = 'Type in a skills repository code name (Optional)';
$string['referentiel_codeh_help'] = 'This code name identify the outcomes matching the same Skills repository.
<br />If Items names are not keys check <i>"'.$string['useitemid'].'</i>"';
$string['select_items_export'] = 'Selection items to exporte';
$string['items_exporth'] = 'Exported Items';
$string['items_exporth_help'] = 'Selected items will be exported in the same Outcomes file.';

$string['select_all'] = 'Check all Items';
$string['select_not_any'] = 'Uncheck all Items';
$string['export_outcomes'] = 'Export items as outcomes';
$string['import_outcomes'] = 'Import outcomes as items';
$string['error_number_columns_outcomes'] = 'Row Outcome has incorrect number of columns in it:<br />{$a}';
$string['old_comment'] = 'Previous comment:';
$string['outcome_link'] = ' <a href="{$a->link}">{$a->name}</a> ';
$string['outcomes'] = 'outcomes';   // DO NOT TRANSLATE. NE PAS TRADUIRE
$string['outcome_name'] = 'Outcome name';
$string['outcome_shortname'] = 'Outcome shortname';
$string['outcome_description'] = 'Outcome Description';
// Scale / Bareme
$string['scale_name'] = 'Skill Item';
$string['scale_items'] = 'Not relevent,Non validated,Validated';
$string['scale_description'] = 'This scale is intended to estimate the acquisition of the skills by the way of Outcomes.';

// Description
$string['edit_description'] = 'Edit the description';
$string['input_description'] = 'Draft your argument';
$string['descriptionh'] = 'Argumentation Help';
$string['descriptionh_help'] = 'Indicate in a brief way the motives which allow you to assert that this task is finished or the skill acquired.';
$string['description'] = 'Type in your argumentation';
$string['delete_description'] = 'Delete the description';

// Document
$string['urlh'] = 'Selection of a Web link';
$string['urlh_help'] = 'You may copy / paste a link <br />(beginning by "<i>http://</i>" or "<i>https://</i>")
inthe field URL, or you may upload a file from your computer...';

$string['add_link'] = 'Add a link or a document';
$string['delete_link'] = 'Delete a link';
$string['edit_link'] = 'Edit a link';
$string['doc_num'] = 'Document N°{$a} ';
$string['edit_document'] = 'Edit the document';
$string['document_associe'] = 'Linked Document';
$string['url'] = 'URL';
$string['description_document'] = 'Document description ';
$string['target'] = 'Open that link in a new window';
$string['title'] = 'Document title';
$string['delete_document'] = 'Delete a document';

$string['documenth'] = 'Document Help';
$string['documenth_help'] = 'Documents linked to a description are "prove of pratice oriented".
<br />
You may link to each Item a description and one or many files ou URLs (Web links).
<br />Document description : a short notice.
<br />URL : Web link
<br /> &nbsp; &nbsp; (or a file upload from your computer).
<br />Title of the link
<br />Targeted frame';


/////////////////////////////////////////////////////////////////////////////////////

$string['addcomments'] = 'Add comments';

$string['additem'] = 'Add';
$string['additemalt'] = 'Add a new item to the list';
$string['additemhere'] = 'Insert new item after this one';
$string['addownitems'] = 'Add your own items';
$string['addownitems-stop'] = 'Stop adding your own items';

$string['allowmodulelinks'] = 'Allow module links';

$string['anygrade'] = 'Any';
$string['autopopulate'] = 'Show course modules in checklist';
$string['autopopulate_help'] = 'This will automatically add a list of all the resources and activities in the current course into the checklist.<br />
This list will be updated with any changes in the course, whenever you visit the \'Edit\' page for the checklist.<br />
Items can be hidden from the list, by clicking on the \'hide\' icon beside them.<br />
To remove the automatic items from the list, change this option back to \'No\', then click on \'Remove course module items\' on the \'Edit\' page.';
$string['autoupdate'] = 'Check-off when modules complete';
$string['autoupdate_help'] = 'This will automatically check-off items in your checklist when you complete the relevant activity in the course.<br />
\'Completing\' an activity varies from one activity to another - \'view\' a resource, \'submit\' a quiz or assignment, \'post\' to a forum or join in with a chat, etc.<br />
If a Moodle 2.0 completion tracking is switched on for a particular activity, that will be used to tick-off the item in the list<br />
For details of exactly what causes an activity to be marked as \'complete\', ask your site administrator to look in the file \'mod/checklist/autoupdate.php\'<br />
Note: it can take up to 60 seconds for a student\'s activity to be reflected in their checklist';

$string['autoupdatewarning_both'] = 'There are items on this list that will be automatically updated (as students complete the related activity). However, as this is a \'student and teacher\' checklist the progress bars will not update until a teacher agrees the marks given.';
$string['autoupdatewarning_student'] = 'There are items on this list that will be automatically updated (as students complete the related activity).';
$string['autoupdatewarning_teacher'] = 'Automatic updating has been switched on for this checklist, but these marks will not be displayed as only \'teacher\' marks are shown.';

$string['canceledititem'] = 'Cancel';

$string['calendardescription'] = 'This event was added by the checklist: {$a}';

$string['changetextcolour'] = 'Next text colour';

$string['checkeditemsdeleted'] = 'Checked items deleted';

$string['checklist'] = 'checklist';
$string['pluginadministration'] = 'Checklist administration';

$string['checklist:edit'] = 'Create and edit checklists';
$string['checklist:emailoncomplete'] = 'Receive completion emails';
$string['checklist:preview'] = 'Preview a checklist';
$string['checklist:updatelocked'] = 'Update locked checklist marks';
$string['checklist:updateother'] = 'Update students\' checklist marks';
$string['checklist:updateown'] = 'Update your checklist marks';
$string['checklist:viewmenteereports'] = 'View mentee progress (only)';
$string['checklist:viewreports'] = 'View students\' progress';

$string['checklistautoupdate'] = 'Allow checklists to automatically update';

$string['checklistfor'] = 'Checklist for';

$string['checklistintro'] = 'Introduction';
$string['checklistsettings'] = 'Settings';

$string['checks'] = 'Check marks';
$string['comments'] = 'Comments';

$string['completionpercentgroup'] = 'Require checked-off';
$string['completionpercent'] = 'Percentage of items that should be checked-off:';

$string['configchecklistautoupdate'] = 'Before allowing this you must make a few changes to the core Moodle code, please see mod/checklist/README.txt for details';

$string['confirmdeleteitem'] = 'Are you sure you want to permanently delete this checklist item?';

$string['deleteitem'] = 'Delete this item';

$string['duedatesoncalendar'] = 'Add due dates to calendar';

$string['edit'] = 'Edit checklist';
$string['editchecks'] = 'Edit checks';
$string['editdatesstart'] = 'Edit dates';
$string['editdatesstop'] = 'Stop editing dates';
$string['edititem'] = 'Edit this item';

$string['emailoncomplete'] = 'Email teachers when checklist is complete';
$string['emailoncomplete_help'] = 'When a checklist is complete, a notification email is sent to all the teachers on the course.<br />
An administrator can control who receives this email using the capability \'mod:checklist/emailoncomplete\' - by default all teachers and non-editing teachers have this capability.';
$string['emailoncompletesubject'] = 'User {$a->user} has completed checklist \'{$a->checklist}\'';
$string['emailoncompletebody'] = 'User {$a->user} has completed checklist \'{$a->checklist}\'
View the checklist here:';

$string['export'] = 'Export items';

$string['forceupdate'] = 'Update checks for all automatic items';

$string['gradetocomplete'] = 'Grade to complete:';
$string['guestsno'] = 'You do not have permission to view this checklist';

$string['headingitem'] = 'This item is a heading - it will not have a checkbox beside it';

$string['import'] = 'Import items';
$string['importfile'] = 'Choose file to import';
$string['importfromsection'] = 'Current section';
$string['importfromcourse'] = 'Whole course';
$string['indentitem'] = 'Indent item';
$string['itemcomplete'] = 'Completed';
$string['items'] = 'Checklist items';

$string['linktomodule'] = 'Link to this module';

$string['lockteachermarks'] = 'Lock teacher marks';
$string['lockteachermarks_help'] = 'When this setting is enabled, once a teacher has saved a \'Yes\' mark, they will be unable to change it. Users with the capability \'mod/checklist:updatelocked\' will still be able to change the mark.';
$string['lockteachermarkswarning'] = 'Note: Once you have saved these marks, you will be unable to change any \'Yes\' marks';

$string['modulename'] = 'Checklist';
$string['modulenameplural'] = 'Checklists';

$string['moveitemdown'] = 'Move item down';
$string['moveitemup'] = 'Move item up';

$string['noitems'] = 'No items in the checklist';

$string['optionalitem'] = 'This item is optional';
$string['optionalhide'] = 'Hide optional items';
$string['optionalshow'] = 'Show optional items';

$string['percentcomplete'] = 'Required items';
$string['percentcompleteall'] = 'All items';
$string['pluginname'] = 'Checklist';
$string['preview'] = 'Preview';
$string['progress'] = 'Progress';

$string['removeauto'] = 'Remove course module items';

$string['report'] = 'View Progress';
$string['reporttablesummary'] = 'Table showing the items on the checklist that each student has completed';

$string['requireditem'] = 'This item is required - it must be completed';

$string['resetchecklistprogress'] = 'Reset checklist progress and user items';

$string['savechecks'] = 'Save';

$string['showfulldetails'] = 'Show full details';
$string['showprogressbars'] = 'Show progress bars';

$string['teachercomments'] = 'Teachers can add comments';

$string['teacheredit'] = 'Updates by';

$string['teachermarkundecided'] = 'Teacher has not yet marked this';
$string['teachermarkyes'] = 'Teacher states that you have completed this';
$string['teachermarkno'] = 'Teacher states that you have NOT completed this';

$string['teachernoteditcheck'] = 'Student only';
$string['teacheroverwritecheck'] = 'Teacher only';
$string['teacheralongsidecheck'] = 'Student and teacher';

$string['toggledates'] = 'Toggle dates';

$string['theme'] = 'Checklist display theme';

$string['updatecompletescore'] = 'Save completion grades';
$string['unindentitem'] = 'Unindent item';
$string['updateitem'] = 'Update';
$string['useritemsallowed'] = 'User can add their own items';
$string['useritemsdeleted'] = 'User items deleted';

$string['view'] = 'View checklist';
$string['viewall'] = 'View all students';
$string['viewallcancel'] = 'Cancel';
$string['viewallsave'] = 'Save';

$string['viewsinglereport'] = 'View progress for this user';
$string['viewsingleupdate'] = 'Update progress for this user';

$string['yesnooverride'] = 'Yes, cannot override';
$string['yesoverride'] = 'Yes, can override';
