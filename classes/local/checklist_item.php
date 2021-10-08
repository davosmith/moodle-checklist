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
 * Class to hold a checklist item.
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
require_once($CFG->dirroot.'/mod/checklist/lib.php');

/**
 * Class checklist_item
 * @package mod_checklist
 */
class checklist_item extends data_object {
    /** @var string */
    public $table = 'checklist_item';
    /** @var string[] */
    public $requiredfields = [
        'id', 'checklist', 'userid', 'displaytext', 'position', 'indent', 'itemoptional', 'duetime',
        'eventid', 'colour', 'moduleid', 'hidden', 'groupingid', 'linkcourseid', 'linkurl', 'openlinkinnewwindow'
    ];

    // DB fields.
    /** @var int */
    public $checklist;
    /** @var int */
    public $userid;
    /** @var string */
    public $displaytext;
    /** @var int */
    public $position;
    /** @var int */
    public $indent = 0;
    /** @var int */
    public $itemoptional = CHECKLIST_OPTIONAL_NO;
    /** @var int */
    public $duetime = 0;
    /** @var int */
    public $eventid = 0;
    /** @var string */
    public $colour = 'black';
    /** @var int */
    public $moduleid = 0;
    /** @var int */
    public $hidden = CHECKLIST_HIDDEN_NO;
    /** @var int */
    public $groupingid = 0;
    /** @var int|null */
    public $linkcourseid = null;
    /** @var string|null */
    public $linkurl = null;
    /** @var bool */
    public $openlinkinnewwindow = false;

    // Extra status fields (for a particular student).
    /** @var int */
    public $usertimestamp = 0;
    /** @var int */
    public $teachermark = CHECKLIST_TEACHERMARK_UNDECIDED;
    /** @var int */
    public $teachertimestamp = 0;
    /** @var int|null */
    public $teacherid = null;

    /** @var string|null */
    protected $teachername = null;
    /** @var checklist_comment|null */
    protected $comment = null;
    /** @var checklist_comment_student|null */
    protected $studentcomment = null;
    /** @var bool */
    protected $editme = false;
    /** @var moodle_url|null */
    protected $modulelink = null;

    /** @var string|null Name of the grouping (set by add_grouping_names) */
    public $groupingname = null;

    /** Link to activity */
    const LINK_MODULE = 'module';
    /** Link to course */
    const LINK_COURSE = 'course';
    /** Link to arbitrary URL */
    const LINK_URL = 'url';

    /**
     * checklist_item constructor.
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
     * Fetch a single matching record.
     * @param array $params
     * @return data_object|false|object
     */
    public static function fetch($params) {
        return self::fetch_helper('checklist_item', __CLASS__, $params);
    }

    /**
     * Fetch all matching records.
     * @param array $params
     * @param bool $sort
     * @return array|false|mixed
     */
    public static function fetch_all($params, $sort = false) {
        $ret = self::fetch_all_helper('checklist_item', __CLASS__, $params);
        if (!$ret) {
            $ret = [];
        }
        if ($sort) {
            self::sort_items($ret);
        }
        return $ret;
    }

    /**
     * Sort the given items
     * @param self[] $items
     */
    public static function sort_items(&$items) {
        if (!$items) {
            return;
        }
        uasort($items, function (checklist_item $a, checklist_item $b) {
            if ($a->position < $b->position) {
                return -1;
            }
            if ($a->position > $b->position) {
                return 1;
            }
            // Sort by id, if the positions are the same.
            if ($a->id < $b->id) {
                return -1;
            }
            if ($a->id > $b->id) {
                return 1;
            }
            return 0;
        });
    }

    /**
     * Store the item status (in the current user's checklist)
     * @param int|null $usertimestamp
     * @param int|null $teachermark
     * @param int|null $teachertimestamp
     * @param int|null $teacherid
     */
    public function store_status($usertimestamp = null, $teachermark = null, $teachertimestamp = null, $teacherid = null) {
        if ($usertimestamp !== null) {
            $this->usertimestamp = $usertimestamp;
        }
        if ($teachermark !== null) {
            if (!checklist_check::teachermark_valid($teachermark)) {
                debugging('Unexpected teachermark value: '.$teachermark);
                $teachermark = CHECKLIST_TEACHERMARK_UNDECIDED;
            }
            $this->teachermark = $teachermark;
        }
        if ($teachertimestamp !== null) {
            $this->teachertimestamp = $teachertimestamp;
        }
        if ($teacherid !== null) {
            $this->teacherid = $teacherid;
        }
    }

    /**
     * Is the item currently checked?
     * @param bool $byteacher
     * @return bool
     */
    public function is_checked($byteacher) {
        if ($this->userid > 0 || !$byteacher) {
            // User custom items are always checked-off by students (regardless of checklist settings).
            return $this->usertimestamp > 0;
        } else {
            return ($this->teachermark == CHECKLIST_TEACHERMARK_YES);
        }
    }

