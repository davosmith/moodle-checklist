<?php

/**
 * Library of functions and constants for module checklist
 * This file should have two well differenced parts:
 *   - All the core Moodle functions, neeeded to allow
 *     the module to work integrated in Moodle.
 *   - All the checklist specific functions, needed
 *     to implement all the module logic. Please, note
 *     that, if the module become complex and this lib
 *     grows a lot, it's HIGHLY recommended to move all
 *     these module specific functions to a new php file,
 *     called "locallib.php" (see forum, quiz...). This will
 *     help to save some memory when Moodle is performing
 *     actions across all modules.
 */

define("CHECKLIST_TEACHERMARK_NO", 2);
define("CHECKLIST_TEACHERMARK_YES", 1);
define("CHECKLIST_TEACHERMARK_UNDECIDED", 0);

define("CHECKLIST_MARKING_STUDENT", 0);
define("CHECKLIST_MARKING_TEACHER", 1);
define("CHECKLIST_MARKING_BOTH", 2);

define("CHECKLIST_AUTOUPDATE_NO", 0);
define("CHECKLIST_AUTOUPDATE_YES", 2);
define("CHECKLIST_AUTOUPDATE_YES_OVERRIDE", 1);

define("CHECKLIST_AUTOPOPULATE_NO", 0);
define("CHECKLIST_AUTOPOPULATE_SECTION", 2);
define("CHECKLIST_AUTOPOPULATE_COURSE", 1);

define("CHECKLIST_MAX_INDENT", 10);

require_once(dirname(__FILE__).'/locallib.php');

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @param object $checklist An object from the form in mod_form.php
 * @return int The id of the newly inserted checklist record
 */
function checklist_add_instance($checklist) {

    $checklist->timecreated = time();
    $returnid = insert_record('checklist', $checklist);

    $checklist = stripslashes_recursive($checklist);
    $checklist->id = $returnid;
    checklist_grade_item_update($checklist);

    return $returnid;
}


/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @param object $checklist An object from the form in mod_form.php
 * @return boolean Success/Fail
 */
function checklist_update_instance($checklist) {

    $checklist->timemodified = time();
    $checklist->id = $checklist->instance;

    $newmax = $checklist->maxgrade;
    $oldmax = get_field('checklist','maxgrade','id',$checklist->id);

    $returnid = update_record('checklist', $checklist);

    // Add or remove all calendar events, as needed
    $course = get_record('course', 'id', $checklist->course);
    $cm = get_coursemodule_from_instance('checklist', $checklist->id, $course->id);
    $chk = new checklist_class($cm->id, 0, $checklist, $cm, $course);
    $chk->setallevents();

    $checklist = stripslashes_recursive($checklist);
    checklist_grade_item_update($checklist);

    if ($newmax != $oldmax) {
        checklist_update_grades($checklist);
    }

    return $returnid;
}


/**
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id Id of the module instance
 * @return boolean Success/Failure
 */
function checklist_delete_instance($id) {

    if (! $checklist = get_record('checklist', 'id', $id)) {
        return false;
    }

    // Remove all calendar events
    if ($checklist->duedatesoncalendar) {
        $checklist->duedatesoncalendar = false;
        $course = get_record('course', 'id', $checklist->course);
        $cm = get_coursemodule_from_instance('checklist', $checklist->id, $course->id);
        if ($cm) { // Should not fail be false, but check, just in case...
            $chk = new checklist_class($cm->id, 0, $checklist, $cm, $course);
            $chk->setallevents();
        }
    }

    $result = true;

    $items = get_records('checklist_item', 'checklist', $checklist->id, '', 'id');
    if ($items) {
        $items = implode(',',array_keys($items));
        $result = delete_records_select('checklist_check', 'item IN ('.$items.')');
        $result = $result && delete_records_select('checklist_comment', 'itemid IN ('.$items.')');

        if ($result) {
            $result = delete_records('checklist_item', 'checklist', $checklist->id);
        }
    }
    if ($result && !delete_records('checklist', 'id', $checklist->id)) {
        $result = false;
    }

    checklist_grade_item_delete($checklist);

    return $result;
}

