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
 * A comment added, by a student, to a checklist item
 *
 * @package   mod_checklist
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_checklist\local;

use core\persistent;
use moodle_url;

defined('MOODLE_INTERNAL') || die();

/**
 * Class checklist_comment_student
 * @package mod_checklist
 */
class checklist_comment_student extends persistent {
    /** Table name for the persistent. */
    const TABLE = 'checklist_comment_student';
    /** @var int */
    private static $courseid;
    private $studentname;

    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties() {
        return array(
            'userid' => array(
                'type' => PARAM_INT,
            ),
            'itemid' => array(
                'type' => PARAM_INT,
            ),
            'text' => array(
                'type' => PARAM_TEXT,
            ),
        );
    }

    /**
     * Get all matching comments by user id and item ids
     * @param int $userid
     * @param int[] $itemids
     * @return checklist_comment_student[] $itemid => $check
     */
    public static function get_student_comments_indexed($userid, $itemids): array
    {
        global $DB;

        $ret = [];
        if (!$itemids) {
            return $ret;
        }

        list($isql, $params) = $DB->get_in_or_equal($itemids, SQL_PARAMS_NAMED);
        $params['userid'] = $userid;
        $studentcomments = self::get_records_select("userid = :userid AND itemid $isql", $params);

        foreach ($studentcomments as $comment) {
            $ret[$comment->get('itemid')] = $comment;
        }
        return $ret;
    }

    /**
     * Get the name of the person who made the comment
     * @return string|null
     */
    public function get_commentby_name() {
        return $this->studentname;
    }

    /**
     * Get the user profile URL for the commenting user
     * @return moodle_url
     */
    public function get_commentby_url() {
        return new moodle_url('/user/view.php', ['id' => $this->get('userid'), 'course' => self::$courseid]);
    }

    /**
     * Add the name of the commenter to the given comments.
     * @param checklist_comment_student[] $studentcomments
     */
    public static function add_student_names($studentcomments) {
        global $DB;

        $userids = [];
        foreach ($studentcomments as $studentcomment) {
            if ($studentcomment->get('userid')) {
                $userids[] = $studentcomment->get('userid');
            }
        }
        if (!$userids) {
            return;
        }

        if (class_exists('\core_user\fields')) {
            $namesql = \core_user\fields::for_name()->get_sql('', true);
        } else {
            $namesql = (object)[
                'selects' => ','.get_all_user_name_fields(true),
                'joins' => '',
                'params' => [],
                'mappings' => [],
            ];
        }
        $studentcommentusers = $DB->get_records_list('user', 'id', $userids, '', 'id'.$namesql->selects);
        foreach ($studentcomments as $studentcomment) {
            if ($studentcomment->get('userid')) {
                if (isset($studentcommentusers[$studentcomment->get('userid')])) {
                    $studentcomment->studentname = fullname($studentcommentusers[$studentcomment->get('userid')]);
                }
            }
        }
    }

    /**
     * Store the current course id
     * @param int $courseid
     */
    public static function set_courseid($courseid) {
        self::$courseid = $courseid;
    }

    /** Update or create a comment for a student on the given checklist item.
     * @param $checklistitemid
     * @param $commenttext
     * @return bool
     */
    public static function update_student_comment($checklistitemid, $commenttext, $userid): bool
    {
        $existingcomment = checklist_comment_student::get_record(['itemid' => $checklistitemid, 'userid' => $userid]);
        if (!$existingcomment) {
            $newcomment = new checklist_comment_student();
            $newcomment->set('itemid', $checklistitemid);
            $newcomment->set('userid', $userid);
            $newcomment->set('text', $commenttext);
            $newcomment->create();
            return true;
        } else {
            $existingcomment->set('text', $commenttext);
            return $existingcomment->update();
        }
    }

}