    /**
     * Is this checked by the teacher?
     * @return bool
     */
    public function is_checked_teacher() {
        return $this->is_checked(true);
    }

    /**
     * Is this checked by the student?
     * @return bool
     */
    public function is_checked_student() {
        return $this->is_checked(false);
    }

    /**
     * Is this item a heading?
     * @return bool
     */
    public function is_heading() {
        return ($this->itemoptional == CHECKLIST_OPTIONAL_HEADING);
    }

    /**
     * Is this a required item?
     * @return bool
     */
    public function is_required() {
        return ($this->itemoptional == CHECKLIST_OPTIONAL_NO);
    }

    /**
     * Is this an optional item?
     * @return bool
     */
    public function is_optional() {
        return ($this->itemoptional == CHECKLIST_OPTIONAL_YES);
    }

    /**
     * Get the relevant image URL
     * @param string $imagename
     * @param string $component
     * @return moodle_url
     */
    private function image_url($imagename, $component) {
        global $CFG, $OUTPUT;
        if ($CFG->branch < 33) {
            return $OUTPUT->pix_url($imagename, $component);
        }
        return $OUTPUT->image_url($imagename, $component);
    }

    /**
     * Get the relevant teacher mark image
     * @return moodle_url
     */
    public function get_teachermark_image_url() {
        static $images = null;
        if ($images === null) {
            $images = [
                CHECKLIST_TEACHERMARK_YES => $this->image_url('tick_box', 'mod_checklist'),
                CHECKLIST_TEACHERMARK_NO => $this->image_url('cross_box', 'mod_checklist'),
                CHECKLIST_TEACHERMARK_UNDECIDED => $this->image_url('empty_box', 'mod_checklist'),
            ];
        }
        return $images[$this->teachermark];
    }

    /**
     * Get the text for the teacher mark
     * @return string
     */
    public function get_teachermark_text() {
        static $text = null;
        if ($text === null) {
            $text = [
                CHECKLIST_TEACHERMARK_YES => get_string('teachermarkyes', 'mod_checklist'),
                CHECKLIST_TEACHERMARK_NO => get_string('teachermarkno', 'mod_checklist'),
                CHECKLIST_TEACHERMARK_UNDECIDED => get_string('teachermarkundecided', 'mod_checklist'),
            ];
        }
        return $text[$this->teachermark];
    }

    /**
     * Get the CSS class for the teacher mark.
     * @return string
     */
    public function get_teachermark_class() {
        static $classes = null;
        if ($classes === null) {
            $classes = [
                CHECKLIST_TEACHERMARK_YES => 'teachermarkyes',
                CHECKLIST_TEACHERMARK_NO => 'teachermarkno',
                CHECKLIST_TEACHERMARK_UNDECIDED => 'teachermarkundecided',
            ];
        }
        return $classes[$this->teachermark];
    }

    /**
     * Toggle the 'hidden' status for the item.
     */
    public function toggle_hidden() {
        if ($this->hidden == CHECKLIST_HIDDEN_BYMODULE) {
            return; // Do not override items linked to hidden Moodle activities.
        }
        if ($this->hidden == CHECKLIST_HIDDEN_NO) {
            $this->hidden = CHECKLIST_HIDDEN_MANUAL;
        } else {
            $this->hidden = CHECKLIST_HIDDEN_NO;
        }
        $this->update();
    }

    /**
     * Hide the item
     */
    public function hide_item() {
        if (!$this->moduleid) {
            return;
        }
        if ($this->hidden != CHECKLIST_HIDDEN_NO) {
            return;
        }
        $this->hidden = CHECKLIST_HIDDEN_MANUAL;
        $this->update();
    }

    /**
     * Show the item
     */
    public function show_item() {
        if (!$this->moduleid) {
            return;
        }
        if ($this->hidden != CHECKLIST_HIDDEN_MANUAL) {
            return;
        }
        $this->hidden = CHECKLIST_HIDDEN_NO;
        $this->update();
    }

    /**
     * Mark the item as checked / unchecked by the student.
     * @param int $userid
     * @param bool $checked
     * @param int|null $timestamp
     * @return bool
     * @throws \coding_exception
     */
    public function set_checked_student($userid, $checked, $timestamp = null) {
        if ($checked == $this->is_checked_student()) {
            return false; // No change.
        }

        // Update checkmark in the database.
        $check = new checklist_check(['item' => $this->id, 'userid' => $userid]);
        $check->set_checked_student($checked, $timestamp);
        $check->save();

        // Update the stored value in this item.
        $this->usertimestamp = $check->usertimestamp;
        return true;
    }

    /**
     * For the given item, clear all student checkmarks (leaving teacher marks untouched).
     */
    public function clear_all_student_checks() {
        global $DB;
        $DB->set_field_select('checklist_check', 'usertimestamp', 0, 'item = ? AND usertimestamp > 0', [$this->id]);
    }

