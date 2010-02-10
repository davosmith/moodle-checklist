<?php
/**
 * Sets up tabs used by the checklist pages, based on user capabilities
 *
 * Heavily based on the file tabs.php found in the quiz module (Moodle 1.9.7)
 * 
 * @author David Smith (based on Tim Hunt and others)
 * @package checklist
 */

if (empty($checklist)) {
    error('You cannot call this script in that way');
}
if (!isset($currenttab)) {
    $currenttab = '';
}
if (!isset($cm)) {
    $cm = get_coursemodule_from_instance('checklist', $checklist->id);
}

$context = get_context_instance(CONTEXT_MODULE, $cm->id);

$tabs = array();
$row = array();
$inactive = array();
$activated = array();

if (has_capability('mod/checklist:updateown', $context)) {
    $row[] = new tabobject('view', "$CFG->wwwroot/mod/checklist/view.php?checklist=$checklist->id", get_string('view', 'checklist'));
} elseif (has_capability('mod/checklist:preview', $context)) {
    $row[] = new tabobject('preview', "$CFG->wwwroot/mod/checklist/view.php?checklist=$checklist->id", get_string('preview', 'checklist'));
}
if (has_capability('mod/checklist:viewreports', $context)) {
    $row[] = new tabobject('report', "$CFG->wwwroot/mod/checklist/report.php?checklist=$checklist->id", get_string('report', 'checklist'));
}
if (has_capability('mod/checklist:edit', $context)) {
    $row[] = new tabobject('edit', "$CFG->wwwroot/mod/checklist/edit.php?checklist=$checklist->id", get_string('edit', 'checklist'));
}

if ($currenttab == 'view' && count($row) == 1) {
    // No tabs for students
} else {
    $tabs[] = $row;
}

if ($currenttab == 'report') {
    $activated[] = 'report';
}

if ($currenttab == 'edit') {
    $activated[] = 'edit';

    if (!$items) {
        $inactive = array('view', 'report', 'preview');
    }
}

if ($currenttab == 'preview') {
    $activated[] = 'preview';
}

print_tabs($tabs, $currenttab, $inactive, $activated);
    
?>