function checklist_update_all_grades() {
    $checklists = get_records('checklist');
    if ($checklists) {
        foreach ($checklists as $checklist) {
            checklist_update_grades($checklist);
        }
    }
}

function checklist_update_grades($checklist, $userid=0) {
    global $CFG;

    if ($CFG->version < 2007101500) {
        // No gradelib for pre 1.9
        return;
    }

    $items = get_records_select('checklist_item',"checklist = $checklist->id AND userid = 0 AND itemoptional = ".CHECKLIST_OPTIONAL_NO.' AND hidden = '.CHECKLIST_HIDDEN_NO, ''. 'id');
    if (!$items) {
        return; // I should probably do something really clever here (in case there where already grades,
        // but this is a silly use of the checklist, so I'm just going to ignore it
    }
    if (!$course = get_record('course', 'id', $checklist->course)) {
        return;
    }
    if (!$cm = get_coursemodule_from_instance('checklist', $checklist->id, $course->id)) {
        return;
    }

    $checkgroupings = false;
    if ($checklist->autopopulate) {
        foreach ($items as $item) {
            if ($item->grouping) {
                $checkgroupings = true;
                break;
            }
        }
    }

    $scale = $checklist->maxgrade;
    if ($checklist->teacheredit == CHECKLIST_MARKING_STUDENT) {
        $date = ', MAX(c.usertimestamp) AS datesubmitted';
        $where = 'c.usertimestamp > 0';
    } else {
        $date = ', MAX(c.teachertimestamp) AS dategraded';
        $where = 'c.teachermark = '.CHECKLIST_TEACHERMARK_YES;
    }

    // Autopopulate is on and checklist has at least one item with a grouping
    if ($checkgroupings) {
        if ($userid) {
            $users = get_records('user', 'id', $userid, null, 'id, firstname, lastname');
        } else {
            $context = get_context_instance(CONTEXT_MODULE, $cm->id);
            if (!$users = get_users_by_capability($context, 'mod/checklist:updateown', 'u.id', '', '', '', '', '', false)) {
                return;
            }
        }

        $grades = array();

        // With groupings, need to update each user individually (as each has different groupings)
        foreach ($users as $userid => $user) {
            $groupings = checklist_class::get_user_groupings($userid, $course->id);

            $total = 0;
            $itemlist = '';
            foreach ($items as $item) {
                if ($item->grouping) {
                    if (!in_array($item->grouping, $groupings)) {
                        continue;
                    }
                }
                $itemlist .= $item->id.',';
                $total++;
            }

            if (!$total) { // No items - set score to 0
                $ugrade = new stdClass;
                $ugrade->userid = $userid;
                $ugrade->rawgrade = 0;
                $ugrade->date = time();

            } else {
                $itemlist = substr($itemlist, 0, -1);

                $sql = 'SELECT '.$userid.' AS userid, (SUM(CASE WHEN '.$where.' THEN 1 ELSE 0 END) * '.$scale.' / '.$total.') AS rawgrade'.$date;
                $sql .= " FROM {$CFG->prefix}checklist_check c ";
                $sql .= " WHERE c.item IN ($itemlist)";
                $sql .= ' AND c.userid = '.$userid;

                $ugrade = get_record_sql($sql);
                if (!$ugrade) {
                    $ugrade = new stdClass;
                    $ugrade->userid = $userid;
                    $ugrade->rawgrade = 0;
                    $ugrade->date = time();
                }
            }

            // Needed when sending emails
            $ugrade->firstname = $user->firstname;
            $ugrade->lastname = $user->lastname;

            $grades[$userid] = $ugrade;
        }

    } else {
        // No need to check groupings, so update all student grades at once

        if ($userid) {
            $users = $userid;
        } else {
            $context = get_context_instance(CONTEXT_MODULE, $cm->id);
            if (!$users = get_users_by_capability($context, 'mod/checklist:updateown', 'u.id', '', '', '', '', '', false)) {
                return;
            }
            $users = implode(',',array_keys($users));
        }

        $total = count($items);

        $itemlist = implode(',',array_keys($items));

        $sql = 'SELECT u.id AS userid, (SUM(CASE WHEN '.$where.' THEN 1 ELSE 0 END) * '.$scale.' / '.$total.') AS rawgrade'.$date;
        $sql .= ', u.firstname, u.lastname ';
        $sql .= " FROM {$CFG->prefix}user u LEFT JOIN {$CFG->prefix}checklist_check c ON u.id = c.userid";
        $sql .= " WHERE c.item IN ($itemlist)";
        $sql .= ' AND u.id IN ('.$users.')';
        $sql .= ' GROUP BY u.id, u.firstname, u.lastname';

        $grades = get_records_sql($sql);

    }

    if ($grades) {
        foreach ($grades as $grade) {
            // Log completion of checklist
            if ($grade->rawgrade == $scale) {
                if ($checklist->emailoncomplete) {
                    $timelimit = time() - 1 * 60 * 60; // Do not send another email if this checklist was already 'completed' in the last hour
                    $filter = "l.time > $timelimit AND l.cmid = $cm->id AND l.userid = $grade->userid";
                    $logs = get_logs($filter, '', 1, 1, $logcount);
                    if ($logcount == 0) {
                        if (!isset($context)) {
                            $context = get_context_instance(CONTEXT_MODULE, $cm->id);
                        }
                        if ($recipients = get_users_by_capability($context, 'mod/checklist:emailoncomplete', 'u.*', '', '', '', '', '', false)) {
                            foreach ($recipients as $recipient) {
                                $details = new stdClass;
                                $details->user = fullname($grade);
                                $details->checklist = s($checklist->name);

                                $subj = get_string('emailoncompletesubject', 'checklist', $details);
                                $content = get_string('emailoncompletebody', 'checklist', $details);
                                $content .= $CFG->wwwroot.'/mod/checklist/view.php?id='.$cm->id;

                                // $grade contains firstname & lastname, so can count as 'sender'
                                email_to_user($recipient, $grade, $subj, $content, '', '', '', false);
                            }
                        }
                    }
                }
                add_to_log($checklist->course, 'checklist', 'complete', "view.php?id={$cm->id}", addslashes($checklist->name), $cm->id, $grade->userid);
            }
        }
    }
    checklist_grade_item_update($checklist, $grades);
}

