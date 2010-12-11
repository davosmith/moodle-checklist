<html><head><title>Updating checklist</title></head>
<body>
<h1>Attempting to update checklist from past log entries</h1>
<?php

// This will force an update of this checklist, based on the logs

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');

$id = required_param('id', PARAM_INT);

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

require_capability('mod/checklist:viewreports', $context);

if (!$checklist->autoupdate || !$checklist->autopopulate) {
    error("This is not an auto-updating checklist");
}

// Get a list of all the checklist items with a module linked to them
$sql = "SELECT cm.id AS cmid, m.name AS mod_name, i.id AS itemid
        FROM {$CFG->prefix}modules m, {$CFG->prefix}course_modules cm, {$CFG->prefix}checklist_item i
        WHERE m.id = cm.module AND cm.id = i.moduleid AND i.moduleid > 0 AND i.checklist = {$cm->instance}";

$items = get_records_sql($sql);
if (!$items) {
    error("No suitable items in this checklist to update");
}

if ($users = get_users_by_capability($context, 'mod/checklist:updateown', 'u.id', '', '', '', '', '', false)) {
    $users = implode(',',array_keys($users));
}

if (!$users) {
    error("No users to update");
}

$updategrades = false;
foreach ($items as $item) {
    $logaction = '';
    $logaction2 = false;

    switch($item->mod_name) {
    case 'survey':
        $logaction = 'submit';
        break;
    case 'quiz':
        $logaction = 'close attempt';
        break;
    case 'forum':
        $logaction = 'add post';
        $logaction2 = 'add discussion';
        break;
    case 'resource':
        $logaction = 'view';
        break;
    case 'hotpot':
        $logaction = 'submit';
        break;
    case 'wiki':
        $logaction = 'edit';
        break;
    case 'checklist':
        $logaction = 'complete';
        break;
    case 'choice':
        $logaction = 'choose';
        break;
    case 'lams':
        $logaction = 'view';
        break;
    case 'scorm':
        $logaction = 'view';
        break;
    case 'assignment':
        $logaction = 'upload';
        break;
    case 'journal':
        $logaction = 'add entry';
        break;
    case 'lesson':
        $logaction = 'end';
        break;
    case 'realtimequiz':
        $logaction = 'submit';
        break;
    case 'workshop':
        $logaction = 'submit';
        break;
    case 'glossary':
        $logaction = 'add entry';
        break;
    case 'data':
        $logaction = 'add';
        break;
    case 'chat':
        $logaction = 'talk';
        break;
    case 'feedback':
        $logaction = 'submit';
        break;
    default:
        continue 2;
        break;
    }

    $sql = 'SELECT DISTINCT userid ';
    $sql .= "FROM {$CFG->prefix}log ";
    $sql .= "WHERE cmid = {$item->cmid} AND (action = '{$logaction}'";
    if ($logaction2) {
        $sql .= " OR action = '{$logaction2}'";
    }
    $sql .= ") AND userid IN ($users)";
    $log_entries = get_records_sql($sql);

    if (!$log_entries) {
        continue;
    }

    foreach ($log_entries as $entry) {
        //echo "User: {$entry->userid} has completed '{$item->mod_name}' with cmid {$item->cmid}, so updating checklist item {$item->itemid}<br />\n";

        $check = get_record('checklist_check', 'item', $item->itemid, 'userid', $entry->userid);
        if ($check) {
            if ($check->usertimestamp) {
                continue;
            }
            $check->usertimestamp = time();
            update_record('checklist_check', $check);
            $updategrades = true;
        } else {
            $check = new stdClass;
            $check->item = $item->itemid;
            $check->userid = $entry->userid;
            $check->usertimestamp = time();
            $check->teachertimestamp = 0;
            $check->teachermark = 0; // CHECKLIST_TEACHERMARK_UNDECIDED - not loading from mod/checklist/lib.php to reduce overhead
                    
            $check->id = insert_record('checklist_check', $check);
            $updategrades = true;
        }
    }
}


if ($updategrades) {
    require_once($CFG->dirroot.'/mod/checklist/lib.php');
    checklist_update_grades($checklist);

    echo "<p>The checklist '{$checklist->name}' has been updated</p>";

} else {

    echo "<p>The checklist '{$checklist->name}' did not need updating</p>";
}

?>
</body>
</html>
