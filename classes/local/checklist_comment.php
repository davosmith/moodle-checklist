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
 * A comment added, by a teacher, to a checklist item
 *
 * @package   mod_checklist
 * @copyright 2016 Davo Smith, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_checklist\local;

use data_object;
use moodle_url;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot.'/completion/data_object.php');

/**
 * Class checklist_comment
 * @package mod_checklist
 */
class checklist_comment extends data_object {
    /** @var string */
    public $table = 'checklist_comment';
    /** @var string[] */
    public $requiredfields = [
        'id', 'itemid', 'userid', 'commentby', 'text'
    ];

    // DB fields.
    /** @var int */
    public $itemid;
    /** @var int */
    public $userid;
    /** @var int */
    public $commentby;
    /** @var string */
    public $text;

    // Extra data.
    /** @var string|null */
    protected $commentbyname = null;

    /** @var int|null */
    protected static $courseid = null;

    /**
     * checklist_comment constructor.
     * @param array|null $params
     * @param bool $fetch
     * @throws \coding_exception
     */
    public function __construct(array $params = null, $fetch = true) {
        // Really ugly hack to stop travis complaining about $required_fields.
        $this->{'required_fields'} = $this->requiredfields;
        parent::__construct($params, $fetch);
    }

    /**
     * Get a single matching record.
     * @param array $params
     * @return data_object|false|object
     */
    public static function fetch($params) {
        return self::fetch_helper('checklist_comment', __CLASS__, $params);
    }

    /**
     * Get all matching records.
     * @param array $params
     * @param false $sort
     * @return array|false|mixed
     */
    public static function fetch_all($params, $sort = false) {
        $ret = self::fetch_all_helper('checklist_comment', __CLASS__, $params);
        if (!$ret) {
            $ret = [];
        }
        return $ret;
    }

    /**
     * Get all matching comments by user id and item ids
     * @param int $userid
     * @param int[] $itemids
     * @return checklist_comment[] $itemid => $check
     */
    public static function fetch_by_userid_itemids($userid, $itemids) {
        global $DB;

        $ret = [];
        if (!$itemids) {
            return $ret;
        }

        list($isql, $params) = $DB->get_in_or_equal($itemids, SQL_PARAMS_NAMED);
        $params['userid'] = $userid;
        $comments = $DB->get_records_select('checklist_comment', "userid = :userid AND itemid $isql", $params);
        foreach ($comments as $comment) {
            $ret[$comment->itemid] = new checklist_comment();
            self::set_properties($ret[$comment->itemid], $comment);
        }
        return $ret;
    }

    /**
     * Get the name of the person who made the comment
     * @return string|null
     */
    public function get_commentby_name() {
        return $this->commentbyname;
    }

    /**
     * Get the user profile URL for the commenting user
     * @return moodle_url
     */
    public function get_commentby_url() {
        return new moodle_url('/user/view.php', ['id' => $this->commentby, 'course' => self::$courseid]);
    }

    /**
     * Add the name of the commenter to the given comments.
     * @param checklist_comment[] $comments
     */
    public static function add_commentby_names($comments) {
        global $DB;

        $userids = [];
        foreach ($comments as $comment) {
            if ($comment->commentby) {
                $userids[] = $comment->commentby;
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
        $commentusers = $DB->get_records_list('user', 'id', $userids, '', 'id'.$namesql->selects);
        foreach ($comments as $comment) {
            if ($comment->commentby) {
                if (isset($commentusers[$comment->commentby])) {
                    $comment->commentbyname = fullname($commentusers[$comment->commentby]);
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
}
