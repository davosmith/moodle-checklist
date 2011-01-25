<?php

/*

To make this work, you need to open up the following files:

* moodle/lib/datalib.php
Find the function 'add_to_log', then add these lines to the end of it:

    require_once($CFG->dirroot.'/mod/checklist/autoupdate.php');
    checklist_autoupdate($courseid, $module, $action, $cm, $userid);

* moodle/mod/quiz/lib.php
Find the function 'quiz_grade_item_update', then add these lines just before the final 'return' line:

    // Inserted to allow autoupdating items in checklist
    require_once($CFG->dirroot.'/mod/checklist/autoupdate.php');
    checklist_autoupdate_score('quiz', $quiz->course, $quiz->id, $grades);
    // Inserted to allow autoupdating items in checklist

* moodle/mod/forum/lib.php
Find the function 'forum_grade_item_update', then add these lines just before the final 'return' line:

    // Inserted to allow autoupdating items in checklist
    require_once($CFG->dirroot.'/mod/checklist/autoupdate.php');
    checklist_autoupdate_score('forum', $forum->course, $forum->id, $grades);
    // Inserted to allow autoupdating items in checklist


* moodle/mod/assignment/lib.php
Find the function 'assignment_grade_item_update', then add these lines just before the final 'return' line:

    // Inserted to allow autoupdating items in checklist
    require_once($CFG->dirroot.'/mod/checklist/autoupdate.php');
    checklist_autoupdate_score('assignment', $assignment->courseid, $assignment->id, $grades);
    // Inserted to allow autoupdating items in checklist


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
        $items = get_records_sql("SELECT * FROM {$CFG->prefix}checklist_item i WHERE i.checklist IN {$checklistids} AND i.moduleid = $cm AND i.itemoptional < 2 AND i.complete_score = 0");
        // itemoptional - 0: required; 1: optional; 2: heading; 3: disabled; 4: disabled heading
        // not loading defines from mod/checklist/locallib.php to reduce overhead
        if (!$items) {
            return;
        }

        $updategrades = false;
        foreach ($items as $item) {
            $updategrades = checklist_set_check($item->id, $userid, true) || $updategrades;
        }
        if ($updategrades) {
            require_once($CFG->dirroot.'/mod/checklist/lib.php');
            foreach ($checklists as $checklist) {
                checklist_update_grades($checklist, $userid);
            }
        }
    }
}

function checklist_set_check($itemid, $userid, $set) {
    $check = get_record_select('checklist_check', 'item = '.$itemid.' AND userid = '.$userid);
    if ($check) {
        if ($set) {
            if ($check->usertimestamp) {
                return false;
            }
            $check->usertimestamp = time();
            update_record('checklist_check', $check);
            return true;
        } else {
            if (!$check->usertimestamp) {
                return false;
            }
            $check->usertimestamp = 0;
            update_record('checklist_check',$check);
            return true;
        }
    } else {
        if (!$set) {
            return false;
        }
        $check = new stdClass;
        $check->item = $itemid;
        $check->userid = $userid;
        $check->usertimestamp = time();
        $check->teachertimestamp = 0;
        $check->teachermark = 0; // CHECKLIST_TEACHERMARK_UNDECIDED - not loading from mod/checklist/lib.php to reduce overhead
                    
        $check->id = insert_record('checklist_check', $check);
        return true;
    }
}

function checklist_autoupdate_score($modname, $courseid, $instanceid, $grades) {
    global $CFG;

    if (!$grades) {
        return;
    }

    $checklists = get_records_sql("SELECT * FROM {$CFG->prefix}checklist WHERE course = $courseid AND autoupdate > 0 AND autopopulate > 0");
    if (!$checklists) {
        return;
    }

    $checklistids = '('.implode(',', array_keys($checklists)).')';

    $cm = get_coursemodule_from_instance($modname, $instanceid, $courseid);
    $sql = "SELECT * FROM {$CFG->prefix}checklist_item i WHERE i.checklist IN {$checklistids} AND i.moduleid = {$cm->id} AND i.itemoptional < 2 AND i.complete_score > 0";
    $items = get_records_sql($sql);
    // itemoptional - 0: required; 1: optional; 2: heading; 3: disabled; 4: disabled heading
    // not loading defines from mod/checklist/locallib.php to reduce overhead
    if (!$items) {
        return;
    }

    if (!is_array($grades)) {
        $grades = array($grades);
    }

    $updategrades = false;
    foreach ($grades as $grade) {
        foreach ($items as $item) {
            $complete = $grade->rawgrade >= $item->complete_score;
            $updategrades = checklist_set_check($item->id, $grade->userid, $complete) || $updategrades;
        }
    }
    
    if ($updategrades) {
        require_once($CFG->dirroot.'/mod/checklist/lib.php');
        foreach ($checklists as $checklist) {
            foreach ($grades as $grade) {
                checklist_update_grades($checklist, $grade->userid);
            }
        }
    }
}

?>