function checklist_grade_item_delete($checklist) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');
    if (!isset($checklist->courseid)) {
        $checklist->courseid = $checklist->course;
    }

    return grade_update('mod/checklist', $checklist->courseid, 'mod', 'checklist', $checklist->id, 0, NULL, array('deleted'=>1));
}

function checklist_grade_item_update($checklist, $grades=NULL) {
    global $CFG;
    if (!function_exists('grade_update')) { //workaround for buggy PHP versions
        require_once($CFG->libdir.'/gradelib.php');
    }

    if (!isset($checklist->courseid)) {
        $checklist->courseid = $checklist->course;
    }

    $params = array('itemname'=>$checklist->name);
    $params['gradetype'] = GRADE_TYPE_VALUE;
    $params['grademax']  = $checklist->maxgrade;
    $params['grademin']  = 0;

    if ($grades  === 'reset') {
        $params['reset'] = true;
        $grades = NULL;
    }

    return grade_update('mod/checklist', $checklist->courseid, 'mod', 'checklist', $checklist->id, 0, $grades, $params);
}


/**
 * Return a small object with summary information about what a
 * user has done with a given particular instance of this module
 * Used for user activity reports.
 * $return->time = the time they did it
 * $return->info = a short text description
 *
 * @return null
 * @todo Finish documenting this function
 */
