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

/**
 * Stores all the functions for manipulating a checklist
 *
 * @author   David Smith <moodle@davosmith.co.uk>
 * @package  mod/checklist
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');

define("CHECKLIST_TEXT_INPUT_WIDTH", 45);
define("CHECKLIST_OPTIONAL_NO", 0);
define("CHECKLIST_OPTIONAL_YES", 1);
define("CHECKLIST_OPTIONAL_HEADING", 2);

define("CHECKLIST_HIDDEN_NO", 0);
define("CHECKLIST_HIDDEN_MANUAL", 1);
define("CHECKLIST_HIDDEN_BYMODULE", 2);

class checklist_class {
    protected $cm;
    protected $course;
    protected $checklist;
    protected $strchecklists;
    protected $strchecklist;
    protected $context;
    protected $userid;
    protected $items;
    protected $useritems;
    protected $useredit;
    protected $additemafter;
    protected $editdates;
    /** @var bool|int[] */
    protected $groupings;

    /**
     * @param int|string $cmid optional
     * @param int $userid optional
     * @param object $checklist optional
     * @param object $cm optional
     * @param object $course optional
     */
    public function __construct($cmid = 'staticonly', $userid = 0, $checklist = null, $cm = null, $course = null) {
        global $COURSE, $DB, $CFG;

        if ($cmid == 'staticonly') {
            // Use static functions only!
            return;
        }

        $this->userid = $userid;

        if ($cm) {
            $this->cm = $cm;
        } else {
            $this->cm = get_coursemodule_from_id('checklist', $cmid, 0, false, MUST_EXIST);
        }

        if ($CFG->version < 2011120100) {
            $this->context = get_context_instance(CONTEXT_MODULE, $this->cm->id);
        } else {
            $this->context = context_module::instance($this->cm->id);
        }

        if ($course) {
            $this->course = $course;
        } else if ($this->cm->course == $COURSE->id) {
            $this->course = $COURSE;
        } else {
            $this->course = $DB->get_record('course', array('id' => $this->cm->course), '*', MUST_EXIST);
        }

        if ($checklist) {
            $this->checklist = $checklist;
        } else {
            $this->checklist = $DB->get_record('checklist', array('id' => $this->cm->instance), '*', MUST_EXIST);
        }

        if (isset($CFG->enablegroupmembersonly) && $CFG->enablegroupmembersonly && $checklist->autopopulate && $userid) {
            $this->groupings = self::get_user_groupings($userid, $this->course->id);
        } else {
            $this->groupings = false;
        }

        $this->strchecklist = get_string('modulename', 'checklist');
        $this->strchecklists = get_string('modulenameplural', 'checklist');
        $this->pagetitle = strip_tags($this->course->shortname.': '.$this->strchecklist.': '.
                                      format_string($this->checklist->name, true));

        $this->get_items();

        if ($this->checklist->autopopulate) {
            $this->update_items_from_course();
        }
    }

    /**
     * Get an array of the items in a checklist
     *
     */
    protected function get_items() {
        global $DB;

        // Load all shared checklist items.
        $this->items = $DB->get_records('checklist_item', array('checklist' => $this->checklist->id, 'userid' => 0), 'position');

        // Makes sure all items are numbered sequentially, starting at 1.
        $this->update_item_positions();

        // Load student's own checklist items.
        if ($this->userid && $this->canaddown()) {
            $this->useritems = $DB->get_records('checklist_item', array(
                'checklist' => $this->checklist->id,
                'userid' => $this->userid
            ), 'position, id');
        } else {
            $this->useritems = false;
        }

        // Load the currently checked-off items.
        if ($this->userid) {
            $sql = 'SELECT i.id, c.usertimestamp, c.teachermark, c.teachertimestamp, c.teacherid
                      FROM {checklist_item} i
                 LEFT JOIN {checklist_check} c ';
            $sql .= 'ON (i.id = c.item AND c.userid = ?) WHERE i.checklist = ? ';

            $checks = $DB->get_records_sql($sql, array($this->userid, $this->checklist->id));

            foreach ($checks as $check) {
                $id = $check->id;

                if (isset($this->items[$id])) {
                    $this->items[$id]->checked = $check->usertimestamp > 0;
                    $this->items[$id]->teachermark = $check->teachermark;
                    $this->items[$id]->usertimestamp = $check->usertimestamp;
                    $this->items[$id]->teachertimestamp = $check->teachertimestamp;
                    $this->items[$id]->teacherid = $check->teacherid;
                } else if ($this->useritems && isset($this->useritems[$id])) {
                    $this->useritems[$id]->checked = $check->usertimestamp > 0;
                    $this->useritems[$id]->usertimestamp = $check->usertimestamp;
                    // User items never have a teacher mark to go with them.
                }
            }
        }
    }

    /**
     * Loop through all activities / resources in course and check they
     * are in the current checklist (in the right order)
     */
    protected function update_items_from_course() {
        global $DB, $CFG;

        $mods = get_fast_modinfo($this->course);

        $section = 1;
        $nextpos = 1;
        $changes = false;
        reset($this->items);

        $importsection = -1;
        if ($this->checklist->autopopulate == CHECKLIST_AUTOPOPULATE_SECTION) {
            foreach ($mods->get_sections() as $num => $section) {
                if (in_array($this->cm->id, $section)) {
                    $importsection = $num;
                    $section = $importsection;
                    break;
                }
            }
        }

        $groupmembersonly = isset($CFG->enablegroupmembersonly) && $CFG->enablegroupmembersonly;

        $numsections = 1;
        $courseformat = null;
        if ($CFG->version >= 2012120300) {
            $courseformat = course_get_format($this->course);
            $opts = $courseformat->get_format_options();
            if (isset($opts['numsections'])) {
                $numsections = $opts['numsections'];
            }
        } else {
            $numsections = $this->course->numsections;
        }
        $sections = $mods->get_sections();
        while ($section <= $numsections || $section == $importsection) {
            if (!array_key_exists($section, $sections)) {
                $section++;
                continue;
            }

            if ($importsection >= 0 && $importsection != $section) {
                $section++; // Only importing the section with the checklist in it.
                continue;
            }

            $sectionheading = 0;
            while (list($itemid, $item) = each($this->items)) {
                // Search from current position.
                if (($item->moduleid == $section) && ($item->itemoptional == CHECKLIST_OPTIONAL_HEADING)) {
                    $sectionheading = $itemid;
                    break;
                }
            }

            if (!$sectionheading) {
                // Search again from the start.
                foreach ($this->items as $item) {
                    if (($item->moduleid == $section) && ($item->itemoptional == CHECKLIST_OPTIONAL_HEADING)) {
                        $sectionheading = $itemid;
                        break;
                    }
                }
                reset($this->items);
            }

            $sectionname = '';
            if ($CFG->version >= 2012120300) {
                $sectionname = $courseformat->get_section_name($section);
            }
            if (trim($sectionname) == '') {
                $sectionname = get_string('section').' '.$section;
            }
            if (!$sectionheading) {
                $sectionheading = $this->additem($sectionname, 0, 0, false, false, $section, CHECKLIST_OPTIONAL_HEADING);
                reset($this->items);
            } else {
                if ($this->items[$sectionheading]->displaytext != $sectionname) {
                    $this->updateitemtext($sectionheading, $sectionname);
                }
            }

            if ($sectionheading) {
                $this->items[$sectionheading]->stillexists = true;

                if ($this->items[$sectionheading]->position < $nextpos) {
                    $this->moveitemto($sectionheading, $nextpos, true);
                    reset($this->items);
                }
                $nextpos = $this->items[$sectionheading]->position + 1;
            }

            foreach ($sections[$section] as $cmid) {
                if ($this->cm->id == $cmid) {
                    continue; // Do not include this checklist in the list of modules.
                }
                if ($mods->get_cm($cmid)->modname == 'label') {
                    continue; // Ignore any labels.
                }

                $foundit = false;
                while (list(, $item) = each($this->items)) {
                    // Search list from current position (will usually be the next item).
                    if (($item->moduleid == $cmid) && ($item->itemoptional != CHECKLIST_OPTIONAL_HEADING)) {
                        $foundit = $item;
                        break;
                    }
                    if (($item->moduleid == 0) && ($item->position == $nextpos)) {
                        // Skip any items that are not linked to modules.
                        $nextpos++;
                    }
                }
                if (!$foundit) {
                    // Search list again from the start (just in case).
                    foreach ($this->items as $item) {
                        if (($item->moduleid == $cmid) && ($item->itemoptional != CHECKLIST_OPTIONAL_HEADING)) {
                            $foundit = $item;
                            break;
                        }
                    }
                    reset($this->items);
                }
                $modname = $mods->get_cm($cmid)->name;
                if ($foundit) {
                    $item->stillexists = true;
                    if ($item->position != $nextpos) {
                        $this->moveitemto($item->id, $nextpos, true);
                        reset($this->items);
                    }
                    if ($item->displaytext != $modname) {
                        $this->updateitemtext($item->id, $modname);
                    }
                    if (($item->hidden == CHECKLIST_HIDDEN_BYMODULE) && $mods->get_cm($cmid)->visible) {
                        // Course module was hidden and now is not.
                        $item->hidden = CHECKLIST_HIDDEN_NO;
                        $upd = new stdClass;
                        $upd->id = $item->id;
                        $upd->hidden = $item->hidden;
                        $DB->update_record('checklist_item', $upd);
                        $changes = true;

                    } else if (($item->hidden == CHECKLIST_HIDDEN_NO) && !$mods->get_cm($cmid)->visible) {
                        // Course module is now hidden.
                        $item->hidden = CHECKLIST_HIDDEN_BYMODULE;
                        $upd = new stdClass;
                        $upd->id = $item->id;
                        $upd->hidden = $item->hidden;
                        $DB->update_record('checklist_item', $upd);
                        $changes = true;
                    }

                    $groupingid = $mods->get_cm($cmid)->groupingid;
                    if ($groupmembersonly && $groupingid && $mods->get_cm($cmid)->groupmembersonly) {
                        if ($item->grouping != $groupingid) {
                            $item->grouping = $groupingid;
                            $upd = new stdClass;
                            $upd->id = $item->id;
                            $upd->grouping = $groupingid;
                            $DB->update_record('checklist_item', $upd);
                            $changes = true;
                        }
                    } else {
                        if ($item->grouping) {
                            $item->grouping = 0;
                            $upd = new stdClass;
                            $upd->id = $item->id;
                            $upd->grouping = 0;
                            $DB->update_record('checklist_item', $upd);
                            $changes = true;
                        }
                    }
                } else {
                    $hidden = $mods->get_cm($cmid)->visible ? CHECKLIST_HIDDEN_NO : CHECKLIST_HIDDEN_BYMODULE;
                    $itemid = $this->additem($modname, 0, 0, $nextpos, false, $cmid, CHECKLIST_OPTIONAL_NO, $hidden);
                    $changes = true;
                    reset($this->items);
                    $this->items[$itemid]->stillexists = true;
                    $usegrouping = $groupmembersonly && $mods->get_cm($cmid)->groupmembersonly;
                    $this->items[$itemid]->grouping = $usegrouping ? $mods->get_cm($cmid)->groupingid : 0;
                    $item = $this->items[$itemid];
                }
                $item->modulelink = new moodle_url('/mod/'.$mods->get_cm($cmid)->modname.'/view.php', array('id' => $cmid));
                $nextpos++;
            }

            $section++;
        }

        // Delete any items that are related to activities / resources that have been deleted.
        if ($this->items) {
            foreach ($this->items as $item) {
                if ($item->moduleid && !isset($item->stillexists)) {
                    $this->deleteitem($item->id, true);
                    $changes = true;
                }
            }
        }

        if ($changes) {
            $this->update_all_autoupdate_checks();
        }
    }

    protected function removeauto() {
        if ($this->checklist->autopopulate) {
            return; // Still automatically populating the checklist, so don't remove the items.
        }

        if (!$this->canedit()) {
            return;
        }

        if ($this->items) {
            foreach ($this->items as $item) {
                if ($item->moduleid) {
                    $this->deleteitem($item->id);
                }
            }
        }
    }

    /**
     * Check all items are numbered sequentially from 1
     * then, move any items between $start and $end
     * the number of places indicated by $move
     *
     * @param int $move (optional) - how far to offset the current positions
     * @param int $start (optional) - where to start offsetting positions
     * @param bool $end (optional) - where to stop offsetting positions
     */
    protected function update_item_positions($move = 0, $start = 1, $end = false) {
        global $DB;

        $pos = 1;

        if (!$this->items) {
            return;
        }
        foreach ($this->items as $item) {
            if ($pos == $start) {
                $pos += $move;
                $start = -1;
            }
            if ($item->position != $pos) {
                $oldpos = $item->position;
                $item->position = $pos;
                $upditem = new stdClass;
                $upditem->id = $item->id;
                $upditem->position = $pos;
                $DB->update_record('checklist_item', $upditem);
                if ($oldpos == $end) {
                    break;
                }
            }
            $pos++;
        }
    }

    /**
     * @param int $position
     * @return bool|object
     */
    protected function get_item_at_position($position) {
        if (!$this->items) {
            return false;
        }
        foreach ($this->items as $item) {
            if ($item->position == $position) {
                return $item;
            }
        }
        return false;
    }

    protected function canupdateown() {
        global $USER;
        return (!$this->userid || ($this->userid == $USER->id)) && has_capability('mod/checklist:updateown', $this->context);
    }

    protected function canaddown() {
        global $USER;
        return $this->checklist->useritemsallowed
        && (!$this->userid || ($this->userid == $USER->id)) && has_capability('mod/checklist:updateown', $this->context);
    }

    protected function canpreview() {
        return has_capability('mod/checklist:preview', $this->context);
    }

    protected function canedit() {
        return has_capability('mod/checklist:edit', $this->context);
    }

    protected function caneditother() {
        return has_capability('mod/checklist:updateother', $this->context);
    }

    protected function canviewreports() {
        return has_capability('mod/checklist:viewreports', $this->context)
        || has_capability('mod/checklist:viewmenteereports', $this->context);
    }

    protected function only_view_mentee_reports() {
        return has_capability('mod/checklist:viewmenteereports', $this->context)
        && !has_capability('mod/checklist:viewreports', $this->context);
    }

    /**
     * Test if the current user is a mentor of the passed in user id.
     *
     * @param int $userid
     * @return bool
     */
    public static function is_mentor($userid) {
        global $USER, $DB;

        $sql = 'SELECT c.instanceid
                  FROM {role_assignments} ra
                  JOIN {context} c ON ra.contextid = c.id
                 WHERE c.contextlevel = '.CONTEXT_USER.'
                   AND ra.userid = ?
                   AND c.instanceid = ?';
        return $DB->record_exists_sql($sql, array($USER->id, $userid));
    }

    /**
     * Takes a list of userids and returns only those that the current user
     * is a mentor for (ones where the current user is assigned a role in their
     * user context)
     *
     * @param int[] $userids
     * @return int[]
     */
    public static function filter_mentee_users($userids) {
        global $DB, $USER;

        list($usql, $uparams) = $DB->get_in_or_equal($userids);
        $sql = 'SELECT c.instanceid
                  FROM {role_assignments} ra
                  JOIN {context} c ON ra.contextid = c.id
                 WHERE c.contextlevel = '.CONTEXT_USER.'
                   AND ra.userid = ?
                   AND c.instanceid '.$usql;
        $params = array_merge(array($USER->id), $uparams);
        return $DB->get_fieldset_sql($sql, $params);
    }

    public function view() {
        global $OUTPUT, $CFG;

        if ((!$this->items) && $this->canedit()) {
            redirect(new moodle_url('/mod/checklist/edit.php', array('id' => $this->cm->id)));
        }

        if ($this->canupdateown()) {
            $currenttab = 'view';
        } else if ($this->canpreview()) {
            $currenttab = 'preview';
        } else {
            if ($this->canviewreports()) { // No editing, but can view reports.
                redirect(new moodle_url('/mod/checklist/report.php', array('id' => $this->cm->id)));
            } else {
                $this->view_header();

                echo $OUTPUT->heading(format_string($this->checklist->name));
                echo $OUTPUT->confirm('<p>'.get_string('guestsno', 'checklist')."</p>\n\n<p>".
                                      get_string('liketologin')."</p>\n", get_login_url(), get_referer(false));
                echo $OUTPUT->footer();
                die;
            }
            $currenttab = '';
        }

        $this->view_header();

        echo $OUTPUT->heading(format_string($this->checklist->name));

        $this->view_tabs($currenttab);

        if ($CFG->version > 2014051200) { // Moodle 2.7+.
            $params = array(
                'contextid' => $this->context->id,
                'objectid' => $this->checklist->id,
            );
            $event = \mod_checklist\event\course_module_viewed::create($params);
            $event->trigger();
        } else { // Before Moodle 2.7.
            add_to_log($this->course->id, 'checklist', 'view', "view.php?id={$this->cm->id}", $this->checklist->id, $this->cm->id);
        }

        if ($this->canupdateown()) {
            $this->process_view_actions();
        }

        $this->view_items();

        $this->view_footer();
    }

    public function edit() {
        global $OUTPUT, $CFG;

        if (!$this->canedit()) {
            redirect(new moodle_url('/mod/checklist/view.php', array('id' => $this->cm->id)));
        }

        if ($CFG->version > 2014051200) { // Moodle 2.7+.
            $params = array(
                'contextid' => $this->context->id,
                'objectid' => $this->checklist->id,
            );
            $event = \mod_checklist\event\edit_page_viewed::create($params);
            $event->trigger();
        } else { // Before Moodle 2.7.
            add_to_log($this->course->id, "checklist", "edit", "edit.php?id={$this->cm->id}", $this->checklist->id, $this->cm->id);
        }

        $this->view_header();

        echo $OUTPUT->heading(format_string($this->checklist->name));

        $this->view_tabs('edit');

        $this->process_edit_actions();

        if ($this->checklist->autopopulate) {
            // Needs to be done again, just in case the edit actions have changed something.
            $this->update_items_from_course();
        }

        $this->view_import_export();

        $this->view_edit_items();

        $this->view_footer();
    }

    public function report() {
        global $OUTPUT, $CFG;

        if ((!$this->items) && $this->canedit()) {
            redirect(new moodle_url('/mod/checklist/edit.php', array('id' => $this->cm->id)));
        }

        if (!$this->canviewreports()) {
            redirect(new moodle_url('/mod/checklist/view.php', array('id' => $this->cm->id)));
        }

        if ($this->userid && $this->only_view_mentee_reports()) {
            // Check this user is a mentee of the logged in user.
            if (!self::is_mentor($this->userid)) {
                $this->userid = false;
            }

        } else if (!$this->caneditother()) {
            $this->userid = false;
        }

        $this->view_header();

        echo $OUTPUT->heading(format_string($this->checklist->name));

        $this->view_tabs('report');

        $this->process_report_actions();

        if ($CFG->version > 2014051200) { // Moodle 2.7+.
            $params = array(
                'contextid' => $this->context->id,
                'objectid' => $this->checklist->id,
            );
            if ($this->userid) {
                $params['relateduserid'] = $this->userid;
            }
            $event = \mod_checklist\event\report_viewed::create($params);
            $event->trigger();
        } else { // Before Moodle 2.7.
            $url = "report.php?id={$this->cm->id}";
            if ($this->userid) {
                $url .= "&studentid={$this->userid}";
            }
            add_to_log($this->course->id, "checklist", "report", $url, $this->checklist->id, $this->cm->id);
        }

        if ($this->userid) {
            $this->view_items(true);
        } else {
            $this->view_report();
        }

        $this->view_footer();
    }

    public function user_complete() {
        $this->view_items(false, true);
    }

    protected function view_header() {
        global $PAGE, $OUTPUT;

        $PAGE->set_title($this->pagetitle);
        $PAGE->set_heading($this->course->fullname);

        echo $OUTPUT->header();
    }

    protected function view_tabs($currenttab) {
        $tabs = array();
        $row = array();
        $inactive = array();
        $activated = array();

        if ($this->canupdateown()) {
            $row[] = new tabobject('view', new moodle_url('/mod/checklist/view.php', array('id' => $this->cm->id)),
                                   get_string('view', 'checklist'));
        } else if ($this->canpreview()) {
            $row[] = new tabobject('preview', new moodle_url('/mod/checklist/view.php', array('id' => $this->cm->id)),
                                   get_string('preview', 'checklist'));
        }
        if ($this->canviewreports()) {
            $row[] = new tabobject('report', new moodle_url('/mod/checklist/report.php', array('id' => $this->cm->id)),
                                   get_string('report', 'checklist'));
        }
        if ($this->canedit()) {
            $row[] = new tabobject('edit', new moodle_url('/mod/checklist/edit.php', array('id' => $this->cm->id)),
                                   get_string('edit', 'checklist'));
        }

        if (count($row) > 1) { // No tabs for students.
            $tabs[] = $row;
        }

        if ($currenttab == 'report') {
            $activated[] = 'report';
        }

        if ($currenttab == 'edit') {
            $activated[] = 'edit';

            if (!$this->items) {
                $inactive = array('view', 'report', 'preview');
            }
        }

        if ($currenttab == 'preview') {
            $activated[] = 'preview';
        }

        print_tabs($tabs, $currenttab, $inactive, $activated);
    }

    protected function view_progressbar() {
        global $OUTPUT;

        if (!$this->items) {
            return;
        }

        $teacherprogress = ($this->checklist->teacheredit != CHECKLIST_MARKING_STUDENT);

        $totalitems = 0;
        $requireditems = 0;
        $completeitems = 0;
        $allcompleteitems = 0;
        $checkgroupings = $this->checklist->autopopulate && ($this->groupings !== false);
        foreach ($this->items as $item) {
            if (($item->itemoptional == CHECKLIST_OPTIONAL_HEADING) || ($item->hidden)) {
                continue;
            }
            if ($checkgroupings && !empty($item->grouping)) {
                if (!in_array($item->grouping, $this->groupings)) {
                    continue; // Current user is not a member of this item's grouping.
                }
            }
            if ($item->itemoptional == CHECKLIST_OPTIONAL_NO) {
                $requireditems++;
                if ($teacherprogress) {
                    if ($item->teachermark == CHECKLIST_TEACHERMARK_YES) {
                        $completeitems++;
                        $allcompleteitems++;
                    }
                } else if ($item->checked) {
                    $completeitems++;
                    $allcompleteitems++;
                }
            } else if ($teacherprogress) {
                if ($item->teachermark == CHECKLIST_TEACHERMARK_YES) {
                    $allcompleteitems++;
                }
            } else if ($item->checked) {
                $allcompleteitems++;
            }
            $totalitems++;
        }
        if (!$teacherprogress) {
            if ($this->useritems) {
                foreach ($this->useritems as $item) {
                    if ($item->checked) {
                        $allcompleteitems++;
                    }
                    $totalitems++;
                }
            }
        }
        if ($totalitems == 0) {
            return;
        }

        $allpercentcomplete = ($allcompleteitems * 100) / $totalitems;

        if ($requireditems > 0 && $totalitems > $requireditems) {
            $percentcomplete = ($completeitems * 100) / $requireditems;
            echo '<div style="display:block; float:left; width:150px;" class="checklist_progress_heading">';
            echo get_string('percentcomplete', 'checklist').':&nbsp;';
            echo '</div>';
            echo '<span id="checklistprogressrequired">';
            echo '<div class="checklist_progress_outer">';
            echo '<div class="checklist_progress_inner" style="width:'.$percentcomplete.'%; background-image: url('.
                $OUTPUT->pix_url('progress', 'checklist').');" >&nbsp;</div>';
            echo '<div class="checklist_progress_anim" style="width:'.$percentcomplete.'%; background-image: url('.
                $OUTPUT->pix_url('progress-fade', 'checklist').');" >&nbsp;</div>';
            echo '</div>';
            echo '<span class="checklist_progress_percent">&nbsp;'.sprintf('%0d', $percentcomplete).'% </span>';
            echo '</span>';
            echo '<br style="clear:both"/>';
        }

        echo '<div style="display:block; float:left; width:150px;" class="checklist_progress_heading">';
        echo get_string('percentcompleteall', 'checklist').':&nbsp;';
        echo '</div>';
        echo '<span id="checklistprogressall">';
        echo '<div class="checklist_progress_outer">';
        echo '<div class="checklist_progress_inner" style="width:'.$allpercentcomplete.'%; background-image: url('.
            $OUTPUT->pix_url('progress', 'checklist').');" >&nbsp;</div>';
        echo '<div class="checklist_progress_anim" style="width:'.$allpercentcomplete.'%; background-image: url('.
            $OUTPUT->pix_url('progress-fade', 'checklist').');" >&nbsp;</div>';
        echo '</div>';
        echo '<span class="checklist_progress_percent">&nbsp;'.sprintf('%0d', $allpercentcomplete).'% </span>';
        echo '</span>';
        echo '<br style="clear:both"/>';
    }

    protected function get_teachermark($itemid) {
        global $OUTPUT;

        if (!isset($this->items[$itemid])) {
            return array('', '');
        }
        switch ($this->items[$itemid]->teachermark) {
            case CHECKLIST_TEACHERMARK_YES:
                return array(
                    $OUTPUT->pix_url('tick_box', 'checklist'),
                    get_string('teachermarkyes', 'checklist'),
                    'teachermarkyes'
                );

            case CHECKLIST_TEACHERMARK_NO:
                return array(
                    $OUTPUT->pix_url('cross_box', 'checklist'),
                    get_string('teachermarkno', 'checklist'),
                    'teachermarkno'
                );

            default:
                return array(
                    $OUTPUT->pix_url('empty_box', 'checklist'),
                    get_string('teachermarkundecided', 'checklist'),
                    'teachermarkundecided'
                );
        }
    }

    protected function view_items($viewother = false, $userreport = false) {
        global $DB, $OUTPUT, $PAGE, $CFG;

        echo $OUTPUT->box_start('generalbox boxwidthwide boxaligncenter checklistbox');

        echo html_writer::tag('div', '&nbsp;', array('id' => 'checklistspinner'));

        $comments = $this->checklist->teachercomments;
        $editcomments = false;
        $thispage = new moodle_url('/mod/checklist/view.php', array('id' => $this->cm->id));

        $teachermarklocked = false;
        $showcompletiondates = false;
        $strteachername = '';
        $struserdate = '';
        $strteacherdate = '';
        if ($viewother) {
            if ($comments) {
                $editcomments = optional_param('editcomments', false, PARAM_BOOL);
            }
            $thispage = new moodle_url('/mod/checklist/report.php', array('id' => $this->cm->id, 'studentid' => $this->userid));

            if (!$student = $DB->get_record('user', array('id' => $this->userid))) {
                error('No such user!');
            }

            echo '<h2>'.get_string('checklistfor', 'checklist').' '.fullname($student, true).'</h2>';
            echo '&nbsp;';
            echo '<form style="display: inline;" action="'.$thispage->out_omit_querystring().'" method="get">';
            echo html_writer::input_hidden_params($thispage, array('studentid'));
            echo '<input type="submit" name="viewall" value="'.get_string('viewall', 'checklist').'" />';
            echo '</form>';

            if (!$editcomments) {
                echo '<form style="display: inline;" action="'.$thispage->out_omit_querystring().'" method="get">';
                echo html_writer::input_hidden_params($thispage);
                echo '<input type="hidden" name="editcomments" value="on" />';
                echo ' <input type="submit" name="viewall" value="'.get_string('addcomments', 'checklist').'" />';
                echo '</form>';
            }
            echo '<form style="display: inline;" action="'.$thispage->out_omit_querystring().'" method="get">';
            echo html_writer::input_hidden_params($thispage);
            echo '<input type="hidden" name="sesskey" value="'.sesskey().'" />';
            echo '<input type="hidden" name="action" value="toggledates" />';
            echo ' <input type="submit" name="toggledates" value="'.get_string('toggledates', 'checklist').'" />';
            echo '</form>';

            $teachermarklocked = $this->checklist->lockteachermarks
                && !has_capability('mod/checklist:updatelocked', $this->context);

            $reportsettings = $this->get_report_settings();
            $showcompletiondates = $reportsettings->showcompletiondates;

            $strteacherdate = get_string('teacherdate', 'mod_checklist');
            $struserdate = get_string('userdate', 'mod_checklist');
            $strteachername = get_string('teacherid', 'mod_checklist');

            if ($showcompletiondates) {
                $teacherids = array();
                foreach ($this->items as $item) {
                    if ($item->teacherid) {
                        $teacherids[$item->teacherid] = $item->teacherid;
                    }
                }
                if ($CFG->version < 2013111800) {
                    $fields = 'firstname, lastname';
                } else {
                    $fields = get_all_user_name_fields(true);
                }
                $teachers = $DB->get_records_list('user', 'id', $teacherids, '', 'id, '.$fields);
                foreach ($this->items as $item) {
                    if (isset($teachers[$item->teacherid])) {
                        $item->teachername = fullname($teachers[$item->teacherid]);
                    } else {
                        $item->teachername = false;
                    }
                }
            }
        }

        $intro = file_rewrite_pluginfile_urls($this->checklist->intro, 'pluginfile.php', $this->context->id,
                                              'mod_checklist', 'intro', null);
        $opts = array('trusted' => $CFG->enabletrusttext);
        echo format_text($intro, $this->checklist->introformat, $opts);
        echo '<br/>';

        $showteachermark = false;
        $showcheckbox = true;
        if ($this->canupdateown() || $viewother || $userreport) {
            $this->view_progressbar();
            $showteachermark = ($this->checklist->teacheredit == CHECKLIST_MARKING_TEACHER)
                || ($this->checklist->teacheredit == CHECKLIST_MARKING_BOTH);
            $showcheckbox = ($this->checklist->teacheredit == CHECKLIST_MARKING_STUDENT)
                || ($this->checklist->teacheredit == CHECKLIST_MARKING_BOTH);
            $teachermarklocked = $teachermarklocked && $showteachermark; // Make sure this is OFF, if not showing teacher marks.
        }
        $overrideauto = ($this->checklist->autoupdate != CHECKLIST_AUTOUPDATE_YES);
        $checkgroupings = $this->checklist->autopopulate && ($this->groupings !== false);

        if (!$this->items) {
            print_string('noitems', 'checklist');
        } else {
            $focusitem = false;
            $updateform = ($showcheckbox && $this->canupdateown() && !$viewother && !$userreport)
                || ($viewother && ($showteachermark || $editcomments));
            $addown = $this->canaddown() && $this->useredit;
            if ($updateform) {
                if ($this->canaddown() && !$viewother) {
                    echo '<form style="display:inline;" action="'.$thispage->out_omit_querystring().'" method="get">';
                    echo html_writer::input_hidden_params($thispage);
                    if ($addown) {
                        // Switch on for any other forms on this page (but off if this form submitted).
                        $thispage->param('useredit', 'on');
                        echo '<input type="submit" name="submit" value="'.get_string('addownitems-stop', 'checklist').'" />';
                    } else {
                        echo '<input type="hidden" name="useredit" value="on" />';
                        echo '<input type="submit" name="submit" value="'.get_string('addownitems', 'checklist').'" />';
                    }
                    echo '</form>';
                }

                if (!$viewother) {
                    // Load the Javascript required to send changes back to the server (without clicking 'save')
                    if ($CFG->version < 2012120300) { // < Moodle 2.4.
                        $jsmodule = array(
                            'name' => 'mod_checklist',
                            'fullpath' => new moodle_url('/mod/checklist/updatechecks.js')
                        );
                        $PAGE->requires->yui2_lib('dom');
                        $PAGE->requires->yui2_lib('event');
                        $PAGE->requires->yui2_lib('connection');
                        $PAGE->requires->yui2_lib('animation');
                    } else {
                        $jsmodule = array(
                            'name' => 'mod_checklist',
                            'fullpath' => new moodle_url('/mod/checklist/updatechecks24.js')
                        );
                    }
                    $updatechecksurl = new moodle_url('/mod/checklist/updatechecks.php');
                    $updateprogress = $showteachermark ? 0 : 1; // Progress bars should be updated on 'student only' checklists.
                    $PAGE->requires->js_init_call('M.mod_checklist.init', array(
                        $updatechecksurl->out(), sesskey(), $this->cm->id, $updateprogress
                    ), true, $jsmodule);
                }

                echo '<form action="'.$thispage->out_omit_querystring().'" method="post">';
                echo html_writer::input_hidden_params($thispage);
                echo '<input type="hidden" name="action" value="updatechecks" />';
                echo '<input type="hidden" name="sesskey" value="'.sesskey().'" />';
            }

            if ($this->useritems) {
                reset($this->useritems);
            }

            $commentusers = array();
            if ($comments) {
                list($isql, $iparams) = $DB->get_in_or_equal(array_keys($this->items));
                $params = array_merge(array($this->userid), $iparams);
                $commentsunsorted = $DB->get_records_select('checklist_comment', "userid = ? AND itemid $isql", $params);
                $commentuserids = array();
                if (!empty($commentsunsorted)) {
                    $comments = array();
                    foreach ($commentsunsorted as $comment) {
                        $comments[$comment->itemid] = $comment;
                        if ($comment->commentby) {
                            $commentuserids[] = $comment->commentby;
                        }
                    }
                    if (!empty($commentuserids)) {
                        list($csql, $cparams) = $DB->get_in_or_equal(array_unique($commentuserids, SORT_NUMERIC));
                        $commentusers = $DB->get_records_select('user', 'id '.$csql, $cparams);
                    }
                } else {
                    $comments = false;
                }
            }

            if ($teachermarklocked) {
                echo '<p style="checklistwarning">'.get_string('lockteachermarkswarning', 'checklist').'</p>';
            }

            echo '<ol class="checklist" id="checklistouter">';
            $currindent = 0;
            foreach ($this->items as $item) {

                if ($item->hidden) {
                    continue;
                }

                if ($checkgroupings && !empty($item->grouping)) {
                    if (!in_array($item->grouping, $this->groupings)) {
                        continue; // Current user is not a member of this item's grouping, so skip.
                    }
                }

                while ($item->indent > $currindent) {
                    $currindent++;
                    echo '<ol class="checklist">';
                }
                while ($item->indent < $currindent) {
                    $currindent--;
                    echo '</ol>';
                }
                $itemname = '"item'.$item->id.'"';
                $checked = (($updateform || $viewother || $userreport) && $item->checked) ? ' checked="checked" ' : '';
                if ($viewother || $userreport) {
                    $checked .= ' disabled="disabled" ';
                } else if (!$overrideauto && $item->moduleid) {
                    $checked .= ' disabled="disabled" ';
                }
                switch ($item->colour) {
                    case 'red':
                        $itemcolour = 'itemred';
                        break;
                    case 'orange':
                        $itemcolour = 'itemorange';
                        break;
                    case 'green':
                        $itemcolour = 'itemgreen';
                        break;
                    case 'purple':
                        $itemcolour = 'itempurple';
                        break;
                    default:
                        $itemcolour = 'itemblack';
                }

                $checkclass = '';
                if ($item->itemoptional == CHECKLIST_OPTIONAL_HEADING) {
                    $optional = ' class="itemheading '.$itemcolour.'" ';
                } else if ($item->itemoptional == CHECKLIST_OPTIONAL_YES) {
                    $optional = ' class="itemoptional '.$itemcolour.'" ';
                    $checkclass = ' itemoptional';
                } else {
                    $optional = ' class="'.$itemcolour.'" ';
                }

                echo '<li>';
                if ($showteachermark) {
                    if ($item->itemoptional != CHECKLIST_OPTIONAL_HEADING) {
                        if ($viewother) {
                            $lock = ($teachermarklocked && $item->teachermark == CHECKLIST_TEACHERMARK_YES);
                            $disabled = $lock ? 'disabled="disabled" ' : '';

                            $selu = ($item->teachermark == CHECKLIST_TEACHERMARK_UNDECIDED) ? 'selected="selected" ' : '';
                            $sely = ($item->teachermark == CHECKLIST_TEACHERMARK_YES) ? 'selected="selected" ' : '';
                            $seln = ($item->teachermark == CHECKLIST_TEACHERMARK_NO) ? 'selected="selected" ' : '';

                            $selectid = ' id='.$itemname.' ';

                            echo '<select name="items['.$item->id.']" '.$disabled.$selectid.'>';
                            echo '<option value="'.CHECKLIST_TEACHERMARK_UNDECIDED.'" '.$selu.'></option>';
                            echo '<option value="'.CHECKLIST_TEACHERMARK_YES.'" '.$sely.'>'.get_string('yes').'</option>';
                            echo '<option value="'.CHECKLIST_TEACHERMARK_NO.'" '.$seln.'>'.get_string('no').'</option>';
                            echo '</select>';
                        } else {
                            list($imgsrc, $titletext, $class) = $this->get_teachermark($item->id);
                            echo '<img src="'.$imgsrc.'" alt="'.$titletext.'" title="'.$titletext.'" class="'.$class.'" />';
                        }
                    }
                }
                if ($showcheckbox) {
                    if ($item->itemoptional != CHECKLIST_OPTIONAL_HEADING) {
                        $id = ' id='.$itemname.' ';
                        if ($viewother && $showteachermark) {
                            $id = '';
                        }
                        echo '<input class="checklistitem'.$checkclass.'" type="checkbox" name="items[]" '.$id.$checked.
                            ' value="'.$item->id.'" />';
                    }
                }
                echo '<label for='.$itemname.$optional.'>'.format_string($item->displaytext).'</label>';
                if (isset($item->modulelink)) {
                    echo '&nbsp;<a href="'.$item->modulelink.'"><img src="'.$OUTPUT->pix_url('follow_link', 'checklist').'" alt="'.
                        get_string('linktomodule', 'checklist').'" /></a>';
                }

                if ($addown) {
                    echo '&nbsp;<a href="'.$thispage->out(true, array(
                            'itemid' => $item->id, 'sesskey' => sesskey(), 'action' => 'startadditem'
                        )).'">';
                    $title = '"'.get_string('additemalt', 'checklist').'"';
                    echo '<img src="'.$OUTPUT->pix_url('add', 'checklist').'" alt='.$title.' title='.$title.' /></a>';
                }

                if ($item->duetime) {
                    if ($item->duetime > time()) {
                        echo '<span class="checklist-itemdue"> '.userdate($item->duetime, get_string('strftimedate')).'</span>';
                    } else {
                        echo '<span class="checklist-itemoverdue"> '.userdate($item->duetime, get_string('strftimedate')).'</span>';
                    }
                }

                if ($showcompletiondates) {
                    if ($item->itemoptional != CHECKLIST_OPTIONAL_HEADING) {
                        if ($showteachermark && $item->teachermark != CHECKLIST_TEACHERMARK_UNDECIDED && $item->teachertimestamp) {
                            if ($item->teachername) {
                                echo '<span class="itemteachername" title="'.$strteachername.'">'.$item->teachername.'</span>';
                            }
                            echo '<span class="itemteacherdate" title="'.$strteacherdate.'">'.
                                userdate($item->teachertimestamp, get_string('strftimedatetimeshort')).'</span>';
                        }
                        if ($showcheckbox && $item->checked && $item->usertimestamp) {
                            echo '<span class="itemuserdate" title="'.$struserdate.'">'.
                                userdate($item->usertimestamp, get_string('strftimedatetimeshort')).'</span>';
                        }
                    }
                }

                $foundcomment = false;
                if ($comments) {
                    if (array_key_exists($item->id, $comments)) {
                        $comment = $comments[$item->id];
                        $foundcomment = true;
                        echo ' <span class="teachercomment">&nbsp;';
                        if ($comment->commentby) {
                            $userurl = new moodle_url('/user/view.php', array(
                                'id' => $comment->commentby, 'course' => $this->course->id
                            ));
                            echo '<a href="'.$userurl.'">'.fullname($commentusers[$comment->commentby]).'</a>: ';
                        }
                        if ($editcomments) {
                            $outid = '';
                            if (!$focusitem) {
                                $focusitem = 'firstcomment';
                                $outid = ' id="firstcomment" ';
                            }
                            echo '<input type="text" name="teachercomment['.$item->id.']" value="'.s($comment->text).
                                '" '.$outid.'/>';
                        } else {
                            echo s($comment->text);
                        }
                        echo '&nbsp;</span>';
                    }
                }
                if (!$foundcomment && $editcomments) {
                    echo '&nbsp;<input type="text" name="teachercomment['.$item->id.']" />';
                }

                echo '</li>';

                // Output any user-added items.
                if ($this->useritems) {
                    $useritem = current($this->useritems);

                    if ($useritem && ($useritem->position == $item->position)) {
                        $thisitemurl = clone $thispage;
                        $thisitemurl->param('action', 'updateitem');
                        $thisitemurl->param('sesskey', sesskey());

                        echo '<ol class="checklist">';
                        while ($useritem && ($useritem->position == $item->position)) {
                            $itemname = '"item'.$useritem->id.'"';
                            $checked = ($updateform && $useritem->checked) ? ' checked="checked" ' : '';
                            if (isset($useritem->editme)) {
                                $itemtext = explode("\n", $useritem->displaytext, 2);
                                $itemtext[] = '';
                                $text = $itemtext[0];
                                $note = $itemtext[1];
                                $thisitemurl->param('itemid', $useritem->id);

                                echo '<li>';
                                echo '<div style="float: left;">';
                                if ($showcheckbox) {
                                    echo '<input class="checklistitem itemoptional" type="checkbox" name="items[]" id='.
                                        $itemname.$checked.' disabled="disabled" value="'.$useritem->id.'" />';
                                }
                                echo '<form style="display:inline" action="'.$thisitemurl->out_omit_querystring().
                                    '" method="post">';
                                echo html_writer::input_hidden_params($thisitemurl);
                                echo '<input type="text" size="'.CHECKLIST_TEXT_INPUT_WIDTH.'" name="displaytext" value="'.s($text).
                                    '" id="updateitembox" />';
                                echo '<input type="submit" name="updateitem" value="'.get_string('updateitem', 'checklist').'" />';
                                echo '<br />';
                                echo '<textarea name="displaytextnote" rows="3" cols="25">'.s($note).'</textarea>';
                                echo '</form>';
                                echo '</div>';

                                echo '<form style="display:inline;" action="'.$thispage->out_omit_querystring().'" method="get">';
                                echo html_writer::input_hidden_params($thispage);
                                echo '<input type="submit" name="canceledititem" value="'.
                                    get_string('canceledititem', 'checklist').'" />';
                                echo '</form>';
                                echo '<br style="clear: both;" />';
                                echo '</li>';

                                $focusitem = 'updateitembox';
                            } else {
                                echo '<li>';
                                if ($showcheckbox) {
                                    echo '<input class="checklistitem itemoptional" type="checkbox" name="items[]" id='.
                                        $itemname.$checked.' value="'.$useritem->id.'" />';
                                }
                                $splittext = explode("\n", s($useritem->displaytext), 2);
                                $splittext[] = '';
                                $text = $splittext[0];
                                $note = str_replace("\n", '<br />', $splittext[1]);
                                echo '<label class="useritem" for='.$itemname.'>'.$text.'</label>';

                                if ($addown) {
                                    $baseurl = $thispage.'&amp;itemid='.$useritem->id.'&amp;sesskey='.sesskey().'&amp;action=';
                                    echo '&nbsp;<a href="'.$baseurl.'edititem">';
                                    $title = '"'.get_string('edititem', 'checklist').'"';
                                    echo '<img src="'.$OUTPUT->pix_url('/t/edit').'" alt='.$title.' title='.$title.' /></a>';

                                    echo '&nbsp;<a href="'.$baseurl.'deleteitem" class="deleteicon">';
                                    $title = '"'.get_string('deleteitem', 'checklist').'"';
                                    echo '<img src="'.$OUTPUT->pix_url('remove', 'checklist').'" alt='.$title.' title='.$title.
                                        ' /></a>';
                                }
                                if ($note != '') {
                                    echo '<div class="note">'.$note.'</div>';
                                }

                                echo '</li>';
                            }
                            $useritem = next($this->useritems);
                        }
                        echo '</ol>';
                    }
                }

                if ($addown && ($item->id == $this->additemafter)) {
                    $thisitemurl = clone $thispage;
                    $thisitemurl->param('action', 'additem');
                    $thisitemurl->param('position', $item->position);
                    $thisitemurl->param('sesskey', sesskey());

                    echo '<ol class="checklist"><li>';
                    echo '<div style="float: left;">';
                    echo '<form action="'.$thispage->out_omit_querystring().'" method="post">';
                    echo html_writer::input_hidden_params($thisitemurl);
                    if ($showcheckbox) {
                        echo '<input type="checkbox" disabled="disabled" />';
                    }
                    echo '<input type="text" size="'.CHECKLIST_TEXT_INPUT_WIDTH.'" name="displaytext" value="" id="additembox" />';
                    echo '<input type="submit" name="additem" value="'.get_string('additem', 'checklist').'" />';
                    echo '<br />';
                    echo '<textarea name="displaytextnote" rows="3" cols="25"></textarea>';
                    echo '</form>';
                    echo '</div>';

                    echo '<form style="display:inline" action="'.$thispage->out_omit_querystring().'" method="get">';
                    echo html_writer::input_hidden_params($thispage);
                    echo '<input type="submit" name="canceledititem" value="'.get_string('canceledititem', 'checklist').'" />';
                    echo '</form>';
                    echo '<br style="clear: both;" />';
                    echo '</li></ol>';

                    if (!$focusitem) {
                        $focusitem = 'additembox';
                    }
                }
            }
            echo '</ol>';

            if ($updateform) {
                echo '<input id="checklistsavechecks" type="submit" name="submit" value="'.
                    get_string('savechecks', 'checklist').'" />';
                if ($viewother) {
                    echo '&nbsp;<input type="submit" name="save" value="'.get_string('savechecks', 'mod_checklist').'" />';
                    echo '&nbsp;<input type="submit" name="savenext" value="'.get_string('saveandnext').'" />';
                    echo '&nbsp;<input type="submit" name="viewnext" value="'.get_string('next').'" />';
                }
                echo '</form>';
            }

            if ($focusitem) {
                echo '<script type="text/javascript">document.getElementById("'.$focusitem.'").focus();</script>';
            }

            if ($addown) {
                echo '<script type="text/javascript">';
                echo 'function confirmdelete(url) {';
                echo 'if (confirm("'.get_string('confirmdeleteitem', 'checklist').'")) { window.location = url; } ';
                echo '} ';
                echo 'var links = document.getElementById("checklistouter").getElementsByTagName("a"); ';
                echo 'for (var i in links) { ';
                echo 'if (links[i].className == "deleteicon") { ';
                echo 'var url = links[i].href;';
                echo 'links[i].href = "#";';
                echo 'links[i].onclick = new Function( "confirmdelete(\'"+url+"\')" ) ';
                echo '}} ';
                echo '</script>';
            }
        }

        echo $OUTPUT->box_end();
    }

    protected function print_edit_date($ts = 0) {
        // TODO - use fancy JS calendar instead.

        $id = rand();
        if ($ts == 0) {
            $disabled = true;
            $date = usergetdate(time());
        } else {
            $disabled = false;
            $date = usergetdate($ts);
        }
        $day = $date['mday'];
        $month = $date['mon'];
        $year = $date['year'];

        echo '<select name="duetime[day]" id="timedueday'.$id.'" >';
        for ($i = 1; $i <= 31; $i++) {
            $selected = ($i == $day) ? 'selected="selected" ' : '';
            echo '<option value="'.$i.'" '.$selected.'>'.$i.'</option>';
        }
        echo '</select>';
        echo '<select name="duetime[month]" id="timeduemonth'.$id.'" >';
        for ($i = 1; $i <= 12; $i++) {
            $selected = ($i == $month) ? 'selected="selected" ' : '';
            echo '<option value="'.$i.'" '.$selected.'>'.userdate(gmmktime(12, 0, 0, $i, 15, 2000), "%B").'</option>';
        }
        echo '</select>';
        echo '<select name="duetime[year]" id="timedueyear'.$id.'" >';
        $today = usergetdate(time());
        $thisyear = $today['year'];
        for ($i = $thisyear - 5; $i <= ($thisyear + 10); $i++) {
            $selected = ($i == $year) ? 'selected="selected" ' : '';
            echo '<option value="'.$i.'" '.$selected.'>'.$i.'</option>';
        }
        echo '</select>';
        $checked = $disabled ? 'checked="checked" ' : '';
        echo '<input type="checkbox" name="duetimedisable" '.$checked.' id="timeduedisable'.$id.
            '" onclick="toggledate'.$id.'()" /><label for="timeduedisable'.$id.'">'.get_string('disable').' </label>'."\n";
        echo '<script type="text/javascript">'."\n";
        echo "function toggledate{$id}() {
          var disable = document.getElementById('timeduedisable{$id}').checked;
          var day = document.getElementById('timedueday{$id}');
          var month = document.getElementById('timeduemonth{$id}');
          var year = document.getElementById('timedueyear{$id}');
          ";
        echo "if (disable) {
        day.setAttribute('disabled','disabled');
        month.setAttribute('disabled', 'disabled');
        year.setAttribute('disabled', 'disabled');
         } ";
        echo "else {
        day.removeAttribute('disabled');
        month.removeAttribute('disabled');
        year.removeAttribute('disabled');
         }";
        echo "} toggledate{$id}(); </script>
        ";
    }

    protected function view_import_export() {
        $importurl = new moodle_url('/mod/checklist/import.php', array('id' => $this->cm->id));
        $exporturl = new moodle_url('/mod/checklist/export.php', array('id' => $this->cm->id));

        $importstr = get_string('import', 'checklist');
        $exportstr = get_string('export', 'checklist');

        echo "<div class='checklistimportexport'>";
        echo "<a href='$importurl'>$importstr</a>&nbsp;&nbsp;&nbsp;<a href='$exporturl'>$exportstr</a>";
        echo "</div>";
    }

    protected function view_edit_items() {
        global $OUTPUT;

        echo $OUTPUT->box_start('generalbox boxwidthwide boxaligncenter');

        $currindent = 0;
        $addatend = true;
        $focusitem = false;
        $hasauto = false;

        $thispage = new moodle_url('/mod/checklist/edit.php', array('id' => $this->cm->id, 'sesskey' => sesskey()));
        if ($this->additemafter) {
            $thispage->param('additemafter', $this->additemafter);
        }
        if ($this->editdates) {
            $thispage->param('editdates', 'on');
        }

        if ($this->checklist->autoupdate && $this->checklist->autopopulate) {
            if ($this->checklist->teacheredit == CHECKLIST_MARKING_STUDENT) {
                echo '<p>'.get_string('autoupdatewarning_student', 'checklist').'</p>';
            } else if ($this->checklist->teacheredit == CHECKLIST_MARKING_TEACHER) {
                echo '<p class="checklistwarning">'.get_string('autoupdatewarning_teacher', 'checklist').'</p>';
            } else {
                echo '<p class="checklistwarning">'.get_string('autoupdatewarning_both', 'checklist').'</p>';
            }
        }

        echo '<ol class="checklist">';
        if ($this->items) {
            $lastitem = count($this->items);
            $lastindent = 0;
            foreach ($this->items as $item) {

                while ($item->indent > $currindent) {
                    $currindent++;
                    echo '<ol class="checklist">';
                }
                while ($item->indent < $currindent) {
                    $currindent--;
                    echo '</ol>';
                }

                $itemname = '"item'.$item->id.'"';
                $thispage->param('itemid', $item->id);

                switch ($item->colour) {
                    case 'red':
                        $itemcolour = 'itemred';
                        $nexticon = 'colour_orange';
                        break;
                    case 'orange':
                        $itemcolour = 'itemorange';
                        $nexticon = 'colour_green';
                        break;
                    case 'green':
                        $itemcolour = 'itemgreen';
                        $nexticon = 'colour_purple';
                        break;
                    case 'purple':
                        $itemcolour = 'itempurple';
                        $nexticon = 'colour_black';
                        break;
                    default:
                        $itemcolour = 'itemblack';
                        $nexticon = 'colour_red';
                }

                $autoitem = ($this->checklist->autopopulate) && ($item->moduleid != 0);
                if ($autoitem) {
                    $autoclass = ' itemauto';
                } else {
                    $autoclass = '';
                }
                $hasauto = $hasauto || ($item->moduleid != 0);

                echo '<li>';
                if ($item->itemoptional == CHECKLIST_OPTIONAL_YES) {
                    $title = '"'.get_string('optionalitem', 'checklist').'"';
                    echo '<a href="'.$thispage->out(true, array('action' => 'makeheading')).'">';
                    echo '<img src="'.$OUTPUT->pix_url('empty_box', 'checklist').'" alt='.$title.' title='.$title.' /></a>&nbsp;';
                    $optional = ' class="itemoptional '.$itemcolour.$autoclass.'" ';
                } else if ($item->itemoptional == CHECKLIST_OPTIONAL_HEADING) {
                    if ($item->hidden) {
                        $title = '"'.get_string('headingitem', 'checklist').'"';
                        echo '<img src="'.$OUTPUT->pix_url('no_box', 'checklist').'" alt='.$title.' title='.$title.' />&nbsp;';
                        $optional = ' class="'.$itemcolour.$autoclass.' itemdisabled"';
                    } else {
                        $title = '"'.get_string('headingitem', 'checklist').'"';
                        if (!$autoitem) {
                            echo '<a href="'.$thispage->out(true, array('action' => 'makerequired')).'">';
                        }
                        echo '<img src="'.$OUTPUT->pix_url('no_box', 'checklist').'" alt='.$title.' title='.$title.' />';
                        if (!$autoitem) {
                            echo '</a>';
                        }
                        echo '&nbsp;';
                        $optional = ' class="itemheading '.$itemcolour.$autoclass.'" ';
                    }
                } else if ($item->hidden) {
                    $title = '"'.get_string('requireditem', 'checklist').'"';
                    echo '<img src="'.$OUTPUT->pix_url('tick_box', 'checklist').'" alt='.$title.' title='.$title.' />&nbsp;';
                    $optional = ' class="'.$itemcolour.$autoclass.' itemdisabled"';
                } else {
                    $title = '"'.get_string('requireditem', 'checklist').'"';
                    echo '<a href="'.$thispage->out(true, array('action' => 'makeoptional')).'">';
                    echo '<img src="'.$OUTPUT->pix_url('tick_box', 'checklist').'" alt='.$title.' title='.$title.' /></a>&nbsp;';
                    $optional = ' class="'.$itemcolour.$autoclass.'"';
                }

                if (isset($item->editme)) {
                    echo '<form style="display:inline" action="'.$thispage->out_omit_querystring().'" method="post">';
                    echo '<input type="text" size="'.CHECKLIST_TEXT_INPUT_WIDTH.'" name="displaytext" value="'.
                        s($item->displaytext).'" id="updateitembox" />';
                    echo '<input type="hidden" name="action" value="updateitem" />';
                    echo html_writer::input_hidden_params($thispage);
                    if ($this->editdates) {
                        $this->print_edit_date($item->duetime);
                    }
                    echo '<input type="submit" name="updateitem" value="'.get_string('updateitem', 'checklist').'" />';
                    echo '</form>';

                    $focusitem = 'updateitembox';

                    echo '<form style="display:inline" action="'.$thispage->out_omit_querystring().'" method="get">';
                    echo html_writer::input_hidden_params($thispage, array('sesskey', 'itemid'));
                    echo '<input type="submit" name="canceledititem" value="'.get_string('canceledititem', 'checklist').'" />';
                    echo '</form>';

                    $addatend = false;

                } else {
                    echo '<label for='.$itemname.$optional.'>'.format_string($item->displaytext).'</label>&nbsp;';

                    echo '<a href="'.$thispage->out(true, array('action' => 'nextcolour')).'">';
                    $title = '"'.get_string('changetextcolour', 'checklist').'"';
                    echo '<img src="'.$OUTPUT->pix_url($nexticon, 'checklist').'" alt='.$title.' title='.$title.' /></a>';

                    if (!$autoitem) {
                        echo '<a href="'.$thispage->out(true, array('action' => 'edititem')).'">';
                        $title = '"'.get_string('edititem', 'checklist').'"';
                        echo '<img src="'.$OUTPUT->pix_url('/t/edit').'"  alt='.$title.' title='.$title.' /></a>&nbsp;';
                    }

                    if (!$autoitem && $item->indent > 0) {
                        echo '<a href="'.$thispage->out(true, array('action' => 'unindentitem')).'">';
                        $title = '"'.get_string('unindentitem', 'checklist').'"';
                        echo '<img src="'.$OUTPUT->pix_url('/t/left').'" alt='.$title.' title='.$title.'  /></a>';
                    }

                    if (!$autoitem && ($item->indent < CHECKLIST_MAX_INDENT) && (($lastindent + 1) > $currindent)) {
                        echo '<a href="'.$thispage->out(true, array('action' => 'indentitem')).'">';
                        $title = '"'.get_string('indentitem', 'checklist').'"';
                        echo '<img src="'.$OUTPUT->pix_url('/t/right').'" alt='.$title.' title='.$title.' /></a>';
                    }

                    echo '&nbsp;';

                    // TODO more complex checks to take into account indentation.
                    if (!$autoitem && $item->position > 1) {
                        echo '<a href="'.$thispage->out(true, array('action' => 'moveitemup')).'">';
                        $title = '"'.get_string('moveitemup', 'checklist').'"';
                        echo '<img src="'.$OUTPUT->pix_url('/t/up').'" alt='.$title.' title='.$title.' /></a>';
                    }

                    if (!$autoitem && $item->position < $lastitem) {
                        echo '<a href="'.$thispage->out(true, array('action' => 'moveitemdown')).'">';
                        $title = '"'.get_string('moveitemdown', 'checklist').'"';
                        echo '<img src="'.$OUTPUT->pix_url('/t/down').'" alt='.$title.' title='.$title.' /></a>';
                    }

                    if ($autoitem) {
                        if ($item->hidden != CHECKLIST_HIDDEN_BYMODULE) {
                            echo '&nbsp;<a href="'.$thispage->out(true, array('action' => 'deleteitem')).'">';
                            if ($item->hidden == CHECKLIST_HIDDEN_MANUAL) {
                                $title = '"'.get_string('show').'"';
                                echo '<img src="'.$OUTPUT->pix_url('/t/show').'" alt='.$title.' title='.$title.' /></a>';
                            } else {
                                $title = '"'.get_string('hide').'"';
                                echo '<img src="'.$OUTPUT->pix_url('/t/hide').'" alt='.$title.' title='.$title.' /></a>';
                            }
                        }
                    } else {
                        echo '&nbsp;<a href="'.$thispage->out(true, array('action' => 'deleteitem')).'">';
                        $title = '"'.get_string('deleteitem', 'checklist').'"';
                        echo '<img src="'.$OUTPUT->pix_url('/t/delete').'" alt='.$title.' title='.$title.' /></a>';
                    }

                    echo '&nbsp;&nbsp;&nbsp;<a href="'.$thispage->out(true, array('action' => 'startadditem')).'">';
                    $title = '"'.get_string('additemhere', 'checklist').'"';
                    echo '<img src="'.$OUTPUT->pix_url('add', 'checklist').'" alt='.$title.' title='.$title.' /></a>';
                    if ($item->duetime) {
                        if ($item->duetime > time()) {
                            echo '<span class="checklist-itemdue"> '.userdate($item->duetime, get_string('strftimedate')).'</span>';
                        } else {
                            echo '<span class="checklist-itemoverdue"> '.
                                userdate($item->duetime, get_string('strftimedate')).'</span>';
                        }
                    }

                }

                $thispage->remove_params(array('itemid'));

                if ($this->additemafter == $item->id) {
                    $addatend = false;
                    echo '<li>';
                    echo '<form style="display:inline;" action="'.$thispage->out_omit_querystring().'" method="post">';
                    echo html_writer::input_hidden_params($thispage);
                    echo '<input type="hidden" name="action" value="additem" />';
                    echo '<input type="hidden" name="position" value="'.($item->position + 1).'" />';
                    echo '<input type="hidden" name="indent" value="'.$item->indent.'" />';
                    echo '<img src="'.$OUTPUT->pix_url('tick_box', 'checklist').'" /> ';
                    echo '<input type="text" size="'.CHECKLIST_TEXT_INPUT_WIDTH.'" name="displaytext" value="" id="additembox" />';
                    if ($this->editdates) {
                        $this->print_edit_date();
                    }
                    echo '<input type="submit" name="additem" value="'.get_string('additem', 'checklist').'" />';
                    echo '</form>';

                    echo '<form style="display:inline" action="'.$thispage->out_omit_querystring().'" method="get">';
                    echo html_writer::input_hidden_params($thispage, array('sesskey', 'additemafter'));
                    echo '<input type="submit" name="canceledititem" value="'.get_string('canceledititem', 'checklist').'" />';
                    echo '</form>';
                    echo '</li>';

                    if (!$focusitem) {
                        $focusitem = 'additembox';
                    }
                }

                $lastindent = $currindent;

                echo '</li>';
            }
        }

        $thispage->remove_params(array('itemid'));

        if ($addatend) {
            echo '<li>';
            echo '<form action="'.$thispage->out_omit_querystring().'" method="post">';
            echo html_writer::input_hidden_params($thispage);
            echo '<input type="hidden" name="action" value="additem" />';
            echo '<input type="hidden" name="indent" value="'.$currindent.'" />';
            echo '<input type="text" size="'.CHECKLIST_TEXT_INPUT_WIDTH.'" name="displaytext" value="" id="additembox" />';
            if ($this->editdates) {
                $this->print_edit_date();
            }
            echo '<input type="submit" name="additem" value="'.get_string('additem', 'checklist').'" />';
            echo '</form>';
            echo '</li>';
            if (!$focusitem) {
                $focusitem = 'additembox';
            }
        }
        echo '</ol>';
        while ($currindent) {
            $currindent--;
            echo '</ol>';
        }

        echo '<form action="'.$thispage->out_omit_querystring().'" method="get">';
        echo html_writer::input_hidden_params($thispage, array('sesskey', 'editdates'));
        if (!$this->editdates) {
            echo '<input type="hidden" name="editdates" value="on" />';
            echo '<input type="submit" value="'.get_string('editdatesstart', 'checklist').'" />';
        } else {
            echo '<input type="submit" value="'.get_string('editdatesstop', 'checklist').'" />';
        }
        if (!$this->checklist->autopopulate && $hasauto) {
            echo '<input type="hidden" name="sesskey" value="'.sesskey().'" />';
            echo '<input type="submit" value="'.get_string('removeauto', 'checklist').'" name="removeauto" />';
        }
        echo '</form>';

        if ($focusitem) {
            echo '<script type="text/javascript">document.getElementById("'.$focusitem.'").focus();</script>';
        }

        echo $OUTPUT->box_end();
    }

    protected function view_report() {
        global $DB, $OUTPUT, $CFG;

        $reportsettings = $this->get_report_settings();

        $editchecks = $this->caneditother() && optional_param('editchecks', false, PARAM_BOOL);

        $page = optional_param('page', 0, PARAM_INT);
        $perpage = optional_param('perpage', 30, PARAM_INT);

        $thisurl = new moodle_url('/mod/checklist/report.php', array('id' => $this->cm->id, 'sesskey' => sesskey()));
        if ($editchecks) {
            $thisurl->param('editchecks', 'on');
        }

        if ($this->checklist->autoupdate && $this->checklist->autopopulate) {
            if ($this->checklist->teacheredit == CHECKLIST_MARKING_TEACHER) {
                echo '<p class="checklistwarning">'.get_string('autoupdatewarning_teacher', 'checklist').'</p>';
            } else if ($this->checklist->teacheredit == CHECKLIST_MARKING_BOTH) {
                echo '<p class="checklistwarning">'.get_string('autoupdatewarning_both', 'checklist').'</p>';
            }
        }

        groups_print_activity_menu($this->cm, $thisurl);
        $activegroup = groups_get_activity_group($this->cm, true);
        if ($activegroup == 0) {
            if (groups_get_activity_groupmode($this->cm) == SEPARATEGROUPS) {
                if (!has_capability('moodle/site:accessallgroups', $this->context)) {
                    $activegroup = -1; // Not allowed to access any groups.
                }
            }
        }

        echo '&nbsp;&nbsp;<form style="display: inline;" action="'.$thisurl->out_omit_querystring().'" method="get" />';
        echo html_writer::input_hidden_params($thisurl, array('action'));
        if ($reportsettings->showoptional) {
            echo '<input type="hidden" name="action" value="hideoptional" />';
            echo '<input type="submit" name="submit" value="'.get_string('optionalhide', 'checklist').'" />';
        } else {
            echo '<input type="hidden" name="action" value="showoptional" />';
            echo '<input type="submit" name="submit" value="'.get_string('optionalshow', 'checklist').'" />';
        }
        echo '</form>';

        echo '&nbsp;&nbsp;<form style="display: inline;" action="'.$thisurl->out_omit_querystring().'" method="get" />';
        echo html_writer::input_hidden_params($thisurl);
        if ($reportsettings->showprogressbars) {
            $editchecks = false;
            echo '<input type="hidden" name="action" value="hideprogressbars" />';
            echo '<input type="submit" name="submit" value="'.get_string('showfulldetails', 'checklist').'" />';
        } else {
            echo '<input type="hidden" name="action" value="showprogressbars" />';
            echo '<input type="submit" name="submit" value="'.get_string('showprogressbars', 'checklist').'" />';
        }
        echo '</form>';

        if ($editchecks) {
            echo '&nbsp;&nbsp;<form style="display: inline;" action="'.$thisurl->out_omit_querystring().'" method="post" />';
            echo html_writer::input_hidden_params($thisurl);
            echo '<input type="hidden" name="action" value="updateallchecks"/>';
            echo '<input type="submit" name="submit" value="'.get_string('savechecks', 'checklist').'" />';
        } else if (!$reportsettings->showprogressbars && $this->caneditother()
            && $this->checklist->teacheredit != CHECKLIST_MARKING_STUDENT) {
            echo '&nbsp;&nbsp;<form style="display: inline;" action="'.$thisurl->out_omit_querystring().'" method="get" />';
            echo html_writer::input_hidden_params($thisurl);
            echo '<input type="hidden" name="editchecks" value="on" />';
            echo '<input type="submit" name="submit" value="'.get_string('editchecks', 'checklist').'" />';
            echo '</form>';
        }

        echo '<br style="clear:both"/>';

        switch ($reportsettings->sortby) {
            case 'firstdesc':
                $orderby = 'u.firstname DESC';
                break;

            case 'lastasc':
                $orderby = 'u.lastname';
                break;

            case 'lastdesc':
                $orderby = 'u.lastname DESC';
                break;

            default:
                $orderby = 'u.firstname';
                break;
        }

        $ausers = false;
        if ($activegroup == -1) {
            $users = array();
        } else if ($users = get_users_by_capability($this->context, 'mod/checklist:updateown', 'u.id', $orderby, '', '',
                                                    $activegroup, '', false)) {
            $users = array_keys($users);
            if ($this->only_view_mentee_reports()) {
                // Filter to only show reports for users who this user mentors (ie they have been assigned to them in a context).
                $users = $this->filter_mentee_users($users);
            }
        }
        if ($users && !empty($users)) {
            if (count($users) < $page * $perpage) {
                $page = 0;
            }
            echo $OUTPUT->paging_bar(count($users), $page, $perpage, new moodle_url($thisurl, array('perpage' => $perpage)));
            $users = array_slice($users, $page * $perpage, $perpage);

            list($usql, $uparams) = $DB->get_in_or_equal($users);
            if ($CFG->version < 2013111800) {
                $fields = 'u.firstname, u.lastname';
            } else {
                $fields = get_all_user_name_fields(true, 'u');
            }
            $ausers = $DB->get_records_sql("SELECT u.id, $fields FROM {user} u WHERE u.id ".$usql.' ORDER BY '.$orderby, $uparams);
        }

        if ($reportsettings->showprogressbars) {
            if ($ausers) {
                // Show just progress bars.
                if ($reportsettings->showoptional) {
                    $itemstocount = array();
                    foreach ($this->items as $item) {
                        if (!$item->hidden) {
                            if (($item->itemoptional == CHECKLIST_OPTIONAL_YES) || ($item->itemoptional == CHECKLIST_OPTIONAL_NO)) {
                                $itemstocount[] = $item->id;
                            }
                        }
                    }
                } else {
                    $itemstocount = array();
                    foreach ($this->items as $item) {
                        if (!$item->hidden) {
                            if ($item->itemoptional == CHECKLIST_OPTIONAL_NO) {
                                $itemstocount[] = $item->id;
                            }
                        }
                    }
                }
                $totalitems = count($itemstocount);

                $sql = '';
                if ($totalitems) {
                    list($isql, $iparams) = $DB->get_in_or_equal($itemstocount, SQL_PARAMS_NAMED);
                    if ($this->checklist->teacheredit == CHECKLIST_MARKING_STUDENT) {
                        $sql = 'usertimestamp > 0 AND item '.$isql.' AND userid = :user ';
                    } else {
                        $sql = 'teachermark = '.CHECKLIST_TEACHERMARK_YES.' AND item '.$isql.' AND userid = :user ';
                    }
                }
                echo '<div>';
                foreach ($ausers as $auser) {
                    if ($totalitems) {
                        $iparams['user'] = $auser->id;
                        $tickeditems = $DB->count_records_select('checklist_check', $sql, $iparams);
                        $percentcomplete = ($tickeditems * 100) / $totalitems;
                    } else {
                        $percentcomplete = 0;
                        $tickeditems = 0;
                    }

                    if ($this->caneditother()) {
                        $vslink = ' <a href="'.$thisurl->out(true, array('studentid' => $auser->id)).'" ';
                        $vslink .= 'alt="'.get_string('viewsinglereport', 'checklist').'" title="'.
                            get_string('viewsinglereport', 'checklist').'">';
                        $vslink .= '<img src="'.$OUTPUT->pix_url('/t/preview').'" /></a>';
                    } else {
                        $vslink = '';
                    }
                    $userurl = new moodle_url('/user/view.php', array('id' => $auser->id, 'course' => $this->course->id));
                    $userlink = '<a href="'.$userurl.'">'.fullname($auser).'</a>';
                    echo '<div style="float: left; width: 30%; text-align: right; margin-right: 8px; ">'.$userlink.$vslink.'</div>';

                    echo '<div class="checklist_progress_outer">';
                    echo '<div class="checklist_progress_inner" style="width:'.$percentcomplete.'%; background-image: url('.
                        $OUTPUT->pix_url('progress', 'checklist').');" >&nbsp;</div>';
                    echo '</div>';
                    echo '<div class="checklist_percentcomplete" style="float:left; width: 3em;">&nbsp;'.
                        sprintf('%0d%%', $percentcomplete).'</div>';
                    echo '<div style="float:left;">&nbsp;('.$tickeditems.'/'.$totalitems.')</div>';
                    echo '<br style="clear:both;" />';
                }
                echo '</div>';
            }

        } else {

            // Show full table.
            $firstlink = 'firstasc';
            $lastlink = 'lastasc';
            $firstarrow = '';
            $lastarrow = '';
            if ($reportsettings->sortby == 'firstasc') {
                $firstlink = 'firstdesc';
                $firstarrow = '<img src="'.$OUTPUT->pix_url('/t/down').'" alt="'.get_string('asc').'" />';
            } else if ($reportsettings->sortby == 'lastasc') {
                $lastlink = 'lastdesc';
                $lastarrow = '<img src="'.$OUTPUT->pix_url('/t/down').'" alt="'.get_string('asc').'" />';
            } else if ($reportsettings->sortby == 'firstdesc') {
                $firstarrow = '<img src="'.$OUTPUT->pix_url('/t/up').'" alt="'.get_string('desc').'" />';
            } else if ($reportsettings->sortby == 'lastdesc') {
                $lastarrow = '<img src="'.$OUTPUT->pix_url('/t/up').'" alt="'.get_string('desc').'" />';
            }
            $firstlink = new moodle_url($thisurl, array('sortby' => $firstlink));
            $lastlink = new moodle_url($thisurl, array('sortby' => $lastlink));
            $nameheading = ' <a href="'.$firstlink.'" >'.get_string('firstname').'</a> '.$firstarrow;
            $nameheading .= ' / <a href="'.$lastlink.'" >'.get_string('lastname').'</a> '.$lastarrow;

            $table = new stdClass;
            $table->head = array($nameheading);
            $table->level = array(-1);
            $table->size = array('100px');
            $table->skip = array(false);
            foreach ($this->items as $item) {
                if ($item->hidden) {
                    continue;
                }

                $table->head[] = format_string($item->displaytext);
                $table->level[] = ($item->indent < 3) ? $item->indent : 2;
                $table->size[] = '80px';
                $table->skip[] = (!$reportsettings->showoptional) && ($item->itemoptional == CHECKLIST_OPTIONAL_YES);
            }

            $table->data = array();
            if ($ausers) {
                foreach ($ausers as $auser) {
                    $row = array();

                    $vslink = ' <a href="'.$thisurl->out(true, array('studentid' => $auser->id)).'" ';
                    $vslink .= 'alt="'.get_string('viewsinglereport', 'checklist').'" title="'.
                        get_string('viewsinglereport', 'checklist').'">';
                    $vslink .= '<img src="'.$OUTPUT->pix_url('/t/preview').'" /></a>';
                    $userurl = new moodle_url('/user/view.php', array('id' => $auser->id, 'course' => $this->course->id));
                    $userlink = '<a href="'.$userurl.'">'.fullname($auser).'</a>';

                    $row[] = $userlink.$vslink;

                    $sql = 'SELECT i.id, i.itemoptional, i.hidden, c.usertimestamp, c.teachermark
                              FROM {checklist_item} i
                         LEFT JOIN {checklist_check} c ';
                    $sql .= 'ON (i.id = c.item AND c.userid = ? ) WHERE i.checklist = ? AND i.userid=0 ORDER BY i.position';
                    $checks = $DB->get_records_sql($sql, array($auser->id, $this->checklist->id));

                    foreach ($checks as $check) {
                        if ($check->hidden) {
                            continue;
                        }

                        if ($check->itemoptional == CHECKLIST_OPTIONAL_HEADING) {
                            $row[] = array(false, false, true, 0, 0);
                        } else {
                            if ($check->usertimestamp > 0) {
                                $row[] = array($check->teachermark, true, false, $auser->id, $check->id);
                            } else {
                                $row[] = array($check->teachermark, false, false, $auser->id, $check->id);
                            }
                        }
                    }

                    $table->data[] = $row;

                    if ($editchecks) {
                        echo '<input type="hidden" name="userids[]" value="'.$auser->id.'" />';
                    }
                }
            }

            echo '<div style="overflow:auto">';
            $this->print_report_table($table, $editchecks);
            echo '</div>';

            if ($editchecks) {
                echo '<input type="submit" name="submit" value="'.get_string('savechecks', 'checklist').'" />';
                echo '</form>';
            }
        }
    }

    /**
     * This function gets called when we are in editing mode
     * adding the button the the row
     *
     * @table object object being parsed
     * @param $table
     * @return string Return ammended code to output
     */
    protected function report_add_toggle_button_row($table) {
        global $PAGE;

        if (!$table->data) {
            return '';
        }

        $PAGE->requires->yui_module('moodle-mod_checklist-buttons', 'M.mod_checklist.buttons.init');
        $passedrow = $table->data;
        $output = '';
        $output .= '<tr class="r1">';
        foreach ($passedrow[0] as $key => $item) {
            if ($key == 0) {
                // Left align + colspan of 2 (overlapping the button column).
                $output .= '<td colspan="2" style=" text-align: left; width: '.$table->size[0].';" class="cell c0"></td>';
            } else {
                $size = $table->size[$key];
                $cellclass = 'cell c'.$key.' level'.$table->level[$key];
                list($teachermark, $studentmark, $heading, $userid, $checkid) = $item;
                if ($heading) {
                    // Heading items have no buttons.
                    $output .= '<td style=" text-align: center; width: '.$size.';" class="cell c0">&nbsp;</td>';
                } else {
                    // Not a heading item => add a button.
                    $output .= '<td style=" text-align: center; width: '.$size.';" class="'.$cellclass.'">';
                    $output .= html_writer::tag('button', get_string('togglecolumn', 'checklist'),
                                                    array(
                                                        'class' => 'make_col_c',
                                                        'id' => $checkid,
                                                        'type' => 'button'
                                                    ));
                    $output .= '</td>';
                }
            }
        }
        $output .= '</tr>';
        return $output;
    }

    protected function print_report_table($table, $editchecks) {
        global $OUTPUT, $CFG;

        $output = '';

        $output .= '<table summary="'.get_string('reporttablesummary', 'checklist').'"';
        $output .= ' cellpadding="5" cellspacing="1" class="generaltable boxaligncenter checklistreport">';

        $showteachermark = !($this->checklist->teacheredit == CHECKLIST_MARKING_STUDENT);
        $showstudentmark = !($this->checklist->teacheredit == CHECKLIST_MARKING_TEACHER);
        $teachermarklocked = $this->checklist->lockteachermarks && !has_capability('mod/checklist:updatelocked', $this->context);

        // Sort out the heading row.
        $output .= '<tr>';
        $keys = array_keys($table->head);
        $lastkey = end($keys);
        foreach ($table->head as $key => $heading) {
            if ($table->skip[$key]) {
                continue;
            }
            $size = $table->size[$key];
            $levelclass = ' head'.$table->level[$key];
            if ($key == $lastkey) {
                $levelclass .= ' lastcol';
            }
            // If statement to judge if the header is the first cell in the row, if so the <th> needs colspan=2 added
            // to cover the extra column added (containing the toggle button) to retain the correct table structure.
            $colspan = '';
            if ($key == 0 && $editchecks) {
                $colspan = 'colspan="2"';
            }
            $output .= '<th '.$colspan.' style="vertical-align:top; text-align: center; width:'.$size.
                '" class="header c'.$key.$levelclass.'" scope="col">';
            $output .= $heading.'</th>';
        }
        $output .= '</tr>';

        // If we are in editing mode, run the add_row function that adds the button and necessary code to the document.
        if ($editchecks) {
            $output .= $this->report_add_toggle_button_row($table);
        }
        // Output the data.
        if ($CFG->version < 2013111800) {
            $tickimg = '<img src="'.$OUTPUT->pix_url('i/tick_green_big').'" alt="'.get_string('itemcomplete', 'checklist').'" />';
        } else {
            $tickimg = '<img src="'.$OUTPUT->pix_url('i/grade_correct').'" alt="'.get_string('itemcomplete', 'checklist').'" />';
        }
        $teacherimg = array(
            CHECKLIST_TEACHERMARK_UNDECIDED => '<img src="'.$OUTPUT->pix_url('empty_box', 'checklist').'" alt="'
                .get_string('teachermarkundecided', 'checklist').'" />',
            CHECKLIST_TEACHERMARK_YES => '<img src="'.$OUTPUT->pix_url('tick_box', 'checklist').'" alt="'
                .get_string('teachermarkyes', 'checklist').'" />',
            CHECKLIST_TEACHERMARK_NO => '<img src="'.$OUTPUT->pix_url('cross_box', 'checklist').'" alt="'
                .get_string('teachermarkno', 'checklist').'" />'
        );
        $oddeven = 1;
        $keys = array_keys($table->data);
        $lastrowkey = end($keys);
        foreach ($table->data as $key => $row) {
            $oddeven = $oddeven ? 0 : 1;
            $class = '';
            if ($key == $lastrowkey) {
                $class = ' lastrow';
            }

            $output .= '<tr class="r'.$oddeven.$class.'">';
            $keys2 = array_keys($row);
            $lastkey = end($keys2);
            foreach ($row as $colkey => $item) {
                if ($table->skip[$colkey]) {
                    continue;
                }
                if ($colkey == 0) {
                    // First item is the name.
                    $output .= '<td style=" text-align: left; width: '.$table->size[0].';" class="cell c0">'.$item.'</td>';
                } else {
                    $size = $table->size[$colkey];
                    $img = '&nbsp;';
                    $cellclass = 'level'.$table->level[$colkey];
                    list($teachermark, $studentmark, $heading, $userid, $checkid) = $item;
                    // If statement to add button at beginning of row in edting mode.
                    if ($colkey == 1 && $editchecks) {
                        $output .= '<td style=" text-align: center; width: '.$size.';" class="'.$cellclass.'">';
                        $output .= html_writer::tag('button', get_string('togglerow', 'checklist'),
                                                    array(
                                                        'class' => 'make_c',
                                                        'id' => $userid,
                                                        'type' => 'button'
                                                    ));
                        $output .= '</td>';
                    }
                    if ($heading) {
                        $output .= '<td style=" text-align: center; width: '.$size.
                            ';" class="cell c'.$colkey.' reportheading">&nbsp;</td>';
                    } else {
                        if ($showteachermark) {
                            if ($teachermark == CHECKLIST_TEACHERMARK_YES) {
                                $cellclass .= '-checked';
                                $img = $teacherimg[$teachermark];
                            } else if ($teachermark == CHECKLIST_TEACHERMARK_NO) {
                                $cellclass .= '-unchecked';
                                $img = $teacherimg[$teachermark];
                            } else {
                                $img = $teacherimg[CHECKLIST_TEACHERMARK_UNDECIDED];
                            }

                            if ($editchecks) {
                                $lock = $teachermarklocked && $teachermark == CHECKLIST_TEACHERMARK_YES;
                                $disabled = $lock ? 'disabled="disabled" ' : '';

                                $selu = ($teachermark == CHECKLIST_TEACHERMARK_UNDECIDED) ? 'selected="selected" ' : '';
                                $sely = ($teachermark == CHECKLIST_TEACHERMARK_YES) ? 'selected="selected" ' : '';
                                $seln = ($teachermark == CHECKLIST_TEACHERMARK_NO) ? 'selected="selected" ' : '';

                                $img = '<select name="items_'.$userid.'['.$checkid.']" '.$disabled.'>';
                                $img .= '<option value="'.CHECKLIST_TEACHERMARK_UNDECIDED.'" '.$selu.'></option>';
                                $img .= '<option value="'.CHECKLIST_TEACHERMARK_YES.'" '.$sely.'>'.get_string('yes').'</option>';
                                $img .= '<option value="'.CHECKLIST_TEACHERMARK_NO.'" '.$seln.'>'.get_string('no').'</option>';
                                $img .= '</select>';
                            }
                        }
                        if ($showstudentmark) {
                            if ($studentmark) {
                                if (!$showteachermark) {
                                    $cellclass .= '-checked';
                                }
                                $img .= $tickimg;
                            }
                        }

                        $cellclass .= ' cell c'.$colkey;

                        if ($colkey == $lastkey) {
                            $cellclass .= ' lastcol';
                        }

                        $output .= '<td style=" text-align: center; width: '.$size.';" class="'.$cellclass.'">'.$img.'</td>';
                    }
                }
            }
            $output .= '</tr>';
        }

        $output .= '</table>';

        echo $output;
    }

    protected function view_footer() {
        global $OUTPUT;
        echo $OUTPUT->footer();
    }

    protected function process_view_actions() {
        global $CFG;

        $this->useredit = optional_param('useredit', false, PARAM_BOOL);

        $action = optional_param('action', false, PARAM_TEXT);
        if (!$action) {
            return;
        }

        if (!confirm_sesskey()) {
            error('Invalid sesskey');
        }

        $itemid = optional_param('itemid', 0, PARAM_INT);

        switch ($action) {
            case 'updatechecks':
                if ($CFG->version < 2011120100) {
                    $newchecks = optional_param('items', array(), PARAM_INT);
                } else {
                    $newchecks = optional_param_array('items', array(), PARAM_INT);
                }
                $this->updatechecks($newchecks);
                break;

            case 'startadditem':
                $this->additemafter = $itemid;
                break;

            case 'edititem':
                if ($this->useritems && isset($this->useritems[$itemid])) {
                    $this->useritems[$itemid]->editme = true;
                }
                break;

            case 'additem':
                $displaytext = optional_param('displaytext', '', PARAM_TEXT);
                $displaytext .= "\n".optional_param('displaytextnote', '', PARAM_TEXT);
                $position = optional_param('position', false, PARAM_INT);
                $this->additem($displaytext, $this->userid, 0, $position);
                $item = $this->get_item_at_position($position);
                if ($item) {
                    $this->additemafter = $item->id;
                }
                break;

            case 'deleteitem':
                $this->deleteitem($itemid);
                break;

            case 'updateitem':
                $displaytext = optional_param('displaytext', '', PARAM_TEXT);
                $displaytext .= "\n".optional_param('displaytextnote', '', PARAM_TEXT);
                $this->updateitemtext($itemid, $displaytext);
                break;

            default:
                error('Invalid action - "'.s($action).'"');
        }

        if ($action != 'updatechecks') {
            $this->useredit = true;
        }
    }

    protected function process_edit_actions() {
        global $CFG;
        $this->editdates = optional_param('editdates', false, PARAM_BOOL);
        $additemafter = optional_param('additemafter', false, PARAM_INT);
        $removeauto = optional_param('removeauto', false, PARAM_TEXT);

        if ($removeauto) {
            // Remove any automatically generated items from the list
            // (if no longer using automatic items).
            if (!confirm_sesskey()) {
                error('Invalid sesskey');
            }
            $this->removeauto();
            return;
        }

        $action = optional_param('action', false, PARAM_TEXT);
        if (!$action) {
            $this->additemafter = $additemafter;
            return;
        }

        if (!confirm_sesskey()) {
            error('Invalid sesskey');
        }

        $itemid = optional_param('itemid', 0, PARAM_INT);

        switch ($action) {
            case 'additem':
                $displaytext = optional_param('displaytext', '', PARAM_TEXT);
                $indent = optional_param('indent', 0, PARAM_INT);
                $position = optional_param('position', false, PARAM_INT);
                if (optional_param('duetimedisable', false, PARAM_BOOL)) {
                    $duetime = false;
                } else {
                    if ($CFG->branch >= 22) {
                        $duetime = optional_param_array('duetime', false, PARAM_INT);
                    } else {
                        $duetime = optional_param('duetime', false, PARAM_INT);
                    }
                }
                $this->additem($displaytext, 0, $indent, $position, $duetime);
                if ($position) {
                    $additemafter = false;
                }
                break;
            case 'startadditem':
                $additemafter = $itemid;
                break;
            case 'edititem':
                if (isset($this->items[$itemid])) {
                    $this->items[$itemid]->editme = true;
                }
                break;
            case 'updateitem':
                $displaytext = optional_param('displaytext', '', PARAM_TEXT);
                if (optional_param('duetimedisable', false, PARAM_BOOL)) {
                    $duetime = false;
                } else {
                    if ($CFG->version < 2011120100) {
                        $duetime = optional_param('duetime', false, PARAM_INT);
                    } else {
                        $duetime = optional_param_array('duetime', false, PARAM_INT);
                    }
                }
                $this->updateitemtext($itemid, $displaytext, $duetime);
                break;
            case 'deleteitem':
                if (($this->checklist->autopopulate) && (isset($this->items[$itemid])) && ($this->items[$itemid]->moduleid)) {
                    $this->toggledisableitem($itemid);
                } else {
                    $this->deleteitem($itemid);
                }
                break;
            case 'moveitemup':
                $this->moveitemup($itemid);
                break;
            case 'moveitemdown':
                $this->moveitemdown($itemid);
                break;
            case 'indentitem':
                $this->indentitem($itemid);
                break;
            case 'unindentitem':
                $this->unindentitem($itemid);
                break;
            case 'makeoptional':
                $this->makeoptional($itemid, true);
                break;
            case 'makerequired':
                $this->makeoptional($itemid, false);
                break;
            case 'makeheading':
                $this->makeoptional($itemid, true, true);
                break;
            case 'nextcolour':
                $this->nextcolour($itemid);
                break;
            default:
                error('Invalid action - "'.s($action).'"');
        }

        if ($additemafter) {
            $this->additemafter = $additemafter;
        }
    }

    protected function get_report_settings() {
        global $SESSION;

        if (!isset($SESSION->checklist_report)) {
            $settings = new stdClass;
            $settings->showcompletiondates = false;
            $settings->showoptional = true;
            $settings->showprogressbars = false;
            $settings->sortby = 'firstasc';
            $SESSION->checklist_report = $settings;
        }
        return clone $SESSION->checklist_report; // We want changes to settings to be explicit.
    }

    protected function set_report_settings($settings) {
        global $SESSION, $CFG;

        $currsettings = $this->get_report_settings();
        foreach ($currsettings as $key => $currval) {
            if (isset($settings->$key)) {
                $currsettings->$key = $settings->$key; // Only set values if they already exist.
            }
        }
        if ($CFG->debug == DEBUG_DEVELOPER) { // Show dev error if attempting to set non-existent setting.
            foreach ($settings as $key => $val) {
                if (!isset($currsettings->$key)) {
                    debugging("Attempting to set invalid setting '$key'", DEBUG_DEVELOPER);
                }
            }
        }

        $SESSION->checklist_report = $currsettings;
    }

    protected function process_report_actions() {
        $settings = $this->get_report_settings();

        if ($sortby = optional_param('sortby', false, PARAM_TEXT)) {
            $settings->sortby = $sortby;
            $this->set_report_settings($settings);
        }

        $savenext = optional_param('savenext', false, PARAM_TEXT);
        $viewnext = optional_param('viewnext', false, PARAM_TEXT);
        $action = optional_param('action', false, PARAM_TEXT);
        if (!$action) {
            return;
        }

        if (!confirm_sesskey()) {
            error('Invalid sesskey');
        }

        switch ($action) {
            case 'showprogressbars':
                $settings->showprogressbars = true;
                break;
            case 'hideprogressbars':
                $settings->showprogressbars = false;
                break;
            case 'showoptional':
                $settings->showoptional = true;
                break;
            case 'hideoptional':
                $settings->showoptional = false;
                break;
            case 'updatechecks':
                if ($this->caneditother() && !$viewnext) {
                    $this->updateteachermarks();
                }
                break;
            case 'updateallchecks':
                if ($this->caneditother()) {
                    $this->updateallteachermarks();
                }
                break;
            case 'toggledates':
                $settings->showcompletiondates = !$settings->showcompletiondates;
                break;
        }

        $this->set_report_settings($settings);

        if ($viewnext || $savenext) {
            $this->getnextuserid();
            $this->get_items();
        }
    }

    public function additem($displaytext, $userid = 0, $indent = 0, $position = false, $duetime = false, $moduleid = 0,
                               $optional = CHECKLIST_OPTIONAL_NO, $hidden = CHECKLIST_HIDDEN_NO) {
        global $DB;

        $displaytext = trim($displaytext);
        if ($displaytext == '') {
            return false;
        }

        if ($userid) {
            if (!$this->canaddown()) {
                return false;
            }
        } else {
            if (!$moduleid && !$this->canedit()) {
                // Moduleid entries are added automatically, if the activity exists; ignore canedit check.
                return false;
            }
        }

        $item = new stdClass;
        $item->checklist = $this->checklist->id;
        $item->displaytext = $displaytext;
        if ($position) {
            $item->position = $position;
        } else {
            $item->position = count($this->items) + 1;
        }
        $item->indent = $indent;
        $item->userid = $userid;
        $item->itemoptional = $optional;
        $item->hidden = $hidden;
        $item->duetime = 0;
        if ($duetime) {
            $item->duetime = make_timestamp($duetime['year'], $duetime['month'], $duetime['day']);
        }
        $item->eventid = 0;
        $item->colour = 'black';
        $item->moduleid = $moduleid;
        $item->checked = false;

        $item->id = $DB->insert_record('checklist_item', $item);
        if ($item->id) {
            if ($userid) {
                $this->useritems[$item->id] = $item;
                $this->useritems[$item->id]->checked = false;
                if ($position) {
                    uasort($this->useritems, 'checklist_itemcompare');
                }
            } else {
                if ($position) {
                    $this->additemafter = $item->id;
                    $this->update_item_positions(1, $position);
                }
                $this->items[$item->id] = $item;
                $this->items[$item->id]->checked = false;
                $this->items[$item->id]->teachermark = CHECKLIST_TEACHERMARK_UNDECIDED;
                uasort($this->items, 'checklist_itemcompare');
                if ($this->checklist->duedatesoncalendar) {
                    $this->setevent($item->id, true);
                }
            }
        }

        return $item->id;
    }

    protected function setevent($itemid, $add) {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/calendar/lib.php');

        $item = $this->items[$itemid];
        $update = false;

        if ((!$add) || ($item->duetime == 0)) {  // Remove the event (if any).
            if (!$item->eventid) {
                return; // No event to remove.
            }

            $event = calendar_event::load($item->eventid);
            $event->delete();
            $this->items[$itemid]->eventid = 0;
            $update = true;

        } else {  // Add/update event.
            $eventdata = new stdClass();
            $eventdata->name = $item->displaytext;
            $eventdata->description = get_string('calendardescription', 'checklist', $this->checklist->name);
            $eventdata->courseid = $this->course->id;
            $eventdata->modulename = 'checklist';
            $eventdata->instance = $this->checklist->id;
            $eventdata->eventtype = 'due';
            $eventdata->timestart = $item->duetime;

            if ($item->eventid) {
                $event = calendar_event::load($item->eventid);
                $event->update($eventdata);
            } else {
                $event = calendar_event::create($eventdata, false);
                $this->items[$itemid]->eventid = $event->id;
                $update = true;
            }
        }

        if ($update) { // Event added or removed.
            $upditem = new stdClass();
            $upditem->id = $itemid;
            $upditem->eventid = $this->items[$itemid]->eventid;
            $DB->update_record('checklist_item', $upditem);
        }
    }

    public function setallevents() {
        if (!$this->items) {
            return;
        }

        $add = $this->checklist->duedatesoncalendar;
        foreach ($this->items as $key => $value) {
            $this->setevent($key, $add);
        }
    }

    protected function updateitemtext($itemid, $displaytext, $duetime = false) {
        global $DB;

        $displaytext = trim($displaytext);
        if ($displaytext == '') {
            return;
        }

        if (isset($this->items[$itemid])) {
            if ($this->canedit()) {
                $this->items[$itemid]->displaytext = $displaytext;
                $upditem = new stdClass;
                $upditem->id = $itemid;
                $upditem->displaytext = $displaytext;

                $upditem->duetime = 0;
                if ($duetime) {
                    $upditem->duetime = make_timestamp($duetime['year'], $duetime['month'], $duetime['day']);
                }
                $this->items[$itemid]->duetime = $upditem->duetime;

                $DB->update_record('checklist_item', $upditem);

                if ($this->checklist->duedatesoncalendar) {
                    $this->setevent($itemid, true);
                }
            }
        } else if (isset($this->useritems[$itemid])) {
            if ($this->canaddown()) {
                $this->useritems[$itemid]->displaytext = $displaytext;
                $upditem = new stdClass;
                $upditem->id = $itemid;
                $upditem->displaytext = $displaytext;
                $DB->update_record('checklist_item', $upditem);
            }
        }
    }

    protected function toggledisableitem($itemid) {
        global $DB;

        if (isset($this->items[$itemid])) {
            if (!$this->canedit()) {
                return;
            }

            $item = $this->items[$itemid];
            if ($item->hidden == CHECKLIST_HIDDEN_NO) {
                $item->hidden = CHECKLIST_HIDDEN_MANUAL;
            } else if ($item->hidden == CHECKLIST_HIDDEN_MANUAL) {
                $item->hidden = CHECKLIST_HIDDEN_NO;
            }

            $upditem = new stdClass;
            $upditem->id = $itemid;
            $upditem->hidden = $item->hidden;
            $DB->update_record('checklist_item', $upditem);

            // If the item is a section heading, then show/hide all items in that section.
            if ($item->itemoptional == CHECKLIST_OPTIONAL_HEADING) {
                if ($item->hidden) {
                    foreach ($this->items as $it) {
                        if ($it->position <= $item->position) {
                            continue;
                        }
                        if ($it->itemoptional == CHECKLIST_OPTIONAL_HEADING) {
                            break;
                        }
                        if (!$it->moduleid) {
                            continue;
                        }
                        if ($it->hidden == CHECKLIST_HIDDEN_NO) {
                            $it->hidden = CHECKLIST_HIDDEN_MANUAL;
                            $upditem = new stdClass;
                            $upditem->id = $it->id;
                            $upditem->hidden = $it->hidden;
                            $DB->update_record('checklist_item', $upditem);
                        }
                    }

                } else {

                    foreach ($this->items as $it) {
                        if ($it->position <= $item->position) {
                            continue;
                        }
                        if ($it->itemoptional == CHECKLIST_OPTIONAL_HEADING) {
                            break;
                        }
                        if (!$it->moduleid) {
                            continue;
                        }
                        if ($it->hidden == CHECKLIST_HIDDEN_MANUAL) {
                            $it->hidden = CHECKLIST_HIDDEN_NO;
                            $upditem = new stdClass;
                            $upditem->id = $it->id;
                            $upditem->hidden = $it->hidden;
                            $DB->update_record('checklist_item', $upditem);
                        }
                    }
                }
            }
            checklist_update_grades($this->checklist);
        }
    }

    protected function deleteitem($itemid, $forcedelete = false) {
        global $DB;

        if (isset($this->items[$itemid])) {
            if (!$forcedelete && !$this->canedit()) {
                return;
            }
            $this->setevent($itemid, false); // Remove any calendar events.
            unset($this->items[$itemid]);
        } else if (isset($this->useritems[$itemid])) {
            if (!$this->canaddown()) {
                return;
            }
            unset($this->useritems[$itemid]);
        } else {
            // Item for deletion is not currently available.
            return;
        }

        $DB->delete_records('checklist_item', array('id' => $itemid));
        $DB->delete_records('checklist_check', array('item' => $itemid));

        $this->update_item_positions();
    }

    protected function moveitemto($itemid, $newposition, $forceupdate = false) {
        global $DB;

        if (!isset($this->items[$itemid])) {
            if (isset($this->useritems[$itemid])) {
                if ($this->canupdateown()) {
                    $this->useritems[$itemid]->position = $newposition;
                    $upditem = new stdClass;
                    $upditem->id = $itemid;
                    $upditem->position = $newposition;
                    $DB->update_record('checklist_item', $upditem);
                }
            }
            return;
        }

        if (!$forceupdate && !$this->canedit()) {
            return;
        }

        $itemcount = count($this->items);
        if ($newposition < 1) {
            $newposition = 1;
        } else if ($newposition > $itemcount) {
            $newposition = $itemcount;
        }

        $oldposition = $this->items[$itemid]->position;
        if ($oldposition == $newposition) {
            return;
        }

        if ($newposition < $oldposition) {
            $this->update_item_positions(1, $newposition, $oldposition); // Move items down.
        } else {
            $this->update_item_positions(-1, $oldposition, $newposition); // Move items up (including this one).
        }

        $this->items[$itemid]->position = $newposition; // Move item to new position.
        uasort($this->items, 'checklist_itemcompare'); // Sort the array by position.
        $upditem = new stdClass;
        $upditem->id = $itemid;
        $upditem->position = $newposition;
        $DB->update_record('checklist_item', $upditem); // Update the database.
    }

    protected function moveitemup($itemid) {
        // TODO If indented, only allow move if suitable space for 'reparenting'.

        if (!isset($this->items[$itemid])) {
            if (isset($this->useritems[$itemid])) {
                $this->moveitemto($itemid, $this->useritems[$itemid]->position - 1);
            }
            return;
        }
        $this->moveitemto($itemid, $this->items[$itemid]->position - 1);
    }

    protected function moveitemdown($itemid) {
        // TODO If indented, only allow move if suitable space for 'reparenting'.

        if (!isset($this->items[$itemid])) {
            if (isset($this->useritems[$itemid])) {
                $this->moveitemto($itemid, $this->useritems[$itemid]->position + 1);
            }
            return;
        }
        $this->moveitemto($itemid, $this->items[$itemid]->position + 1);
    }

    protected function indentitemto($itemid, $indent) {
        global $DB;

        if (!isset($this->items[$itemid])) {
            // Not able to indent useritems, as they are always parent + 1.
            return;
        }

        $position = $this->items[$itemid]->position;
        if ($position == 1) {
            $indent = 0;
        }

        if ($indent < 0) {
            $indent = 0;
        } else if ($indent > CHECKLIST_MAX_INDENT) {
            $indent = CHECKLIST_MAX_INDENT;
        }

        $oldindent = $this->items[$itemid]->indent;
        $adjust = $indent - $oldindent;
        if ($adjust == 0) {
            return;
        }
        $this->items[$itemid]->indent = $indent;
        $upditem = new stdClass;
        $upditem->id = $itemid;
        $upditem->indent = $indent;
        $DB->update_record('checklist_item', $upditem);

        // Update all 'children' of this item to new indent.
        foreach ($this->items as $item) {
            if ($item->position > $position) {
                if ($item->indent > $oldindent) {
                    $item->indent += $adjust;
                    $upditem = new stdClass;
                    $upditem->id = $item->id;
                    $upditem->indent = $item->indent;
                    $DB->update_record('checklist_item', $upditem);
                } else {
                    break;
                }
            }
        }
    }

    protected function indentitem($itemid) {
        if (!isset($this->items[$itemid])) {
            // Not able to indent useritems, as they are always parent + 1.
            return;
        }
        $this->indentitemto($itemid, $this->items[$itemid]->indent + 1);
    }

    protected function unindentitem($itemid) {
        if (!isset($this->items[$itemid])) {
            // Not able to indent useritems, as they are always parent + 1.
            return;
        }
        $this->indentitemto($itemid, $this->items[$itemid]->indent - 1);
    }

    protected function makeoptional($itemid, $optional, $heading = false) {
        global $DB;

        if (!isset($this->items[$itemid])) {
            return;
        }

        if ($heading) {
            $optional = CHECKLIST_OPTIONAL_HEADING;
        } else if ($optional) {
            $optional = CHECKLIST_OPTIONAL_YES;
        } else {
            $optional = CHECKLIST_OPTIONAL_NO;
        }

        if ($this->items[$itemid]->moduleid) {
            $op = $this->items[$itemid]->itemoptional;
            if ($op == CHECKLIST_OPTIONAL_HEADING) {
                return; // Topic headings must stay as headings.
            } else if ($this->items[$itemid]->itemoptional == CHECKLIST_OPTIONAL_YES) {
                $optional = CHECKLIST_OPTIONAL_NO; // Module links cannot become headings.
            } else {
                $optional = CHECKLIST_OPTIONAL_YES;
            }
        }

        $this->items[$itemid]->itemoptional = $optional;
        $upditem = new stdClass;
        $upditem->id = $itemid;
        $upditem->itemoptional = $optional;
        $DB->update_record('checklist_item', $upditem);
    }

    protected function nextcolour($itemid) {
        global $DB;

        if (!isset($this->items[$itemid])) {
            return;
        }

        switch ($this->items[$itemid]->colour) {
            case 'black':
                $nextcolour = 'red';
                break;
            case 'red':
                $nextcolour = 'orange';
                break;
            case 'orange':
                $nextcolour = 'green';
                break;
            case 'green':
                $nextcolour = 'purple';
                break;
            default:
                $nextcolour = 'black';
        }

        $upditem = new stdClass;
        $upditem->id = $itemid;
        $upditem->colour = $nextcolour;
        $DB->update_record('checklist_item', $upditem);
        $this->items[$itemid]->colour = $nextcolour;
    }

    public function ajaxupdatechecks($changechecks) {
        // Convert array of itemid=>true/false, into array of all 'checked' itemids.
        $newchecks = array();
        foreach ($this->items as $item) {
            if (array_key_exists($item->id, $changechecks)) {
                if ($changechecks[$item->id]) {
                    // Include in array if new status is true.
                    $newchecks[] = $item->id;
                }
            } else {
                // If no new status, include in array if checked.
                if ($item->checked) {
                    $newchecks[] = $item->id;
                }
            }
        }
        if ($this->useritems) {
            foreach ($this->useritems as $item) {
                if (array_key_exists($item->id, $changechecks)) {
                    if ($changechecks[$item->id]) {
                        // Include in array if new status is true.
                        $newchecks[] = $item->id;
                    }
                } else {
                    // If no new status, include in array if checked.
                    if ($item->checked) {
                        $newchecks[] = $item->id;
                    }
                }
            }
        }

        $this->updatechecks($newchecks);
    }

    protected function updatechecks($newchecks) {
        global $DB, $CFG;

        if (!is_array($newchecks)) {
            // Something has gone wrong, so update nothing.
            return;
        }

        if ($CFG->version > 2014051200) { // Moodle 2.7+.
            $params = array(
                'contextid' => $this->context->id,
                'objectid' => $this->checklist->id,
            );
            $event = \mod_checklist\event\student_checks_updated::create($params);
            $event->trigger();
        } else { // Before Moodle 2.7.
            add_to_log($this->course->id, 'checklist', 'update checks',
                       "report.php?id={$this->cm->id}&studentid={$this->userid}", $this->checklist->id, $this->cm->id);
        }

        $updategrades = false;
        if ($this->items) {
            foreach ($this->items as $item) {
                if (($this->checklist->autoupdate == CHECKLIST_AUTOUPDATE_YES) && ($item->moduleid)) {
                    continue; // Shouldn't get updated anyway, but just in case...
                }

                $newval = in_array($item->id, $newchecks);

                if ($newval != $item->checked) {
                    $updategrades = true;
                    $item->checked = $newval;

                    $check = $DB->get_record('checklist_check', array('item' => $item->id, 'userid' => $this->userid));
                    if ($check) {
                        if ($newval) {
                            $check->usertimestamp = time();
                        } else {
                            $check->usertimestamp = 0;
                        }

                        $DB->update_record('checklist_check', $check);

                    } else {
                        $check = new stdClass;
                        $check->item = $item->id;
                        $check->userid = $this->userid;
                        $check->usertimestamp = time();
                        $check->teachertimestamp = 0;
                        $check->teachermark = CHECKLIST_TEACHERMARK_UNDECIDED;

                        $check->id = $DB->insert_record('checklist_check', $check);
                    }
                }
            }
        }
        if ($updategrades) {
            checklist_update_grades($this->checklist, $this->userid);
        }

        if ($this->useritems) {
            foreach ($this->useritems as $item) {
                $newval = in_array($item->id, $newchecks);

                if ($newval != $item->checked) {
                    $item->checked = $newval;

                    $check = $DB->get_record('checklist_check', array('item' => $item->id, 'userid' => $this->userid));
                    if ($check) {
                        if ($newval) {
                            $check->usertimestamp = time();
                        } else {
                            $check->usertimestamp = 0;
                        }
                        $DB->update_record('checklist_check', $check);

                    } else {
                        $check = new stdClass;
                        $check->item = $item->id;
                        $check->userid = $this->userid;
                        $check->usertimestamp = time();
                        $check->teachertimestamp = 0;
                        $check->teachermark = CHECKLIST_TEACHERMARK_UNDECIDED;

                        $check->id = $DB->insert_record('checklist_check', $check);
                    }
                }
            }
        }
    }

    protected function updateteachermarks() {
        global $USER, $DB, $CFG;

        if ($CFG->version < 2011120100) {
            $newchecks = optional_param('items', array(), PARAM_TEXT);
        } else {
            $newchecks = optional_param_array('items', array(), PARAM_TEXT);
        }
        if (!is_array($newchecks)) {
            // Something has gone wrong, so update nothing.
            return;
        }

        if ($this->checklist->teacheredit != CHECKLIST_MARKING_STUDENT) {
            if (!$student = $DB->get_record('user', array('id' => $this->userid))) {
                error('No such user!');
            }
            if ($CFG->version > 2014051200) { // Moodle 2.7+.
                $params = array(
                    'contextid' => $this->context->id,
                    'objectid' => $this->checklist->id,
                    'relateduserid' => $this->userid,
                );
                $event = \mod_checklist\event\teacher_checks_updated::create($params);
                $event->trigger();
            } else { // Before Moodle 2.7.
                add_to_log($this->course->id, 'checklist', 'update checks',
                           "report.php?id={$this->cm->id}&studentid={$this->userid}", $this->checklist->id, $this->cm->id);
            }

            $teachermarklocked = $this->checklist->lockteachermarks
                && !has_capability('mod/checklist:updatelocked', $this->context);

            $this->update_teachermarks($newchecks, $USER->id, $teachermarklocked);
        }

        if ($CFG->version < 2011120100) {
            $newcomments = optional_param('teachercomment', false, PARAM_TEXT);
        } else {
            $newcomments = optional_param_array('teachercomment', false, PARAM_TEXT);
        }
        if (!$this->checklist->teachercomments || !$newcomments || !is_array($newcomments)) {
            return;
        }

        list($isql, $iparams) = $DB->get_in_or_equal(array_keys($this->items));
        $commentsunsorted = $DB->get_records_select('checklist_comment', "userid = ? AND itemid $isql",
                                                    array_merge(array($this->userid), $iparams));
        $comments = array();
        foreach ($commentsunsorted as $comment) {
            $comments[$comment->itemid] = $comment;
        }
        foreach ($newcomments as $itemid => $newcomment) {
            $newcomment = trim($newcomment);
            if ($newcomment == '') {
                if (array_key_exists($itemid, $comments)) {
                    $DB->delete_records('checklist_comment', array('id' => $comments[$itemid]->id));
                    unset($comments[$itemid]); // Should never be needed, but just in case...
                }
            } else {
                if (array_key_exists($itemid, $comments)) {
                    if ($comments[$itemid]->text != $newcomment) {
                        $updatecomment = new stdClass;
                        $updatecomment->id = $comments[$itemid]->id;
                        $updatecomment->userid = $this->userid;
                        $updatecomment->itemid = $itemid;
                        $updatecomment->commentby = $USER->id;
                        $updatecomment->text = $newcomment;

                        $DB->update_record('checklist_comment', $updatecomment);
                    }
                } else {
                    $addcomment = new stdClass;
                    $addcomment->itemid = $itemid;
                    $addcomment->userid = $this->userid;
                    $addcomment->commentby = $USER->id;
                    $addcomment->text = $newcomment;

                    $DB->insert_record('checklist_comment', $addcomment);
                }
            }
        }
    }

    /**
     * Public to allow use in Behat tests.
     *
     * @param int[] $newchecks maps itemid => teachermark
     * @param int $teacherid userid of the teacher doing the update
     * @param bool $teachermarklocked (optional) set to true to prevent teachers from changing 'yes' to 'no'.
     */
    public function update_teachermarks($newchecks, $teacherid, $teachermarklocked = false) {
        global $DB;

        $updategrades = false;
        foreach ($newchecks as $itemid => $newval) {
            if (isset($this->items[$itemid])) {
                $item = $this->items[$itemid];

                if ($teachermarklocked && $item->teachermark == CHECKLIST_TEACHERMARK_YES) {
                    continue; // Does not have permission to update marks that are already 'Yes'.
                }
                if ($newval != $item->teachermark) {
                    $updategrades = true;

                    $newcheck = new stdClass;
                    $newcheck->teachertimestamp = time();
                    $newcheck->teachermark = $newval;
                    $newcheck->teacherid = $teacherid;

                    $item->teachermark = $newcheck->teachermark;
                    $item->teachertimestamp = $newcheck->teachertimestamp;
                    $item->teacherid = $newcheck->teacherid;

                    $oldcheck = $DB->get_record('checklist_check', array('item' => $item->id, 'userid' => $this->userid));
                    if ($oldcheck) {
                        $newcheck->id = $oldcheck->id;
                        $DB->update_record('checklist_check', $newcheck);
                    } else {
                        $newcheck->item = $itemid;
                        $newcheck->userid = $this->userid;
                        $newcheck->id = $DB->insert_record('checklist_check', $newcheck);
                    }
                }
            }
        }
        if ($updategrades) {
            checklist_update_grades($this->checklist, $this->userid);
        }
    }

    protected function updateallteachermarks() {
        global $DB, $CFG, $USER;

        if ($this->checklist->teacheredit == CHECKLIST_MARKING_STUDENT) {
            // Student only lists do not have teacher marks to update.
            return;
        }

        if ($CFG->version < 2011120100) {
            $userids = optional_param('userids', array(), PARAM_INT);
        } else {
            $userids = optional_param_array('userids', array(), PARAM_INT);
        }
        if (!is_array($userids)) {
            // Something has gone wrong, so update nothing.
            return;
        }

        $userchecks = array();
        foreach ($userids as $userid) {
            if ($CFG->version < 2011120100) {
                $checkdata = optional_param('items_'.$userid, array(), PARAM_INT);
            } else {
                $checkdata = optional_param_array('items_'.$userid, array(), PARAM_INT);
            }
            if (!is_array($checkdata)) {
                continue;
            }
            foreach ($checkdata as $itemid => $val) {
                if ($val != CHECKLIST_TEACHERMARK_NO && $val != CHECKLIST_TEACHERMARK_YES
                    && $val != CHECKLIST_TEACHERMARK_UNDECIDED) {
                    continue; // Invalid value.
                }
                if (!$itemid) {
                    continue;
                }
                if (!array_key_exists($itemid, $this->items)) {
                    continue; // Item is not part of this checklist.
                }
                if (!array_key_exists($userid, $userchecks)) {
                    $userchecks[$userid] = array();
                }
                $userchecks[$userid][$itemid] = $val;
            }
        }

        if (empty($userchecks)) {
            return;
        }

        $teachermarklocked = $this->checklist->lockteachermarks && !has_capability('mod/checklist:updatelocked', $this->context);

        foreach ($userchecks as $userid => $items) {
            list($isql, $iparams) = $DB->get_in_or_equal(array_keys($items));
            $params = array_merge(array($userid), $iparams);
            $currentchecks = $DB->get_records_select('checklist_check', "userid = ? AND item $isql",
                                                     $params, '', 'item, id, teachermark');
            $updategrades = false;
            foreach ($items as $itemid => $val) {
                if (!array_key_exists($itemid, $currentchecks)) {
                    if ($val == CHECKLIST_TEACHERMARK_UNDECIDED) {
                        continue; // Do not create an entry for blank marks.
                    }

                    // No entry for this item - need to create it.
                    $newcheck = new stdClass;
                    $newcheck->item = $itemid;
                    $newcheck->userid = $userid;
                    $newcheck->teachermark = $val;
                    $newcheck->teachertimestamp = time();
                    $newcheck->usertimestamp = 0;
                    $newcheck->teacherid = $USER->id;

                    $DB->insert_record('checklist_check', $newcheck);
                    $updategrades = true;

                } else if ($currentchecks[$itemid]->teachermark != $val) {
                    if ($teachermarklocked && $currentchecks[$itemid]->teachermark == CHECKLIST_TEACHERMARK_YES) {
                        continue;
                    }

                    $updcheck = new stdClass;
                    $updcheck->id = $currentchecks[$itemid]->id;
                    $updcheck->teachermark = $val;
                    $updcheck->teachertimestamp = time();
                    $updcheck->teacherid = $USER->id;

                    $DB->update_record('checklist_check', $updcheck);
                    $updategrades = true;
                }
            }
            if ($updategrades) {
                if ($CFG->version > 2014051200) { // Moodle 2.7+.
                    $params = array(
                        'contextid' => $this->context->id,
                        'objectid' => $this->checklist->id,
                        'relateduserid' => $userid,
                    );
                    $event = \mod_checklist\event\teacher_checks_updated::create($params);
                    $event->trigger();
                }

                checklist_update_grades($this->checklist, $userid);
            }
        }
    }

    public function update_all_autoupdate_checks() {
        global $DB;

        if (!$this->checklist->autoupdate) {
            return;
        }

        $users = get_users_by_capability($this->context, 'mod/checklist:updateown', 'u.id', '', '', '', '', '', false);
        if (!$users) {
            return;
        }
        $userids = implode(',', array_keys($users));

        // Get a list of all the checklist items with a module linked to them (ignoring headings).
        $sql = "SELECT cm.id AS cmid, m.name AS mod_name, i.id AS itemid, cm.completion AS completion
        FROM {modules} m, {course_modules} cm, {checklist_item} i
        WHERE m.id = cm.module AND cm.id = i.moduleid AND i.moduleid > 0 AND i.checklist = ? AND i.itemoptional != 2";

        $completion = new completion_info($this->course);
        $usingcompletion = $completion->is_enabled();

        $items = $DB->get_records_sql($sql, array($this->checklist->id));
        foreach ($items as $item) {
            if ($usingcompletion && $item->completion) {
                $fakecm = new stdClass();
                $fakecm->id = $item->cmid;

                foreach ($users as $user) {
                    $compdata = $completion->get_data($fakecm, false, $user->id);
                    if ($compdata->completionstate == COMPLETION_COMPLETE
                        || $compdata->completionstate == COMPLETION_COMPLETE_PASS) {
                        $check = $DB->get_record('checklist_check', array('item' => $item->itemid, 'userid' => $user->id));
                        if ($check) {
                            if ($check->usertimestamp) {
                                continue;
                            }
                            $check->usertimestamp = time();
                            $DB->update_record('checklist_check', $check);
                        } else {
                            $check = new stdClass;
                            $check->item = $item->itemid;
                            $check->userid = $user->id;
                            $check->usertimestamp = time();
                            $check->teachertimestamp = 0;
                            $check->teachermark = CHECKLIST_TEACHERMARK_UNDECIDED;

                            $check->id = $DB->insert_record('checklist_check', $check);
                        }
                    }
                }

                continue;
            }

            $logaction = '';
            $logaction2 = false;

            switch ($item->mod_name) {
                case 'survey':
                    $logaction = 'submit';
                    break;
                case 'quiz':
                    $logaction = 'close attempt';
                    break;
                case 'forum':
                    $logaction = 'add post';
                    $logaction2 = 'add discussion';
                    break;
                case 'resource':
                    $logaction = 'view';
                    break;
                case 'hotpot':
                    $logaction = 'submit';
                    break;
                case 'wiki':
                    $logaction = 'edit';
                    break;
                case 'checklist':
                    $logaction = 'complete';
                    break;
                case 'choice':
                    $logaction = 'choose';
                    break;
                case 'lams':
                    $logaction = 'view';
                    break;
                case 'scorm':
                    $logaction = 'view';
                    break;
                case 'assignment':
                    $logaction = 'upload';
                    break;
                case 'journal':
                    $logaction = 'add entry';
                    break;
                case 'lesson':
                    $logaction = 'end';
                    break;
                case 'realtimequiz':
                    $logaction = 'submit';
                    break;
                case 'workshop':
                    $logaction = 'submit';
                    break;
                case 'glossary':
                    $logaction = 'add entry';
                    break;
                case 'data':
                    $logaction = 'add';
                    break;
                case 'chat':
                    $logaction = 'talk';
                    break;
                case 'feedback':
                    $logaction = 'submit';
                    break;
                default:
                    continue 2;
                    break;
            }

            $sql = 'SELECT DISTINCT userid ';
            $sql .= "FROM {log} ";
            $sql .= "WHERE cmid = ? AND (action = ?";
            if ($logaction2) {
                $sql .= ' OR action = ?';
            }
            $sql .= ") AND userid IN ($userids)";
            $logentries = $DB->get_records_sql($sql, array($item->cmid, $logaction, $logaction2));

            if (!$logentries) {
                continue;
            }

            foreach ($logentries as $entry) {
                $check = $DB->get_record('checklist_check', array('item' => $item->itemid, 'userid' => $entry->userid));
                if ($check) {
                    if ($check->usertimestamp) {
                        continue;
                    }
                    $check->usertimestamp = time();
                    $DB->update_record('checklist_check', $check);
                } else {
                    $check = new stdClass;
                    $check->item = $item->itemid;
                    $check->userid = $entry->userid;
                    $check->usertimestamp = time();
                    $check->teachertimestamp = 0;
                    $check->teachermark = CHECKLIST_TEACHERMARK_UNDECIDED;

                    $check->id = $DB->insert_record('checklist_check', $check);
                }
            }

            // Always update the grades.
            checklist_update_grades($this->checklist);
        }
    }

    // Update the userid to point to the next user to view.
    protected function getnextuserid() {
        global $DB;

        $activegroup = groups_get_activity_group($this->cm, true);
        $settings = $this->get_report_settings();
        switch ($settings->sortby) {
            case 'firstdesc':
                $orderby = 'ORDER BY u.firstname DESC';
                break;

            case 'lastasc':
                $orderby = 'ORDER BY u.lastname';
                break;

            case 'lastdesc':
                $orderby = 'ORDER BY u.lastname DESC';
                break;

            default:
                $orderby = 'ORDER BY u.firstname';
                break;
        }

        $ausers = false;
        if ($users = get_users_by_capability($this->context, 'mod/checklist:updateown', 'u.id', '', '', '',
                                             $activegroup, '', false)) {
            $users = array_keys($users);
            if ($this->only_view_mentee_reports()) {
                $users = $this->filter_mentee_users($users);
            }
            if (!empty($users)) {
                list($usql, $uparams) = $DB->get_in_or_equal($users);
                $ausers = $DB->get_records_sql('SELECT u.id FROM {user} u WHERE u.id '.$usql.$orderby, $uparams);
            }
        }

        $stoponnext = false;
        foreach ($ausers as $user) {
            if ($stoponnext) {
                $this->userid = $user->id;
                return;
            }
            if ($user->id == $this->userid) {
                $stoponnext = true;
            }
        }
        $this->userid = false;
    }

    public static function print_user_progressbar($checklistid, $userid, $width = '300px', $showpercent = true,
                                                  $return = false, $hidecomplete = false) {
        global $OUTPUT;

        list($ticked, $total) = self::get_user_progress($checklistid, $userid);
        if (!$total) {
            return '';
        }
        if ($hidecomplete && ($ticked == $total)) {
            return '';
        }
        $percent = $ticked * 100 / $total;

        // TODO - fix this now that styles.css is included.
        $output = '<div class="checklist_progress_outer" style="width: '.$width.';" >';
        $output .= '<div class="checklist_progress_inner" style="width:'.$percent.
            '%; background-image: url('.$OUTPUT->pix_url('progress', 'checklist').');" >&nbsp;</div>';
        $output .= '</div>';
        if ($showpercent) {
            $output .= '<span class="checklist_progress_percent">&nbsp;'.sprintf('%0d%%', $percent).'</span>';
        }
        $output .= '<br style="clear:both;" />';
        if ($return) {
            return $output;
        }

        echo $output;
        return '';
    }

    public static function get_user_progress($checklistid, $userid) {
        global $DB, $CFG;

        $userid = intval($userid); // Just to be on the safe side...

        $checklist = $DB->get_record('checklist', array('id' => $checklistid));
        if (!$checklist) {
            return array(false, false);
        }
        $groupingssel = '';
        if (isset($CFG->enablegroupmembersonly) && $CFG->enablegroupmembersonly && $checklist->autopopulate) {
            $groupings = self::get_user_groupings($userid, $checklist->course);
            $groupings[] = 0;
            $groupingssel = ' AND grouping IN ('.implode(',', $groupings).') ';
        }
        $items = $DB->get_records_select('checklist_item', 'checklist = ? AND userid = 0 AND itemoptional = '.CHECKLIST_OPTIONAL_NO.
                                                         ' AND hidden = '.CHECKLIST_HIDDEN_NO.$groupingssel, array($checklist->id),
                                         '', 'id');
        if (empty($items)) {
            return array(false, false);
        }
        $total = count($items);
        list($isql, $iparams) = $DB->get_in_or_equal(array_keys($items));
        $params = array_merge(array($userid), $iparams);

        $sql = "userid = ? AND item $isql AND ";
        if ($checklist->teacheredit == CHECKLIST_MARKING_STUDENT) {
            $sql .= 'usertimestamp > 0';
        } else {
            $sql .= 'teachermark = '.CHECKLIST_TEACHERMARK_YES;
        }
        $ticked = $DB->count_records_select('checklist_check', $sql, $params);

        return array($ticked, $total);
    }

    public static function get_user_groupings($userid, $courseid) {
        global $DB;
        $sql = "SELECT gg.groupingid
                  FROM ({groups} g JOIN {groups_members} gm ON g.id = gm.groupid)
                  JOIN {groupings_groups} gg ON gg.groupid = g.id
                  WHERE gm.userid = ? AND g.courseid = ? ";
        $groupings = $DB->get_records_sql($sql, array($userid, $courseid));
        if (!empty($groupings)) {
            return array_keys($groupings);
        }
        return array();
    }

    /**
     * Used to support Behat testing.
     *
     * @param string $itemname
     * @param int $strictness (optional) defaults to throwing an exception if the item is missing
     * @return int|null
     * @throws dml_missing_record_exception
     */
    public function get_itemid_by_name($itemname, $strictness = MUST_EXIST) {
        foreach ($this->items as $item) {
            if ($item->displaytext == $itemname) {
                return $item->id;
            }
        }
        foreach ($this->useritems as $item) {
            if ($item->displaytext == $itemname) {
                return $item->id;
            }
        }
        if ($strictness == MUST_EXIST) {
            // OK - not actually failed to get the record, but if we've not found it then it is missing in the DB.
            throw new dml_missing_record_exception('checklist_item', 'displayname = ?', array($itemname));
        }
        return null;
    }

}

function checklist_itemcompare($item1, $item2) {
    if ($item1->position < $item2->position) {
        return -1;
    } else if ($item1->position > $item2->position) {
        return 1;
    }
    if ($item1->id < $item2->id) {
        return -1;
    } else if ($item1->id > $item2->id) {
        return 1;
    }
    return 0;
}
