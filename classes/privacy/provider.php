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
 * GDPR information
 *
 * @package   mod_checklist
 * @copyright 2018 Davo Smith, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_checklist\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\helper;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

defined('MOODLE_INTERNAL') || die();

/**
 * Class provider
 * @package mod_checklist
 */
class provider implements \core_privacy\local\metadata\provider,
                          \core_privacy\local\request\plugin\provider,
                          \core_privacy\local\request\core_userlist_provider {

    /**
     * Get a description of the data stored by this plugin.
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection) : collection {
        $collection->add_database_table(
            'checklist_item',
            [
                'checklist' => 'privacy:metadata:checklist_item:checklist',
                'userid' => 'privacy:metadata:checklist_item:userid',
                'displaytext' => 'privacy:metadata:checklist_item:displaytext',
            ],
            'privacy:metadata:checklist_item'
        );
        $collection->add_database_table(
            'checklist_check',
            [
                'item' => 'privacy:metadata:checklist_check:item',
                'userid' => 'privacy:metadata:checklist_check:userid',
                'usertimestamp' => 'privacy:metadata:checklist_check:usertimestamp',
                'teachermark' => 'privacy:metadata:checklist_check:teachermark',
                'teachertimestamp' => 'privacy:metadata:checklist_check:teachertimestamp',
                'teacherid' => 'privacy:metadata:checklist_check:teacherid',
            ],
            'privacy:metadata:checklist_check'
        );
        $collection->add_database_table(
            'checklist_comment',
            [
                'itemid' => 'privacy:metadata:checklist_comment:itemid',
                'userid' => 'privacy:metadata:checklist_comment:userid',
                'commentby' => 'privacy:metadata:checklist_comment:commentby',
                'text' => 'privacy:metadata:checklist_comment:text',
            ],
            'privacy:metadata:checklist_comment'
        );
        $collection->add_database_table(
            'checklist_comment_student',
            [
                'itemid' => 'privacy:metadata:checklist_comment_student:itemid',
                'usermodified' => 'privacy:metadata:checklist_comment_student:usermodified',
                'text' => 'privacy:metadata:checklist_comment_student:text',
            ],
            'privacy:metadata:checklist_comment_student'
        );
        return $collection;
    }

    /** @var int */
    private static $modid;

    /**
     * Get the module id for the 'checklist' module.
     * @return false|mixed
     * @throws \dml_exception
     */
    private static function get_modid() {
        global $DB;
        if (self::$modid === null) {
            self::$modid = $DB->get_field('modules', 'id', ['name' => 'checklist']);
        }
        return self::$modid;
    }

    /**
     * Get the contexts where the given user has 'checklist' data.
     * @param int $userid
     * @return contextlist
     * @throws \dml_exception
     */
    public static function get_contexts_for_userid(int $userid) : contextlist {
        $contextlist = new contextlist();
        $modid = self::get_modid();
        if (!$modid) {
            return $contextlist; // Checklist module not installed.
        }

        $params = [
            'modid' => $modid,
            'contextlevel' => CONTEXT_MODULE,
            'userid' => $userid,
        ];

        // User-created personal checklist items.
        $sql = '
           SELECT c.id
             FROM {context} c
             JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
                                      AND cm.module = :modid
             JOIN {checklist} ck ON ck.id = cm.instance
             JOIN {checklist_item} ci ON ci.checklist = ck.id
            WHERE ci.userid = :userid
        ';
        $contextlist->add_from_sql($sql, $params);

        // Items that have been checked-off by the user (or for the user, by their teacher).
        $sql = '
           SELECT c.id
             FROM {context} c
             JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
                                      AND cm.module = :modid
             JOIN {checklist} ck ON ck.id = cm.instance
             JOIN {checklist_item} ci ON ci.checklist = ck.id
             JOIN {checklist_check} cc ON cc.item = ci.id
            WHERE cc.userid = :userid
        ';
        $contextlist->add_from_sql($sql, $params);

        // Comments made by the teacher about a particular item for a user.
        $sql = '
           SELECT c.id
             FROM {context} c
             JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
                                      AND cm.module = :modid
             JOIN {checklist} ck ON ck.id = cm.instance
             JOIN {checklist_item} ci ON ci.checklist = ck.id
             JOIN {checklist_comment} ccm ON ccm.itemid = ci.id
            WHERE ccm.userid = :userid
        ';
        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param   userlist    $userlist   The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if (!is_a($context, \context_module::class)) {
            return;
        }
        $modid = self::get_modid();
        if (!$modid) {
            return; // Checklist module not installed.
        }
        $params = [
            'modid' => $modid,
            'contextlevel' => CONTEXT_MODULE,
            'contextid'    => $context->id,
        ];

        // User-created personal checklist items.
        $sql = "
            SELECT ci.userid
              FROM {checklist_item} ci
              JOIN {checklist} ck ON ck.id = ci.checklist
              JOIN {course_modules} cm ON cm.instance = ck.id AND cm.module = :modid
              JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :contextlevel
             WHERE ctx.id = :contextid
        ";
        $userlist->add_from_sql('userid', $sql, $params);

        // Items that have been checked-off by the user (or for the user, by their teacher).
        $sql = "
            SELECT cc.userid
              FROM {checklist_check} cc
              JOIN {checklist_item} ci ON ci.id = cc.item
              JOIN {checklist} ck ON ck.id = ci.checklist
              JOIN {course_modules} cm ON cm.instance = ck.id AND cm.module = :modid
              JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :contextlevel
             WHERE ctx.id = :contextid
        ";
        $userlist->add_from_sql('userid', $sql, $params);

        // Comments made by the teacher about a particular item for a user.
        $sql = "
            SELECT ccm.userid
              FROM {checklist_comment} ccm
              JOIN {checklist_item} ci ON ci.id = ccm.itemid
              JOIN {checklist} ck ON ck.id = ci.checklist
              JOIN {course_modules} cm ON cm.instance = ck.id AND cm.module = :modid
              JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :contextlevel
             WHERE ctx.id = :contextid
        ";
        $userlist->add_from_sql('userid', $sql, $params);
    }

    /**
     * Export all the checklist data for the given contextlist
     * @param approved_contextlist $contextlist
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (!$contextlist->count()) {
            return;
        }

        $user = $contextlist->get_user();
        list($contextsql, $contextparams) = $DB->get_in_or_equal($contextlist->get_contextids(), SQL_PARAMS_NAMED);

        $sql = "SELECT cm.id AS cmid,
                       ci.displaytext,
                       cc.usertimestamp,
                       cc.teachermark,
                       cc.teachertimestamp,
                       cc.teacherid,
                       ccm.text AS commenttext,
                       ccm.commentby

                 FROM {context} c
                 JOIN {course_modules} cm ON cm.id = c.instanceid
                 JOIN {checklist} ck ON ck.id = cm.instance
                 JOIN {checklist_item} ci ON ci.checklist = ck.id
                 LEFT JOIN {checklist_check} cc ON cc.item = ci.id
                 LEFT JOIN {checklist_comment} ccm ON ccm.itemid = ci.id

                WHERE c.id $contextsql
                  AND (ci.userid = 0 OR ci.userid = :userid1)
                  AND (cc.userid IS NULL OR cc.userid = :userid2)
                  AND (ccm.userid IS NULL OR ccm.userid = :userid3)
                  AND (ci.userid <> 0 OR cc.userid IS NOT NULL OR ccm.userid IS NOT NULL)

                ORDER BY cm.id, ci.position, ci.id
        ";
        $params = ['userid1' => $user->id, 'userid2' => $user->id, 'userid3' => $user->id] + $contextparams;
        $lastcmid = null;
        $itemdata = [];

        $teachermarks = [0 => '', 1 => get_string('yes'), 2 => get_string('no')];
        $items = $DB->get_recordset_sql($sql, $params);
        foreach ($items as $item) {
            if ($lastcmid !== $item->cmid) {
                if ($itemdata) {
                    self::export_checklist_data_for_user($itemdata, $lastcmid, $user);
                }
                $itemdata = [];
                $lastcmid = $item->cmid;
            }

            $itemdata[] = (object)[
                'item' => $item->displaytext,
                'usertimestamp' => $item->usertimestamp ? transform::datetime($item->usertimestamp) : '',
                'teachermark' => $teachermarks[$item->teachermark] ?? '',
                'teachertimestamp' => $item->teachertimestamp ? transform::datetime($item->teachertimestamp) : '',
                'teacherid' => $item->teacherid,
                'commenttext' => $item->commenttext,
                'commentby' => $item->commentby,
            ];
        }
        $items->close();
        if ($itemdata) {
            self::export_checklist_data_for_user($itemdata, $lastcmid, $user);
        }
    }

    /**
     * Export the supplied personal data for a single checklist activity, along with any generic data or area files.
     *
     * @param array $items the data for each of the items in the checklist
     * @param int $cmid
     * @param \stdClass $user
     */
    protected static function export_checklist_data_for_user(array $items, int $cmid, \stdClass $user) {
        // Fetch the generic module data for the choice.
        $context = \context_module::instance($cmid);
        $contextdata = helper::get_context_data($context, $user);

        // Merge with checklist data and write it.
        $contextdata = (object)array_merge((array)$contextdata, ['items' => $items]);
        writer::with_context($context)->export_data([], $contextdata);

        // Write generic module intro files.
        helper::export_context_files($context, $user);
    }

    /**
     * Delete all checklist data for all users in the given context
     * @param \context $context
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if (!$context) {
            return;
        }
        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }
        if (!$cm = get_coursemodule_from_id('checklist', $context->instanceid)) {
            return;
        }
        $itemids = $DB->get_fieldset_select('checklist_item', 'id', 'checklist = ?', [$cm->instance]);
        if ($itemids) {
            $DB->delete_records_list('checklist_check', 'item', $itemids);
            $DB->delete_records_list('checklist_comment', 'itemid', $itemids);
            $DB->delete_records_select('checklist_item', 'checklist = ? AND userid <> 0', [$cm->instance]);
            $DB->delete_records_list('checklist_comment_student', 'itemid', $itemids);
        }
    }

    /**
     * Delete all checklist data for the given contexts and user
     * @param approved_contextlist $contextlist
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        if (!$contextlist->count()) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_MODULE) {
                continue;
            }
            if (!$cm = get_coursemodule_from_id('checklist', $context->instanceid)) {
                continue;
            }
            $itemids = $DB->get_fieldset_select('checklist_item', 'id', 'checklist = ?', [$cm->instance]);
            if ($itemids) {
                list($isql, $params) = $DB->get_in_or_equal($itemids, SQL_PARAMS_NAMED);
                $params['userid'] = $userid;
                $DB->delete_records_select('checklist_check', "item $isql AND userid = :userid", $params);
                $DB->delete_records_select('checklist_comment', "itemid $isql AND userid = :userid", $params);
                $DB->delete_records_select('checklist_comment_student', "itemid $isql AND usermodified = :userid", $params);
                $params = ['instanceid' => $cm->instance, 'userid' => $userid];
                $DB->delete_records_select('checklist_item', 'checklist = :instanceid AND userid = :userid', $params);
            }
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param   approved_userlist       $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        $context = $userlist->get_context();
        if (!is_a($context, \context_module::class)) {
            return;
        }
        $modid = self::get_modid();
        if (!$modid) {
            return; // Checklist module not installed.
        }
        if (!$cm = get_coursemodule_from_id('checklist', $context->instanceid)) {
            return;
        }

        // Prepare SQL to gather all completed IDs.
        $itemids = $DB->get_fieldset_select('checklist_item', 'id', 'checklist = ?', [$cm->instance]);
        list($itsql, $itparams) = $DB->get_in_or_equal($itemids, SQL_PARAMS_NAMED);
        $userids = $userlist->get_userids();
        list($insql, $inparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);

        // Delete user-created personal checklist items.
        $DB->delete_records_select(
            'checklist_item',
            "userid $insql AND checklist = :checklistid",
            array_merge($inparams, ['checklistid' => $cm->instance])
        );

        // Delete items that have been checked-off by the user (or for the user, by their teacher).
        $DB->delete_records_select(
            'checklist_check',
            "userid $insql AND item $itsql",
            array_merge($inparams, $itparams)
        );

        // Delete comments made by a teacher about a particular item for a student.
        $DB->delete_records_select(
            'checklist_comment',
            "userid $insql AND itemid $itsql",
            array_merge($inparams, $itparams)
        );
    }
}
