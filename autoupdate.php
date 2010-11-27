<?php

/*

To make this work, you need to open up the following file:
moodle/lib/datalib.php

Find the function 'add_to_log', then add these lines to the end of it:

    require_once($CFG->dirroot.'/mod/checklist/autoupdate.php');
    checklist_autoupdate($courseid, $module, $action, $cm, $userid);

WARNING: This will slow your Moodle site down very slightly.
However, the difference is unlikely to be noticable.

*/

function checklist_autoupdate($courseid, $module, $action, $cm, $userid) {
    global $CFG;

    if ($module == 'course') { return; }
    if ($module == 'user') { return;  }
    if ($module == 'role') { return;  }
    if ($module == 'notes') { return;  }
    if ($module == 'calendar') { return; }
    if ($module == 'message') { return; }
    if ($module == 'admin/mnet') { return; }
    if ($module == 'blog') { return; }
    if ($module == 'tag') { return; }
    if ($module == 'blocks/tag_youtube') { return; }
    if ($module == 'login') { return; }
    if ($module == 'library') { return; }
    if ($module == 'upload') { return; }

    
    if (
        (($module == 'survey') && ($action == 'submit'))
        || (($module == 'quiz') && ($action == 'close attempt'))
        || (($module == 'forum') && (($action == 'add post')||($action == 'add discussion')))
        || (($module == 'resource') && ($action == 'view'))
        || (($module == 'hotpot') && ($action == 'submit'))
        || (($module == 'wiki') && ($action == 'edit'))
        || (($module == 'checklist') && ($action == 'complete'))
        || (($module == 'choice') && ($action == 'choose'))
        || (($module == 'lams') && ($action == 'view'))
        || (($module == 'scorm') && ($action == 'view'))
        || (($module == 'assignment') && ($action == 'upload'))
        || (($module == 'journal') && ($action == 'add entry'))
        || (($module == 'lesson') && ($action == 'end'))
        || (($module == 'realtimequiz') && ($action == 'submit'))
        || (($module == 'workshop') && ($action == 'submit'))
        || (($module == 'glossary') && ($action == 'add entry'))
        || (($module == 'data') && ($action == 'add'))
        || (($module == 'chat') && ($action == 'talk'))
        || (($module == 'feedback') && ($action == 'submit'))
        ) {

        $checklists = get_records_sql("SELECT * FROM {$CFG->prefix}checklist WHERE course = $courseid AND autoupdate > 0");
        if (!$checklists) {
            return;
        }

        // Find all checklist_item records which are related to these $checklists which have a moduleid matching $module
        // and do not have a related checklist_check record that is filled in
        $checklistids = '('.implode(',', array_keys($checklists)).')';
        $items = get_records_sql("SELECT * FROM {$CFG->prefix}checklist_item i WHERE i.checklist IN {$checklistids} AND i.moduleid = $cm AND i.itemoptional < 2");
        // itemoptional - 0: required; 1: optional; 2: heading; 3: disabled; 4: disabled heading
        // not loading defines from mod/checklist/locallib.php to reduce overhead
        if (!$items) {
            return;
        }

        $updategrades = false;
        foreach ($items as $item) {
            $check = get_record_select('checklist_check', 'item = '.$item->id.' AND userid = '.$userid);
            if ($check) {
                if ($check->usertimestamp) {
                    continue;
                }
                $check->usertimestamp = time();
                update_record('checklist_check', $check);
                $updategrades = true;
            } else {
                $check = new stdClass;
                $check->item = $item->id;
                $check->userid = $userid;
                $check->usertimestamp = time();
                $check->teachertimestamp = 0;
                $check->teachermark = 0; // CHECKLIST_TEACHERMARK_UNDECIDED - not loading from mod/checklist/lib.php to reduce overhead
                    
                $check->id = insert_record('checklist_check', $check);
                $updategrades = true;
            }
        }
        if ($updategrades) {
            require_once($CFG->dirroot.'/mod/checklist/lib.php');
            foreach ($checklists as $checklist) {
                checklist_update_grades($checklist, $userid);
            }
        }
    }
}

?>