function checklist_user_outline($course, $user, $mod, $checklist) {
    global $CFG;

    $groupings_sel = '';
    if ($checklist->autopopulate) {
        $groupings = checklist_class::get_user_groupings($user->id, $checklist->course);
        $groupings[] = 0;
        $groupings_sel = ' AND grouping IN ('.implode(',',$groupings).') ';
    }
    $items = get_records_select('checklist_item',"checklist = $checklist->id AND userid = 0 AND itemoptional = ".CHECKLIST_OPTIONAL_NO.' AND hidden = '.CHECKLIST_HIDDEN_NO.$groupings_sel, '', 'id');
    if (!$items) {
        return null;
    }

    $total = count($items);
    $itemlist = implode(',',array_keys($items));

    $sql = "userid = {$user->id} AND item IN ($itemlist) AND ";
    if ($checklist->teacheredit == CHECKLIST_MARKING_STUDENT) {
        $sql .= 'usertimestamp > 0';
        $order = 'usertimestamp DESC';
    } else {
        $sql .= 'teachermark = '.CHECKLIST_TEACHERMARK_YES;
        $order = 'teachertimestamp DESC';
    }
    $checks = get_records_select('checklist_check', $sql, $order);

    $return = null;
    if ($checks) {
        $return = new stdClass;

        $ticked = count($checks);
        $check = reset($checks);
        if ($checklist->teacheredit == CHECKLIST_MARKING_STUDENT) {
            $return->time = $check->usertimestamp;
        } else {
            $return->time = $check->teachertimestamp;
        }
        $percent = sprintf('%0d',($ticked * 100) / $total);
        $return->info = get_string('progress','checklist').': '.$ticked.'/'.$total.' ('.$percent.'%)';
    }

    return $return;
}


/**
 * Print a detailed representation of what a user has done with
 * a given particular instance of this module, for user activity reports.
 *
 * @return boolean
 * @todo Finish documenting this function
 */
function checklist_user_complete($course, $user, $mod, $checklist) {
    $chk = new checklist_class($mod->id, $user->id, $checklist, $mod, $course);

    $chk->user_complete();

    return true;
}


/**
 * Given a course and a time, this module should find recent activity
 * that has occurred in newmodule activities and print it out.
 * Return true if there was output, or false is there was none.
 *
 * @return boolean
 * @todo Finish documenting this function
 */
function checklist_print_recent_activity($course, $isteacher, $timestart) {
    return false;  //  True if anything was printed, otherwise false
}


function checklist_print_overview($courses, &$htmlarray) {
    global $USER, $CFG;

    if (empty($courses) || !is_array($courses) || count($courses) == 0) {
        return array();
    }

    if (!$checklists = get_all_instances_in_courses('checklist',$courses)) {
        return;
    }

    $strchecklist = get_string('modulename','checklist');

    foreach ($checklists as $key => $checklist) {
        $show_all = true;
        if ($checklist->teacheredit == CHECKLIST_MARKING_STUDENT) {
            $context = get_context_instance(CONTEXT_MODULE, $checklist->coursemodule);
            $show_all = !has_capability('mod/checklist:updateown', $context);
        }

        // Do now worry about hidden items / groupings as automatic items cannot have dates (and manual items cannot be hidden / have groupings)
        if ($show_all) { // Show all items whether or not they are checked off (as this user is unable to check them off)
            $date_items = get_records_select('checklist_item','checklist = '.$checklist->id.' AND duetime > 0','duetime');
        } else { // Show only items that have not been checked off
            $date_items = get_records_sql('SELECT i.* FROM '.$CFG->prefix.'checklist_item i JOIN '.$CFG->prefix.'checklist_check c ON c.item = i.id '.
                                          'WHERE i.checklist = '.$checklist->id.' AND i.duetime > 0 AND c.userid = '.$USER->id.' AND usertimestamp = 0 '.
                                          'ORDER BY i.duetime');
        }

        $str = '<div class="checklist overview"><div class="name">'.$strchecklist.': '.
            '<a title="'.$strchecklist.'" href="'.$CFG->wwwroot.'/mod/checklist/view.php?id='.$checklist->coursemodule.'">'.$checklist->name.'</a></div>';
        $str .= '<div class="info">'.checklist_class::print_user_progressbar($checklist->id, $USER->id, '300px', true, true).'</div>';
        if ($date_items) {
            foreach ($date_items as $item) {
                $str .= '<div class="info">'.$item->displaytext.': ';
                if ($item->duetime > time()) {
                    $str .= '<span class="itemdue">';
                } else {
                    $str .= '<span class="itemoverdue">';
                }
                $str .= date('j M Y', $item->duetime).'</span></div>';
            }
        }
        $str .= '</div>';
        if (empty($htmlarray[$checklist->course]['checklist'])) {
            $htmlarray[$checklist->course]['checklist'] = $str;
        } else {
            $htmlarray[$checklist->course]['checklist'] .= $str;
        }
    }
}

