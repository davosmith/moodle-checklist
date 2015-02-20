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

defined('MOODLE_INTERNAL') || die();
global $CFG;
$CFG->checklist_autoupdate_use_cron = true;

/*
 Remove the '//' at the start of the next line to output lots of
 helpful information during the cron update. Do NOT use this if you
 have made the core modifications outlined in core_modifications.txt
*/
//define("DEBUG_CHECKLIST_AUTOUPDATE", 1);

function checklist_autoupdate($courseid, $module, $action, $cmid, $userid, $url, $checklists = null) {
    global $CFG, $DB;

    if ($userid == 0) {
        return 0;
    }

    if ($module == 'course') {
        return 0;
    }
    if ($module == 'user') {
        return 0;
    }
    if ($module == 'role') {
        return 0;
    }
    if ($module == 'notes') {
        return 0;
    }
    if ($module == 'calendar') {
        return 0;
    }
    if ($module == 'message') {
        return 0;
    }
    if ($module == 'admin/mnet') {
        return 0;
    }
    if ($module == 'blog') {
        return 0;
    }
    if ($module == 'tag') {
        return 0;
    }
    if ($module == 'blocks/tag_youtube') {
        return 0;
    }
    if ($module == 'login') {
        return 0;
    }
    if ($module == 'library') {
        return 0;
    }
    if ($module == 'upload') {
        return 0;
    }

    if (
        (($module == 'survey') && ($action == 'submit'))
        || (($module == 'quiz') && ($action == 'close attempt'))
        || (($module == 'forum') && (($action == 'add post') || ($action == 'add discussion')))
        || (($module == 'resource') && ($action == 'view'))
        || (($module == 'page') && ($action == 'view'))
        || (($module == 'hotpot') && ($action == 'submit'))
        || (($module == 'wiki') && ($action == 'edit'))
        || (($module == 'checklist') && ($action == 'complete'))
        || (($module == 'choice') && ($action == 'choose'))
        || (($module == 'lams') && ($action == 'view'))
        || (($module == 'scorm') && ($action == 'view'))
        || (($module == 'assignment') && ($action == 'upload'))
        || (($module == 'assign') && ($action == 'submit'))
        || (($module == 'journal') && ($action == 'add entry'))
        || (($module == 'lesson') && ($action == 'end'))
        || (($module == 'realtimequiz') && ($action == 'submit'))
        || (($module == 'workshop') && ($action == 'submit'))
        || (($module == 'glossary') && ($action == 'add entry'))
        || (($module == 'data') && ($action == 'add'))
        || (($module == 'chat') && ($action == 'talk'))
        || (($module == 'feedback') && ($action == 'submit'))
        || (($module == 'questionnaire') && ($action == 'submit'))
    ) {

        if (defined("DEBUG_CHECKLIST_AUTOUPDATE")) {
            mtrace("Possible update needed - courseid: $courseid, module: $module, action: $action, cmid: $cmid, userid: $userid, url: $url");
        }

        if ($cmid == 0) {
            $matches = array();
            if (!preg_match('/id=(\d+)/i', $url, $matches)) {
                return 0;
            }
            $cmid = $matches[1];
        }

        if (!$checklists) {
            $checklists = $DB->get_records_select('checklist',
                                                  'course = ? AND autoupdate > 0',
                                                  array($courseid));

            if (empty($checklists)) {
                if (defined("DEBUG_CHECKLIST_AUTOUPDATE")) {
                    mtrace("No suitable checklists to update in course $courseid");
                }
                return 0;
                // No checklists in this course that are auto-updating.
            }
        }

        if (isset($CFG->enablecompletion) && $CFG->enablecompletion) {
            // Completion is enabled on this site, so we need to check if this module
            // can do completion (and then wait for that to indicate the module is complete).
            $coursecompletion = $DB->get_field('course',
                                               'enablecompletion',
                                               array('id' => $courseid));
            if ($coursecompletion) {
                $cmcompletion = $DB->get_field('course_modules',
                                               'completion',
                                               array('id' => $cmid));
                if ($cmcompletion) {
                    if (defined("DEBUG_CHECKLIST_AUTOUPDATE")) {
                        mtrace("This course module has completion enabled - allow that to control any checklist items");
                    }
                    return 0;
                }
            }
        }

        // Find all checklist_item records which are related to these $checklists which have a moduleid matching $module
        // and any information about checks they might have.
        list($csql, $cparams) = $DB->get_in_or_equal(array_keys($checklists));
        $params = array_merge(array($userid, $cmid), $cparams);

        $sql = "SELECT i.id AS itemid, c.id AS checkid, c.usertimestamp FROM {checklist_item} i ";
        $sql .= "LEFT JOIN {checklist_check} c ON (c.item = i.id AND c.userid = ?) ";
        $sql .= "WHERE i.moduleid = ? AND i.checklist $csql AND i.itemoptional < 2";
        $items = $DB->get_records_sql($sql, $params);
        // Itemoptional - 0: required; 1: optional; 2: heading;
        // not loading defines from mod/checklist/locallib.php to reduce overhead.
        if (empty($items)) {
            if (defined("DEBUG_CHECKLIST_AUTOUPDATE")) {
                mtrace("No checklist items linked to this course module");
            }
            return 0;
        }

        $updatecount = 0;
        foreach ($items as $item) {
            if ($item->checkid) {
                if ($item->usertimestamp) {
                    continue;
                }
                $check = new stdClass;
                $check->id = $item->checkid;
                $check->usertimestamp = time();
                $DB->update_record('checklist_check', $check);
                $updatecount++;
            } else {
                $check = new stdClass;
                $check->item = $item->itemid;
                $check->userid = $userid;
                $check->usertimestamp = time();
                $check->teachertimestamp = 0;
                $check->teachermark = 0;
                // CHECKLIST_TEACHERMARK_UNDECIDED - not loading from mod/checklist/lib.php to reduce overhead.

                $check->id = $DB->insert_record('checklist_check', $check);
                $updatecount++;
            }
        }
        if (defined("DEBUG_CHECKLIST_AUTOUPDATE")) {
            mtrace("$updatecount checklist items updated from this log entry");
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

    if (defined("DEBUG_CHECKLIST_AUTOUPDATE")) {
        mtrace("Completion status change for cmid: $cmid, userid: $userid, newstate: $newstate");
    }

    $sql = "SELECT i.id AS itemid, c.id AS checkid, c.usertimestamp, i.checklist FROM {checklist_item} i ";
    $sql .= "JOIN {checklist} cl ON i.checklist = cl.id ";
    $sql .= "LEFT JOIN {checklist_check} c ON (c.item = i.id AND c.userid = ?) ";
    $sql .= "WHERE cl.autoupdate > 0 AND i.moduleid = ? AND i.itemoptional < 2 ";
    $items = $DB->get_records_sql($sql, array($userid, $cmid));
    // Itemoptional - 0: required; 1: optional; 2: heading;
    // not loading defines from mod/checklist/locallib.php to reduce overhead.
    if (empty($items)) {
        if (defined("DEBUG_CHECKLIST_AUTOUPDATE")) {
            mtrace("No checklist items linked to this course module");
        }
        return 0;
    }

    $newstate = ($newstate == COMPLETION_COMPLETE || $newstate == COMPLETION_COMPLETE_PASS); // Not complete if failed.
    $updatecount = 0;
    $updatechecklists = array();
    foreach ($items as $item) {
        if ($item->checkid) {
            if ($newstate) {
                if ($item->usertimestamp) {
                    continue;
                }
                $check = new stdClass;
                $check->id = $item->checkid;
                $check->usertimestamp = time();
                $DB->update_record('checklist_check', $check);
                $updatechecklists[] = $item->checklist;
                $updatecount++;
            } else {
                if (!$item->usertimestamp) {
                    continue;
                }
                $check = new stdClass;
                $check->id = $item->checkid;
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
            $check->item = $item->itemid;
            $check->userid = $userid;
            $check->usertimestamp = time();
            $check->teachertimestamp = 0;
            $check->teachermark = 0;
            // CHECKLIST_TEACHERMARK_UNDECIDED - not loading from mod/checklist/lib.php to reduce overhead.

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

    if (defined("DEBUG_CHECKLIST_AUTOUPDATE")) {
        mtrace("Updated $updatecount checklist items from this completion status change");
    }

    return $updatecount;
}
