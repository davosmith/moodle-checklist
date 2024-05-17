<?php
// This file is part of Moodle - http://moodle.org/
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

/**
 * Supporting class for calculating autoupdate
 *
 * @package   mod_checklist
 * @copyright 2015 Davo Smith, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_checklist\local;

/**
 * Class autoupdate
 * @package mod_checklist
 */
class autoupdate {
    /** @var null */
    protected static $uselegacy = null;
    /** @var \core\log\sql_reader */
    protected static $reader = null;

    /**
     * Get the legacy log actions associated with the given activity
     * @param string $modname
     * @return string[]|null
     */
    public static function get_log_actions_legacy($modname) {
        switch ($modname) {
            case 'survey':
                return ['submit'];
                break;
            case 'quiz':
                return ['close attempt'];
                break;
            case 'forum':
                return ['add post', 'add discussion'];
                break;
            case 'resource':
                return ['view'];
                break;
            case 'hotpot':
                return ['submit'];
                break;
            case 'wiki':
                return ['edit'];
                break;
            case 'checklist':
                return ['complete'];
                break;
            case 'choice':
                return ['choose'];
                break;
            case 'lams':
                return ['view'];
                break;
            case 'scorm':
                return ['view'];
                break;
            case 'assignment':
                return ['upload'];
                break;
            case 'journal':
                return ['add entry'];
                break;
            case 'lesson':
                return ['end'];
                break;
            case 'realtimequiz':
                return ['submit'];
                break;
            case 'workshop':
                return ['submit'];
                break;
            case 'glossary':
                return ['add entry'];
                break;
            case 'data':
                return ['add'];
                break;
            case 'chat':
                return ['talk'];
                break;
            case 'feedback':
                return ['submit'];
                break;
        }
        return null;
    }

    /**
     * Get the log actions associated with the given activity
     * @param string $modname
     * @return string[]|\string[][]|null
     */
    public static function get_log_action_new($modname) {
        switch ($modname) {
            case 'assign':
                return ['submission', 'created'];
                break;
            case 'book':
                return ['course_module', 'viewed'];
                break;
            case 'chat':
                return ['message', 'sent'];
                break;
            case 'checklist':
                return ['checklist', 'completed'];
                break;
            case 'choice':
                return ['answer', 'submitted'];
                break;
            case 'choicegroup':
                return ['choice', 'updated'];
                break;
            case 'data':
                return ['record', 'created'];
                break;
            case 'feedback':
                return ['response', 'submitted'];
                break;
            case 'folder':
                return ['course_module', 'viewed'];
                break;
            case 'forum':
                return [
                    ['post', 'created'],
                    ['discussion', 'created'],
                ];
                break;
            case 'glossary':
                return ['entry', 'created'];
                break;
            case 'hotpot':
                return ['attempt', 'submitted'];
                break;
            case 'imscp':
                return ['course_module', 'viewed'];
                break;
            case 'lesson':
                return ['lesson', 'ended'];
                break;
            case 'lti':
                return ['course_module', 'viewed'];
                break;
            case 'page':
                return ['course_module', 'viewed'];
                break;
            case 'quiz':
                return ['attempt', 'submitted'];
                break;
            case 'resource':
                return ['course_module', 'viewed'];
                break;
            case 'scorm':
                return ['sco', 'launched'];
                break;
            case 'survey':
                return ['response', 'submitted'];
                break;
            case 'url':
                return ['course_module', 'viewed'];
                break;
            case 'wiki':
                return ['page', 'updated'];
                break;
            case 'workshop':
                return ['submission', 'created'];
                break;
        }
        return null;
    }

    /**
     * Get a list of all userids where the user has a log entry with the given cmid and action(s).
     *
     * @param string $modname
     * @param int $cmid
     * @param int[] $checklistuserids limit the search to the given users
     * @return int[]
     */
    public static function get_logged_userids($modname, $cmid, $checklistuserids) {
        self::init_log_status();

        $userids = [];
        if (self::$uselegacy) {
            $userids = array_merge($userids, self::get_logged_userids_legacy($modname, $cmid, $checklistuserids));
        }
        if (self::$reader) {
            $userids = array_merge($userids, self::get_logged_userids_new($modname, $cmid, $checklistuserids));
        }
        return array_unique($userids);
    }

    /**
     * Initialise the log reader.
     */
    protected static function init_log_status() {
        global $CFG;
        if (self::$uselegacy !== null) {
            return;
        }

        $manager = get_log_manager();
        $allreaders = $manager->get_readers();
        if (isset($allreaders['logstore_legacy'])) {
            self::$uselegacy = true;
        } else {
            self::$uselegacy = false;
        }

        if ($CFG->branch < 29) {
            $selectreaders = $manager->get_readers('\core\log\sql_select_reader');
        } else {
            $selectreaders = $manager->get_readers('\core\log\sql_reader');
        }
        if ($selectreaders) {
            self::$reader = reset($selectreaders);
        }
    }

