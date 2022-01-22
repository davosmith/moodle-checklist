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
 * Stores fields that define the status of the checklist output
 *
 * @package   mod_checklist
 * @copyright 2016 Davo Smith, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_checklist\local;

/**
 * Class output_status
 * @package mod_checklist
 */
class output_status {
    // All output.
    /** @var int */
    protected $additemafter = 0;

    // View items only.
    /** @var bool */
    protected $viewother = false;
    /** @var bool */
    protected $userreport = false;
    /** @var bool */
    protected $teachercomments = false;
    /** @var bool */
    protected $studentcomments = false;
    /** @var bool */
    protected $editcomments = false;
    /** @var bool */
    protected $teachermarklocked = false;
    /** @var bool */
    protected $showcompletiondates = false;
    /** @var bool */
    protected $canupdateown = false;
    /** @var bool */
    protected $canaddown = false;
    /** @var bool */
    protected $addown = false;
    /** @var bool */
    protected $showprogressbar = false;
    /** @var bool */
    protected $showteachermark = false;
    /** @var bool */
    protected $showcheckbox = false;
    /** @var bool */
    protected $overrideauto = false;
    /** @var bool */
    protected $checkgroupings = false;
    /** @var bool */
    protected $updateform = false;

    // Edit items only.
    /** @var bool */
    protected $editdates = false;
    /** @var bool */
    protected $editlinks = false;
    /** @var bool */
    protected $allowcourselinks = false;
    /** @var int|null */
    protected $itemid = null;
    /** @var bool */
    protected $autopopulate = false;
    /** @var string|null */
    protected $autoupdatewarning = null;
    /** @var bool */
    protected $editgrouping = false;
    /** @var int|null */
    protected $courseid = null;

    /**
     * Viewing another user (i.e. teacher report about a single user)
     * @return boolean
     */
    public function is_viewother() {
        return $this->viewother;
    }

    /**
     * Set as viewing another user
     * @param boolean $viewother
     */
    public function set_viewother($viewother) {
        $this->viewother = $viewother;
    }

    /**
     * Viewing complete user report (so no updating of checkmarks)
     * @return boolean
     */
    public function is_userreport() {
        return $this->userreport;
    }

    /**
     * Set as being a user report
     * @param boolean $userreport
     */
    public function set_userreport($userreport) {
        $this->userreport = $userreport;
    }

    /**
     * Are teacher comments enabled for this instance?
     * @return boolean
     */
    public function is_teachercomments() {
        return $this->teachercomments;
    }

    /**
     * Are student comments enabled for this instance?
     * @return boolean
     */
    public function is_studentcomments() {
        return $this->studentcomments;
    }

    /**
     * Set as teacher comments enabled
     * @param boolean $teachercomments
     */
    public function set_teachercomments($teachercomments) {
        $this->teachercomments = $teachercomments;
    }

    /**
     * Set as student comments enabled.
     * @param bool $studentcomments
     */
    public function set_studentcomments(bool $studentcomments) {
        $this->studentcomments = $studentcomments;
    }

    /**
     * Is the user editing comments at the moment?
     * @return boolean
     */
    public function is_editcomments() {
        return $this->editcomments;
    }

    /**
     * Set whether or not edit comments is enabled
     * @param boolean $editcomments
     */
    public function set_editcomments($editcomments) {
        $this->editcomments = $editcomments;
    }

    /**
     * Are completed teacher marks locked (so the current user can't update them)?
     * @return boolean
     */
    public function is_teachermarklocked() {
        return $this->teachermarklocked;
    }

    /**
     * Set whether or not teacher marks are locked
     * @param boolean $teachermarklocked
     */
    public function set_teachermarklocked($teachermarklocked) {
        $this->teachermarklocked = $teachermarklocked;
    }

    /**
     * Should the completion dates be output?
     * @return boolean
     */
    public function is_showcompletiondates() {
        return $this->showcompletiondates;
    }

    /**
     * Set whether or not to show completion dates
     * @param boolean $showcompletiondates
     */
    public function set_showcompletiondates($showcompletiondates) {
        $this->showcompletiondates = $showcompletiondates;
    }

    /**
     * Can the user update their own checkmarks (students).
     * @return boolean
     */
    public function is_canupdateown() {
        return $this->canupdateown;
    }

    /**
     * Set if we can update our own checkmarks.
     * @param boolean $canupdateown
     */
    public function set_canupdateown($canupdateown) {
        $this->canupdateown = $canupdateown;
    }

    /**
     * Should the progress bar be shown?
     * @return boolean
     */
    public function is_showprogressbar() {
        return $this->showprogressbar;
    }

    /**
     * Set whether or not to show the progress bar.
     * @param boolean $showprogressbar
     */
    public function set_showprogressbar($showprogressbar) {
        $this->showprogressbar = $showprogressbar;
    }

    /**
     * Should the teacher mark be shown?
     * @return boolean
     */
    public function is_showteachermark() {
        return $this->showteachermark;
    }

    /**
     * Set whether or not teacher marks should be shown
     * @param boolean $showteachermark
     */
    public function set_showteachermark($showteachermark) {
        $this->showteachermark = $showteachermark;
    }

