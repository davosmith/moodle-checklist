<?php

defined('MOODLE_INTERNAL') || die();

$CFG->checklist_autoupdate_use_cron = true;

function checklist_autoupdate($courseid, $module, $action, $cmid, $userid, $url) {
    global $CFG, $DB;

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

        if ($cmid == 0) {
            $matches = array();
            if (!preg_match('/id=(\d+)/i', $url, $matches)) {
                return 0;
            }
            $cmid = $matches[1];
        }

        $checklists = $DB->get_records_select('checklist', 'course = ? AND autoupdate > 0', array($courseid));
        if (empty($checklists)) {
            return 0;
            // No checklists in this course that are auto-updating
        }

        if (isset($CFG->enablecompletion) && $CFG->enablecompletion) {
            // Completion is enabled on this site, so we need to check if this module
            // can do completion (and then wait for that to indicate the module is complete)
            $coursecompletion = $DB->get_field('course', 'enablecompletion', array('id'=>$courseid));
            if ($coursecompletion) {
                $cmcompletion = $DB->get_field('course_modules', 'completion', array('id'=>$cmid));
                if ($cmcompletion) {
                    return 0;
                }
            }
        }

        // Find all checklist_item records which are related to these $checklists which have a moduleid matching $module
        // and do not have a related checklist_check record that is filled in
        list($csql, $cparams) = $DB->get_in_or_equal(array_keys($checklists));
        $params = array_merge($cparams, array($cmid));
        $items = $DB->get_records_select('checklist_item', "checklist $csql AND moduleid = ? AND itemoptional < 2", $params);
        // itemoptional - 0: required; 1: optional; 2: heading; 3: disabled; 4: disabled heading
        // not loading defines from mod/checklist/locallib.php to reduce overhead
        if (empty($items)) {
            return 0;
        }

        $updatecount = 0;
        foreach ($items as $item) {
            $check = $DB->get_record('checklist_check', array('item'=>$item->id, 'userid'=>$userid));
            if ($check) {
                if ($check->usertimestamp) {
                    continue;
                }
                $check->usertimestamp = time();
                $DB->update_record('checklist_check', $check);
                $updatecount++;
            } else {
                $check = new stdClass;
                $check->item = $item->id;
                $check->userid = $userid;
                $check->usertimestamp = time();
                $check->teachertimestamp = 0;
                $check->teachermark = 0; // CHECKLIST_TEACHERMARK_UNDECIDED - not loading from mod/checklist/lib.php to reduce overhead
                    
                $check->id = $DB->insert_record('checklist_check', $check);
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

function checklist_completion_autoupdate($cmid, $userid, $newstate) {
    global $DB, $CFG, $USER;

    if ($userid == 0) {
        $userid = $USER->id;
    }

    $items = $DB->get_records_sql('SELECT i.* FROM {checklist_item} i JOIN {checklist} c ON i.checklist = c.id 
              WHERE c.autoupdate > 0 AND i.moduleid = ? AND i.itemoptional < 2', array($cmid));
    // itemoptional - 0: required; 1: optional; 2: heading; 3: disabled; 4: disabled heading
    // not loading defines from mod/checklist/locallib.php to reduce overhead
    if (empty($items)) {
        return 0;
    }

    $updatecount = 0;
    $updatechecklists = array();
    foreach ($items as $item) {
        $check = $DB->get_record('checklist_check', array('item'=>$item->id, 'userid'=>$userid));
        if ($check) {
            if ($newstate) {
                if ($check->usertimestamp) {
                    continue;
                }
                $check->usertimestamp = time();
                $DB->update_record('checklist_check', $check);
                $updatechecklists[] = $item->checklist;
                $updatecount++;
            } else {
                if (!$check->usertimestamp) {
                    continue;
                }
                $check->usertimestamp = 0;
                $DB->update_record('checklist_check', $check);
                $updatechecklists[] = $item->checklist;
                $updatecount++;
            }
        } else {
            if (!$newstate) {
                continue;
            }
            $check = new stdClass;
            $check->item = $item->id;
            $check->userid = $userid;
            $check->usertimestamp = time();
            $check->teachertimestamp = 0;
            $check->teachermark = 0; // CHECKLIST_TEACHERMARK_UNDECIDED - not loading from mod/checklist/lib.php to reduce overhead
                    
            $check->id = $DB->insert_record('checklist_check', $check);
            $updatechecklists[] = $item->checklist;
            $updatecount++;
        }
    }
    if (!empty($updatechecklists)) {
        $updatechecklists = array_unique($updatechecklists);
        list($csql, $cparams) = $DB->get_in_or_equal($updatechecklists);
        $checklists = $DB->get_records_select('checklist', 'id '.$csql, $cparams);
        require_once($CFG->dirroot.'/mod/checklist/lib.php');
        foreach ($checklists as $checklist) {
            checklist_update_grades($checklist, $userid);
        }
    }

    return $updatecount;
}

?>