/**
 * Function to be run periodically according to the moodle cron
 * This function searches for things that need to be done, such
 * as sending out mail, toggling flags etc ...
 *
 * @return boolean
 * @todo Finish documenting this function
 **/
function checklist_cron () {
    global $CFG;

    $lastcron = get_field('modules', 'lastcron', 'name', 'checklist');
    if (!$lastcron) {
        // First time run - checklists will take care of any updates before now
        return true;
    }

    require_once($CFG->dirroot.'/mod/checklist/autoupdate.php');
    if (!$CFG->checklist_autoupdate_use_cron) {
        return true;
    }

    $lastlogtime = $lastcron - 5; // Subtract 5 seconds just in case a log slipped through during the last cron update

    // Find all autoupdating checklists
    $checklists = get_records_select('checklist', 'autopopulate > 0 AND autoupdate > 0');
    if (!$checklists) {
        // No checklists to update
        return true;
    }

    // Match up these checklists to the courses they are in
    $courses = array();
    foreach ($checklists as $checklist) {
        if (array_key_exists($checklist->course, $courses)) {
            $courses[$checklist->course][$checklist->id] = $checklist;
        } else {
            $courses[$checklist->course] = array($checklist->id => $checklist);
        }
    }
    $courseids = implode(',', array_keys($courses));

    // Find all the log updates for these courses
    $logupdate = 0;
    $logs = get_logs("l.time >= $lastlogtime AND l.course IN ($courseids) AND cmid > 0", 'l.time ASC', '', '', $totalcount);
    if ($logs) {
        foreach ($logs as $log) {
            $logupdate += checklist_autoupdate($log->course, $log->module, $log->action, $log->cmid, $log->userid, $courses[$log->course]);
        }
    }

    if ($logupdate) {
        mtrace(" Updated $logupdate checkmark(s) from log changes");
    }

    $sql = 'SELECT g.id, i.itemmodule, i.courseid, i.iteminstance, g.rawgrade, g.userid ';
    $sql .= "FROM {$CFG->prefix}grade_items i, {$CFG->prefix}grade_grades g ";
    $sql .= "WHERE g.itemid = i.id AND g.timemodified >= $lastlogtime ";
    $sql .= 'AND i.itemmodule IN ("quiz","assignment","forum") ';
    $sql .= "AND i.courseid IN ($courseids) ";

    $gradeupdate = 0;
    $grades = get_records_sql($sql);
    if ($grades) {
        foreach ($grades as $grade) {
            $gradeupdate += checklist_autoupdate_score($grade->itemmodule, $grade->courseid, $grade->iteminstance, $grade, $courses[$grade->courseid]);
        }
    }

    if ($gradeupdate) {
        mtrace("Updated $gradeupdate checkmark(s) from grade changes");
    }

    return true;
}