    /**
     * Set the teacher status for the item
     * @param int $userid
     * @param int $teachermark
     * @param int $teacherid
     * @return bool
     */
    public function set_teachermark($userid, $teachermark, $teacherid) {
        if ($teachermark == $this->teachermark) {
            return false; // No change.
        }

        if (!checklist_check::teachermark_valid($teachermark)) {
            throw new \coding_exception('Invalid teachermark '.$teachermark);
        }

        // Update checkmark in the database.
        $check = new checklist_check(['item' => $this->id, 'userid' => $userid]);
        $check->set_teachermark($teachermark, $teacherid);
        $check->save();

        // Update the stored value in this item.
        $this->teachertimestamp = $check->teachertimestamp;
        $this->teachermark = $check->teachermark;
        $this->teacherid = $check->teacherid;

        return true;
    }

    /**
     * Get the name of the teacher who updated this item
     * @return string|null
     */
    public function get_teachername() {
        return $this->teachername;
    }

    /**
     * Get the comment made by the teacher on this item
     * @return checklist_comment|null
     */
    public function get_comment() {
        return $this->comment;
    }

    /**
     * Get the comment made by the teacher on this item
     * @return checklist_comment_student|null
     */
    public function get_student_comment() {
        return $this->studentcomment;
    }

    /**
     * Mark the item being edited at the moment
     * @param bool $editme
     */
    public function set_editme($editme = true) {
        $this->editme = $editme;
    }

    /**
     * Is this item being edited at the moment?
     * @return bool
     */
    public function is_editme() {
        return $this->editme;
    }

    /**
     * Get the URL to link to
     * @return moodle_url|null
     * @throws \moodle_exception
     */
    public function get_link_url() {
        if ($this->modulelink) {
            return $this->modulelink;
        }
        if ($this->linkcourseid) {
            return new moodle_url('/course/view.php', ['id' => $this->linkcourseid]);
        }
        if ($this->linkurl) {
            return new moodle_url($this->linkurl);
        }
        return null;
    }

    /**
     * Get the link type for this item
     * @return string|null
     */
    public function get_link_type() {
        if ($this->modulelink) {
            return self::LINK_MODULE;
        }
        if ($this->linkcourseid) {
            return self::LINK_COURSE;
        }
        if ($this->linkurl) {
            return self::LINK_URL;
        }
        return null;
    }

    /**
     * Set the activity module link
     * @param moodle_url $link
     */
    public function set_modulelink(moodle_url $link) {
        $this->modulelink = $link;
    }

    /**
     * Check if this item can be automatically updated.
     * i.e. is it linked to an activity or linked to a course with completion enabled
     *
     * @return bool
     */
    public function is_auto_item() {
        if ($this->moduleid) {
            return true;
        }
        if ($this->linkcourseid) {
            $completion = new \completion_info(get_course($this->linkcourseid));
            if ($completion->is_enabled()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Add links from the checklist items to the comments on them (for a particular user).
     * @param checklist_item[] $items (indexed by id)
     * @param checklist_comment[] $comments (indexed by itemid)
     */
    public static function add_comments($items, $comments) {
        foreach ($items as $item) {
            if (isset($comments[$item->id])) {
                $item->comment = $comments[$item->id];
            }
        }
    }

    /**
     * Add links from the checklist items to the student comments on them (for a particular user).
     * @param checklist_item[] $items checklist items.
     * @param checklist_comment_student[] $studentcomments student comments indexed by item id.
     */
    public static function add_student_comments(array $items, array $studentcomments) {
        foreach ($items as $item) {
            if (isset($studentcomments[$item->id])) {
                $item->studentcomment = $studentcomments[$item->id];
            }
        }
    }

    /**
     * Add the names of all the teachers who have updated the checklist items.
     * @param checklist_item[] $items
     */
    public static function add_teacher_names($items) {
        global $DB;

        $userids = [];
        foreach ($items as $item) {
            if ($item->teacherid) {
                $userids[] = $item->teacherid;
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
        $teachers = $DB->get_records_list('user', 'id', $userids, '', 'id'.$namesql->selects);
        foreach ($items as $item) {
            if ($item->teacherid) {
                if (isset($teachers[$item->teacherid])) {
                    $item->teachername = fullname($teachers[$item->teacherid]);
                }
            }
        }
    }

    /**
     * Add grouping names to all the checklist items given
     * @param checklist_item[] $items
     * @param int $courseid
     */
    public static function add_grouping_names($items, $courseid) {
        $groupings = \checklist_class::get_course_groupings($courseid);
        if (!$groupings) {
            return;
        }
        foreach ($items as $item) {
            if ($item->groupingid && isset($groupings[$item->groupingid])) {
                $item->groupingname = $groupings[$item->groupingid];
            }
        }
    }
}