    /**
     * Should the student mark be shown?
     * @return boolean
     */
    public function is_showcheckbox() {
        return $this->showcheckbox;
    }

    /**
     * Set whether or not student marks should be shown
     * @param boolean $showcheckbox
     */
    public function set_showcheckbox($showcheckbox) {
        $this->showcheckbox = $showcheckbox;
    }

    /**
     * Can the user override automatically-calculated checkbox items (linked to activity completion)?
     * @return boolean
     */
    public function is_overrideauto() {
        return $this->overrideauto;
    }

    /**
     * Set whether or not auto checkbox items can be overridden
     * @param boolean $overrideauto
     */
    public function set_overrideauto($overrideauto) {
        $this->overrideauto = $overrideauto;
    }

    /**
     * Should items be checked against groupings, for visibility purposes?
     * @return boolean
     */
    public function is_checkgroupings() {
        return $this->checkgroupings;
    }

    /**
     * Set whether items should be checked against groupings
     * @param boolean $checkgroupings
     */
    public function set_checkgroupings($checkgroupings) {
        $this->checkgroupings = $checkgroupings;
    }

    /**
     * Can the student add their own items?
     * @return boolean
     */
    public function is_canaddown() {
        return $this->canaddown;
    }

    /**
     * Set whether a student can add their own items
     * @param boolean $canaddown
     */
    public function set_canaddown($canaddown) {
        $this->canaddown = $canaddown;
    }

    /**
     * Is the user currently adding/editing their own items?
     * @return boolean
     */
    public function is_addown() {
        return $this->addown;
    }

    /**
     * Set whether the student is adding their own items
     * @param boolean $addown
     */
    public function set_addown($addown) {
        $this->addown = $addown;
    }

    /**
     * Output 'add item' fields after this item.
     * @return int
     */
    public function get_additemafter() {
        return $this->additemafter;
    }

    /**
     * Set the location where we should be adding the next item
     * @param int $additemafter
     */
    public function set_additemafter($additemafter) {
        $this->additemafter = $additemafter;
    }

    /**
     * Should an update form be output?
     * @return boolean
     */
    public function is_updateform() {
        return $this->updateform;
    }

    /**
     * Set whether or not the update form should be shown.
     * @param boolean $updateform
     */
    public function set_updateform($updateform) {
        $this->updateform = $updateform;
    }

    /**
     * Is date editing enabled?
     * @return boolean
     */
    public function is_editdates() {
        return $this->editdates;
    }

    /**
     * Set whether or not date editing is enabled.
     * @param boolean $editdates
     */
    public function set_editdates($editdates) {
        $this->editdates = $editdates;
    }

    /**
     * The ID of the item being edited (to generate the correct URLs).
     * @return int|null
     */
    public function get_itemid() {
        return $this->itemid;
    }

    /**
     * Set the id of the item being edited
     * @param int|null $itemid
     */
    public function set_itemid($itemid) {
        $this->itemid = $itemid;
    }

    /**
     * Is the checklist autopopulated with course activities?
     * @return boolean
     */
    public function is_autopopulate() {
        return $this->autopopulate;
    }

    /**
     * Set whether or not the checklist is autopopulated
     * @param boolean $autopopulate
     */
    public function set_autopopulate($autopopulate) {
        $this->autopopulate = $autopopulate;
    }

    /**
     * Should the autoupdate warning be shown and, if so, what type?
     * @return int|null
     */
    public function get_autoupdatewarning() {
        return $this->autoupdatewarning;
    }

    /**
     * Is there an auto update warning?
     * @return bool
     */
    public function is_autoupdatewarning() {
        return ($this->autoupdatewarning !== null);
    }

    /**
     * Set the auto update warning
     * @param boolean $autoupdatewarning
     */
    public function set_autoupdatewarning($autoupdatewarning) {
        $this->autoupdatewarning = $autoupdatewarning;
    }

    /**
     * Are we editing links?
     * @return boolean
     */
    public function is_editlinks() {
        return $this->editlinks;
    }

    /**
     * Set whether or not we're editing links
     * @param boolean $editlinks
     */
    public function set_editlinks($editlinks) {
        $this->editlinks = $editlinks;
    }

    /**
     * Are course links allowed?
     * @return boolean
     */
    public function is_allowcourselinks() {
        return $this->allowcourselinks;
    }

    /**
     * Set whether or not course links are allowed
     * @param boolean $allowcourselinks
     */
    public function set_allowcourselinks($allowcourselinks) {
        $this->allowcourselinks = $allowcourselinks;
    }

    /**
     * Can we edit the grouping?
     * @return boolean
     */
    public function is_editgrouping() {
        return $this->editgrouping;
    }

    /**
     * Set whether or not we can edit the grouping
     * @param boolean $editgrouping
     */
    public function set_editgrouping($editgrouping) {
        $this->editgrouping = $editgrouping;
    }

    /**
     * Get the current course id
     * @return int
     */
    public function get_courseid() {
        if (!$this->courseid) {
            throw new \coding_exception('No courseid set');
        }
        return $this->courseid;
    }

    /**
     * Set the current course id
     * @param int $courseid
     */
    public function set_courseid($courseid) {
        $this->courseid = $courseid;
    }
}