/**
 * Must return an array of user records (all data) who are participants
 * for a given instance of newmodule. Must include every user involved
 * in the instance, independient of his role (student, teacher, admin...)
 * See other modules as example.
 *
 * @param int $newmoduleid ID of an instance of this module
 * @return mixed boolean/array of students
 */
function checklist_get_participants($checklistid) {
    global $CFG;

    $sql = "SELECT DISTINCT u.id, u.id FROM {$CFG->prefix}user u, {$CFG->prefix}checklist_item i, {$CFG->prefix}checklist_check c ";
    $sql .= "WHERE i.checklist = '$checklistid' AND ((c.item = i.id AND c.userid = u.id) OR (i.userid = u.id))";

    $return = get_records_sql($sql);

    return $return;
}


/**
 * This function returns if a scale is being used by one checklist
 * if it has support for grading and scales. Commented code should be
 * modified if necessary. See forum, glossary or journal modules
 * as reference.
 *
 * @param int $newmoduleid ID of an instance of this module
 * @return mixed
 * @todo Finish documenting this function
 */
function checklist_scale_used($checklistid, $scaleid) {
    return false;
}


/**
 * Checks if scale is being used by any instance of checklist.
 * This function was added in 1.9
 *
 * This is used to find out if scale used anywhere
 * @param $scaleid int
 * @return boolean True if the scale is used by any checklist
 */
function checklist_scale_used_anywhere($scaleid) {
    return false;
}


/**
 * Execute post-install custom actions for the module
 * This function was added in 1.9
 *
 * @return boolean true if success, false on error
 */
function checklist_install() {
    return true;
}


/**
 * Execute post-uninstall custom actions for the module
 * This function was added in 1.9
 *
 * @return boolean true if success, false on error
 */
function checklist_uninstall() {
    return true;
}

function checklist_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'checklistheader', get_string('modulenameplural', 'checklist'));
    $mform->addElement('checkbox', 'reset_checklist_progress', get_string('resetchecklistprogress','checklist'));
}

function checklist_reset_course_form_defaults($course) {
    return array('reset_checklist_progress' => 1);
}

function checklist_reset_userdata($data) {
    global $CFG;

    $status = array();
    $component = get_string('modulenameplural', 'checklist');
    $typestr = get_string('resetchecklistprogress', 'checklist');
    $status[] = array('component'=>$component, 'item'=>$typestr, 'error'=>false);

    if (!empty($data->reset_checklist_progress)) {
        $checklists = get_records('checklist', 'course', $data->courseid);
        if (!$checklists) {
            return $status;
        }
        $checklistkeys = implode(',',array_keys($checklists));
        $items = get_records_select('checklist_item', 'checklist IN ('.$checklistkeys.')');
        if (!$items) {
            return $status;
        }
        $items = implode(',',array_keys($items));

        delete_records_select('checklist_check', 'item IN ('.$items.')');

        $sql = 'checklist IN ('.$checklistkeys.') AND userid != 0';
        delete_records_select('checklist_item', $sql);

        // Reset the grades
        foreach ($checklists as $checklist) {
            checklist_grade_item_update($checklist, 'reset');
        }
    }

    return $status;
}

function checklist_refresh_events($courseid = 0) {

    if ($courseid) {
        $checklists = get_records('checklist', 'course', $courseid);
        $course = get_record('course', 'id', $courseid);
    } else {
        $checklists = get_records('checklist');
        $course = NULL;
    }
    if (!$checklists) {
        return true;
    }

    foreach ($checklists as $checklist) {
        if ($checklist->duedatesoncalendar) {
            $cm = get_coursemodule_from_instance('checklist', $checklist->id, $checklist->course);
            $chk = new checklist_class($cm->id, 0, $checklist, $cm, $course);
            $chk->setallevents();
        }
    }

    return true;
}

?>