    /**
     * Get the relevant userids from legacy logs
     * @param string $modname
     * @param int $cmid
     * @param int[] $userids
     * @return array
     * @throws \coding_exception
     * @throws \dml_exception
     */
    protected static function get_logged_userids_legacy($modname, $cmid, $userids) {
        global $DB;

        $logactions = self::get_log_actions_legacy($modname);
        if (!$logactions) {
            return [];
        }

        [$usql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params['cmid'] = $cmid;
        $params['action1'] = $logactions[0];

        $action2 = '';
        if (isset($logactions[1])) {
            $action2 = ' OR action = :action2 ';
            $params['action2'] = $logactions[1];
        }

        $sql = "SELECT DISTINCT userid
                  FROM {log}
                 WHERE cmid = :cmid AND (action = :action1 $action2)
                   AND userid $usql ";
        $userids = $DB->get_fieldset_sql($sql, $params);

        return $userids;
    }

    /**
     * Get the relevant userids from the logs
     * @param string $modname
     * @param int $cmid
     * @param int[] $userids
     * @return array
     * @throws \coding_exception
     * @throws \dml_exception
     */
    protected static function get_logged_userids_new($modname, $cmid, $userids) {
        global $DB;

        $actions = self::get_log_action_new($modname);
        if (!$actions) {
            return [];
        }

        [$usql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);

        $params['contextinstanceid'] = $cmid;
        $params['contextlevel'] = CONTEXT_MODULE;
        $select = "contextinstanceid = :contextinstanceid AND contextlevel = :contextlevel
                   AND target = :target AND action = :action AND userid $usql";

        $userids = [];

        if (!is_array($actions[0])) {
            // To cope with the few (one!) cases where there are multiple possible actions, wrap the other cases
            // in an array (but with only a single item).
            $actions = [$actions];
        }
        foreach ($actions as $action) {
            $params['target'] = $action[0];
            $params['action'] = $action[1];
            $entries = self::$reader->get_events_select($select, $params, '', 0, 0);
            foreach ($entries as $entry) {
                $userids[$entry->userid] = $entry->userid;
            }
        }
        return array_values($userids);
    }

    /**
     * Get details of the logs for the given courses, since the given timestamp.
     *
     * @param int[] $courseids
     * @param int $lastlogtime
     * @return object[]
     */
    public static function get_logs($courseids, $lastlogtime) {
        self::init_log_status();

        $logs = [];
        if (self::$uselegacy) {
            $logs = array_merge($logs, self::get_logs_legacy($courseids, $lastlogtime));
        }
        if (self::$reader) {
            $logs = array_merge($logs, self::get_logs_new($courseids, $lastlogtime));
        }
        return $logs;
    }

    /**
     * Get legacy log entries.
     * @param int[] $courseids
     * @param int $lastlogtime
     * @return array
     * @throws \coding_exception
     * @throws \dml_exception
     */
    protected static function get_logs_legacy($courseids, $lastlogtime) {
        global $DB;

        [$csql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);
        $params['time'] = $lastlogtime;
        $logs = get_logs("l.time >= :time AND l.course $csql AND cmid > 0", $params, 'l.time ASC', '', '', $totalcount);
        $ret = [];
        foreach ($logs as $log) {
            $wantedactions = self::get_log_actions_legacy($log->module);
            if (in_array($log->action, $wantedactions)) {
                $ret[] = $log;
            }
        }
        return $ret;
    }

    /**
     * Get the module name from the given component (if any)
     * @param string $component
     * @return mixed|string|null
     */
    protected static function get_module_from_component($component) {
        [$type, $name] = \core_component::normalize_component($component);
        if ($type == 'mod') {
            return $name;
        }
        if ($type == 'assignsubmission') {
            return 'assign';
        }
        return null;
    }

    /**
     * Get the relevant log records
     * @param int[] $courseids
     * @param int $lastlogtime
     * @return array
     * @throws \coding_exception
     * @throws \dml_exception
     */
    protected static function get_logs_new($courseids, $lastlogtime) {
        global $DB;

        [$csql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);
        $params['time'] = $lastlogtime;
        $select = "courseid $csql AND timecreated > :time";
        $entries = self::$reader->get_events_select($select, $params, 'timecreated ASC', 0, 0);
        $ret = [];
        foreach ($entries as $entry) {
            $info = self::get_entry_info($entry);
            if ($info) {
                $ret[] = $info;
            }
        }

        return $ret;
    }

    /**
     * Get required info from the log entry
     * @param object $entry
     * @return object|null
     */
    protected static function get_entry_info($entry) {
        $module = self::get_module_from_component($entry->component);
        if ($module) {
            $wantedaction = self::get_log_action_new($module);
            if ($wantedaction) {
                if (!is_array($wantedaction[0])) {
                    // Most activities only have a single 'complete' action, but to support those with more
                    // than one (forum!), wrap those with just one action in an array.
                    $wantedaction = [$wantedaction];
                }
                foreach ($wantedaction as $candidate) {
                    [$target, $action] = $candidate;
                    if ($entry->target == $target && $entry->action == $action) {
                        return (object)[
                            'course' => $entry->courseid,
                            'module' => $module,
                            'cmid' => $entry->contextinstanceid,
                            'userid' => $entry->userid,
                        ];
                    }
                }
            }
        }
        return null;
    }

    /**
     * Update the checklist based on the given event
     * @param \core\event\base $event
     * @throws \coding_exception
     */
    public static function update_from_event(\core\event\base $event) {
        global $CFG;
        require_once($CFG->dirroot.'/mod/checklist/autoupdate.php');
        if ($event->target == 'course_module_completion' && $event->action == 'updated') {
            // Update from a completion change event.
            $comp = $event->get_record_snapshot('course_modules_completion', $event->objectid);
            // Update any relevant checklists.
            checklist_completion_autoupdate($comp->coursemoduleid, $comp->userid, $comp->completionstate);
        } else if ($event->target == 'course' && $event->action == 'completed') {

            // Update from a course completion event.
            checklist_course_completion_autoupdate($event->courseid, $event->relateduserid);

        } else {
            // Check if this is an action that counts as 'completing' an activity (when completion is off).
            $info = self::get_entry_info($event);
            if (!$info) {
                return;
            }
            // Update any relevant checklists.
            checklist_autoupdate_internal($info->course, $info->module, $info->cmid, $info->userid);
        }
    }
}
