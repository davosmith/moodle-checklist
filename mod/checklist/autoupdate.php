<?php

$CFG->checklist_autoupdate_use_cron = true;

function checklist_autoupdate($courseid, $module, $action, $cm, $userid) {
    global $CFG;

    if ($module == 'course') { return 0; }
    if ($module == 'user') { return 0;  }
    if ($module == 'role') { return 0;  }
    if ($module == 'notes') { return 0;  }
    if ($module == 'calendar') { return 0; }
    if ($module == 'message') { return 0; }
    if ($module == 'admin/mnet') { return 0; }
    if ($module == 'blog') { return 0; }
    if ($module == 'tag') { return 0; }
    if ($module == 'blocks/tag_youtube') { return 0; }
    if ($module == 'login') { return 0; }
    if ($module == 'library') { return 0; }
    if ($module == 'upload') { return 0; }

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
            return 0;
        }

        // Find all checklist_item records which are related to these $checklists which have a moduleid matching $module
        // and do not have a related checklist_check record that is filled in
        $checklistids = '('.implode(',', array_keys($checklists)).')';
        $items = get_records_sql("SELECT * FROM {$CFG->prefix}checklist_item i WHERE i.checklist IN {$checklistids} AND i.moduleid = $cm AND i.itemoptional < 2 AND i.complete_score = 0");
        // itemoptional - 0: required; 1: optional; 2: heading;
        // not loading defines from mod/checklist/locallib.php to reduce overhead
        if (!$items) {
            return 0;
        }

        $updatecount = 0;
        foreach ($items as $item) {
            if (checklist_set_check($item->id, $userid, true)) {
                $updatecount++;
            }
        }
        if ($updatecount) {
            require_once($CFG->dirroot.'/mod/checklist/lib.php');
            foreach ($checklists as $checklist) {
                checklist_update_grades($checklist, $userid);
            }
            return $updatecount;
        }
    }

    return 0;
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
        return 0;
    }

    $checklists = get_records_sql("SELECT * FROM {$CFG->prefix}checklist WHERE course = $courseid AND autoupdate > 0 AND autopopulate > 0");
    if (!$checklists) {
        return 0;
    }

    $checklistids = '('.implode(',', array_keys($checklists)).')';

    $cm = get_coursemodule_from_instance($modname, $instanceid, $courseid);
    $sql = "SELECT * FROM {$CFG->prefix}checklist_item i WHERE i.checklist IN {$checklistids} AND i.moduleid = {$cm->id} AND i.itemoptional < 2 AND i.complete_score > 0";
    $items = get_records_sql($sql);
    // itemoptional - 0: required; 1: optional; 2: heading;
    // not loading defines from mod/checklist/locallib.php to reduce overhead
    if (!$items) {
        return 0;
    }

    if (!is_array($grades)) {
        $grades = array($grades);
    }

    $updatecount = 0;
    foreach ($grades as $grade) {
        foreach ($items as $item) {
            $complete = $grade->rawgrade >= $item->complete_score;
            if (checklist_set_check($item->id, $grade->userid, $complete)) {
                $updatecount++;
            }
        }
    }

    if ($updatecount) {
        require_once($CFG->dirroot.'/mod/checklist/lib.php');
        foreach ($checklists as $checklist) {
            foreach ($grades as $grade) {
                checklist_update_grades($checklist, $grade->userid);
            }
        }
    }

    return $updatecount;
}

?>
