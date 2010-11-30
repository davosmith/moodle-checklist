<?php

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
define("CHECKLIST_OPTIONAL_DISABLED", 3);
define("CHECKLIST_OPTIONAL_HEADING_DISABLED", 4);

class checklist_class {
    var $cm;
    var $course;
    var $checklist;
    var $strchecklists;
    var $strchecklist;
    var $context;
    var $userid;
    var $items;
    var $useritems;
    var $useredit;
    var $showoptional;
    var $sortby;
    var $additemafter;
    var $editdates;

    function checklist_class($cmid='staticonly', $userid=0, $checklist=NULL, $cm=NULL, $course=NULL) {
        global $COURSE, $DB;

        if ($cmid == 'staticonly') {
            //use static functions only!
            return;
        }

        $this->userid = $userid;

        if ($cm) {
            $this->cm = $cm;
        } else if (! $this->cm = get_coursemodule_from_id('checklist', $cmid)) {
            error('Course Module ID was incorrect');
        }

        $this->context = get_context_instance(CONTEXT_MODULE, $this->cm->id);

        if ($course) {
            $this->course = $course;
        } else if ($this->cm->course == $COURSE->id) {
            $this->course = $COURSE;
        } else if (! $this->course = $DB->get_record('course', array('id' => $this->cm->course) )) {
            error('Course is misconfigured');
        }

        if ($checklist) {
            $this->checklist = $checklist;
        } else if (! $this->checklist = $DB->get_record('checklist', array('id' => $this->cm->instance) )) {
            error('checklist ID was incorrect');
        }

        $this->strchecklist = get_string('modulename', 'checklist');
        $this->strchecklists = get_string('modulenameplural', 'checklist');
        $this->pagetitle = strip_tags($this->course->shortname.': '.$this->strchecklist.': '.format_string($this->checklist->name,true));

        $this->get_items();
    }

    /**
     * Get an array of the items in a checklist
     * 
     */
    function get_items() {
        global $DB;
        
        // Load all shared checklist items
        $sql = 'checklist = ? ';
        $sql .= ' AND userid = 0';
        $this->items = $DB->get_records('checklist_item', array('checklist' => $this->checklist->id, 'userid' => 0), 'position');

        // Makes sure all items are numbered sequentially, starting at 1
        $this->update_item_positions();

        // Load student's own checklist items
        if ($this->userid && $this->canaddown()) {
            $sql = 'checklist = ? ';//.$this->checklist->id;
            $sql .= ' AND userid = ? ';//.$this->userid;
            $this->useritems = $DB->get_records('checklist_item', array('checklist' => $this->checklist->id, 'userid' => $this->userid), 'position, id');
        } else {
            $this->useritems = false;
        }

        // Load the currently checked-off items
        if ($this->userid) { // && ($this->canupdateown() || $this->canviewreports() )) {
            $sql = 'SELECT i.id, c.usertimestamp, c.teachermark FROM {checklist_item} i LEFT JOIN {checklist_check} c ';
            $sql .= 'ON (i.id = c.item AND c.userid = ?) WHERE i.checklist = ? ';

            $checks = $DB->get_records_sql($sql, array($this->userid, $this->checklist->id));

            foreach ($checks as $check) {
                $id = $check->id;
                
                if (isset($this->items[$id])) {
                    $this->items[$id]->checked = $check->usertimestamp > 0;
                    $this->items[$id]->teachermark = $check->teachermark;
                } elseif ($this->useritems && isset($this->useritems[$id])) {
                    $this->useritems[$id]->checked = $check->usertimestamp > 0;
                    // User items never have a teacher mark to go with them
                }
            }
        }
    }

    function get_itemid_from_moduleid($moduleid) {
        foreach ($this->items as $item) {
            if (($item->moduleid == $moduleid) && ($item->itemoptional != CHECKLIST_OPTIONAL_HEADING)) {
                return $item->id;
            }
        }
        return false;
    }

    function get_itemid_from_sectionid($sectionid) {
        foreach ($this->items as $item) {
            if (($item->moduleid == $sectionid) && ($item->itemoptional == CHECKLIST_OPTIONAL_HEADING)) {
                return $item->id;
            }
        }
        return false;
    }

    /**
     * Loop through all activities / resources in course and check they
     * are in the current checklist (in the right order)
     *
     */
    function update_items_from_course() {
        $mods = get_fast_modinfo($this->course);

        $nextpos = 1;
        $section = 1;
        reset($this->items);
        
        while (array_key_exists($section, $mods->sections)) {
            $sectionheading = 0;
            while (list($itemid, $item) = each($this->items)) {
                // Search from current position
                if (($item->moduleid == $section) && (($item->itemoptional == CHECKLIST_OPTIONAL_HEADING)||($item->itemoptional == CHECKLIST_OPTIONAL_HEADING_DISABLED))) {
                    $sectionheading = $itemid;
                    break;
                }
            }

            if (!$sectionheading) {
                // Search again from the start
                foreach ($this->items as $item) {
                    if (($item->moduleid == $section) && (($item->itemoptional == CHECKLIST_OPTIONAL_HEADING)||($item->itemoptional == CHECKLIST_OPTIONAL_HEADING_DISABLED))) {
                        $sectionheading = $itemid;
                        break;
                    }
                }
                reset($this->items);
            }

            if (!$sectionheading) {
                //echo 'adding section '.$section.'<br/>';
                $name = get_string('section').' '.$section;
                $sectionheading = $this->additem($name, 0, 0, false, false, $section, CHECKLIST_OPTIONAL_HEADING);
                reset($this->items);
            }
            $this->items[$sectionheading]->stillexists = true;
            
            if ($this->items[$sectionheading]->position < $nextpos) {
                $this->moveitemto($sectionheading, $nextpos);
                reset($this->items);
            }
            $nextpos = $this->items[$sectionheading]->position + 1;

            foreach($mods->sections[$section] as $cmid) {
                if ($this->cm->id == $cmid) {
                    continue; // Do not include this checklist in the list of modules
                }
                if ($mods->cms[$cmid]->modname == 'label') {
                    continue; // Ignore any labels
                }

                $foundit = false;
                while(list($itemid, $item) = each($this->items)) {
                    // Search list from current position (will usually be the next item)
                    if (($item->moduleid == $cmid) && ($item->itemoptional != CHECKLIST_OPTIONAL_HEADING) && ($item->itemoptional != CHECKLIST_OPTIONAL_HEADING_DISABLED)) {
                        $foundit = $item;
                        break;
                    }
                    if (($item->moduleid == 0) && ($item->position == $nextpos)) {
                        // Skip any items that are not linked to modules
                        $nextpos++;
                    }
                }
                if (!$foundit) {
                    // Search list again from the start (just in case)
                    foreach($this->items as $item) {
                        if (($item->moduleid == $cmid) && ($item->itemoptional != CHECKLIST_OPTIONAL_HEADING) && ($item->itemoptional != CHECKLIST_OPTIONAL_HEADING_DISABLED)) {
                            $foundit = $item;
                            break;
                        }
                    }
                    reset($this->items);
                }
                $modname = $mods->cms[$cmid]->name;
                if ($foundit) {
                    $item->stillexists = true;
                    if ($item->position != $nextpos) {
                        //echo 'reposition '.$item->displaytext.' => '.$nextpos.'<br/>';
                        $this->moveitemto($item->id, $nextpos);
                        reset($this->items);
                    }
                    if ($item->displaytext != $modname) {
                        $this->updateitemtext($item->id, $modname);
                    }
                } else {
                    //echo '+++adding item '.$name.' at '.$nextpos.'<br/>';
                    $itemid = $this->additem($modname, 0, 0, $nextpos, false, $cmid);
                    reset($this->items);
                    $this->items[$itemid]->stillexists = true;
                }
                $nextpos++;
            }

            $section++;
        }

        // Delete any items that are related to activities / resources that have been deleted
        if ($this->items) {
            foreach($this->items as $item) {
                if ($item->moduleid && !isset($item->stillexists)) {
                    //echo '---deleting item '.$item->displaytext.'<br/>';
                    $this->deleteitem($item->id);
                }
            }
        }
    }

    function removeauto() {
        if ($this->checklist->autopopulate) {
            return; // Still automatically populating the checklist, so don't remove the items
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
     * @param $move (optional) - how far to offset the current positions
     * @oaram $start (optional) - where to start offsetting positions
     * @param $end (optional) - where to stop offsetting positions
     */
    function update_item_positions($move=0, $start=1, $end=false) {
        global $DB;
        
        $pos = 1;

        if (!$this->items) {
            return;
        }
        foreach($this->items as $item) {
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

    function get_item_at_position($position) {
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

    function canupdateown() {
        global $USER;
        return (!$this->userid || ($this->userid == $USER->id)) && has_capability('mod/checklist:updateown', $this->context);
    }

    function canaddown() {
        global $USER;
        return $this->checklist->useritemsallowed && (!$this->userid || ($this->userid == $USER->id)) && has_capability('mod/checklist:updateown', $this->context);
    }

    function canpreview() {
        return has_capability('mod/checklist:preview', $this->context);
    }

    function canedit() {
        return has_capability('mod/checklist:edit', $this->context);
    }

    function canviewreports() {
        return has_capability('mod/checklist:viewreports', $this->context);
    }

    function caneditother() {
        return has_capability('mod/checklist:updateother', $this->context);
    }
        
    function view() {
        global $CFG, $OUTPUT;
        
        if ((!$this->items) && $this->canedit()) {
            redirect(new moodle_url('/mod/checklist/edit.php', array('id' => $this->cm->id)) );
        }

        $this->view_header();

        echo $OUTPUT->heading(format_string($this->checklist->name));

        if ($this->canupdateown()) {
            $currenttab = 'view';
        } elseif ($this->canpreview()) {
            $currenttab = 'preview';
        } else {
            echo $OUTPUT->confirm('<p>' . get_string('guestsno', 'checklist') . "</p>\n\n<p>" .
                get_string('liketologin') . "</p>\n", get_login_url(), get_referer(false));
            echo $OUTPUT->footer();
            die;
        }

        $this->view_tabs($currenttab);

        add_to_log($this->course->id, 'checklist', 'view', "view.php?id={$this->cm->id}", $this->checklist->name, $this->cm->id);        

        if ($this->canupdateown()) {
            $this->process_view_actions();
        }

        $this->view_items();

        $this->view_footer();
    }


    function edit() {
        global $OUTPUT;
        
        if (!$this->canedit()) {
            redirect(new moodle_url('/mod/checklist/view.php', array('id' => $this->cm->id)) );
        }

        add_to_log($this->course->id, "checklist", "edit", "edit.php?id={$this->cm->id}", $this->checklist->name, $this->cm->id);

        $this->view_header();

        echo $OUTPUT->heading(format_string($this->checklist->name));

        $this->view_tabs('edit');

        $this->process_edit_actions();

        if ($this->checklist->autopopulate) {
            $this->update_items_from_course();
        }

        $this->view_edit_items();

        $this->view_footer();
    }

    function report() {
        global $OUTPUT;

        if ((!$this->items) && $this->canedit()) {
            redirect(new moodle_url('/mod/checklist/edit.php', array('id' => $this->cm->id)) );
        }

        if (!$this->canviewreports()) {
            redirect(new moodle_url('/mod/checklist/view.php', array('id' => $this->cm->id)) );
        }

        if (!$this->caneditother()) {
            $this->userid = false;
        }

        $this->view_header();

        echo $OUTPUT->heading(format_string($this->checklist->name));

        $this->view_tabs('report');

        $this->process_report_actions();

        if ($this->userid) {
            $this->view_items(true);
        } else {
            add_to_log($this->course->id, "checklist", "report", "report.php?id={$this->cm->id}", $this->checklist->name, $this->cm->id);
            $this->view_report();
        }
        
        $this->view_footer();
    }

    function user_complete() {
        $this->view_items(false, true);
    }

    function view_header() {
        global $PAGE, $OUTPUT;

        $PAGE->set_title($this->pagetitle);
        $PAGE->set_heading($this->course->fullname);

        echo $OUTPUT->header();
    }

    function view_tabs($currenttab) {
        $tabs = array();
        $row = array();
        $inactive = array();
        $activated = array();

        if ($this->canupdateown()) {
            $row[] = new tabobject('view', new moodle_url('/mod/checklist/view.php', array('id' => $this->cm->id)), get_string('view', 'checklist'));
        } elseif ($this->canpreview()) {
            $row[] = new tabobject('preview', new moodle_url('/mod/checklist/view.php', array('id' => $this->cm->id)), get_string('preview', 'checklist'));
        }
        if ($this->canviewreports()) {
            $row[] = new tabobject('report', new moodle_url('/mod/checklist/report.php', array('id' => $this->cm->id)), get_string('report', 'checklist'));
        }
        if ($this->canedit()) {
            $row[] = new tabobject('edit', new moodle_url('/mod/checklist/edit.php', array('id' => $this->cm->id)), get_string('edit', 'checklist'));
        }

        if ($currenttab == 'view' && count($row) == 1) {
            // No tabs for students
        } else {
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

    function view_progressbar() {
        global $OUTPUT;

        if (!$this->items) {
            return;
        }

        $teacherprogress = ($this->checklist->teacheredit != CHECKLIST_MARKING_STUDENT);
        
        $totalitems = 0;
        $requireditems = 0;
        $completeitems = 0;
        $allcompleteitems = 0;
        foreach ($this->items as $item) {
            if (($item->itemoptional == CHECKLIST_OPTIONAL_HEADING)||($item->itemoptional == CHECKLIST_OPTIONAL_DISABLED)||($item->itemoptional == CHECKLIST_OPTIONAL_HEADING_DISABLED)) { 
                continue;
            }
            if ($item->itemoptional == CHECKLIST_OPTIONAL_NO) {
                $requireditems++;
                if ($teacherprogress) {
                    if ($item->teachermark == CHECKLIST_TEACHERMARK_YES) {
                        $completeitems++;
                        $allcompleteitems++;
                    }
                } elseif ($item->checked) {
                    $completeitems++;
                    $allcompleteitems++;
                }
            } elseif ($teacherprogress) {
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
        
        $percentcomplete = ($completeitems * 100) / $requireditems;
        $allpercentcomplete = ($allcompleteitems * 100) / $totalitems;

        if ($totalitems > $requireditems) {
            echo '<div style="display:block; float:left; width:150px;">';
            echo get_string('percentcomplete','checklist').':&nbsp;';
            echo '</div>';
            echo '<div class="checklist_progress_outer">';
            echo '<div class="checklist_progress_inner" style="width:'.$percentcomplete.'%; background-image: url('.$OUTPUT->pix_url('progress','checklist').');" >&nbsp;</div>';
            echo '</div>';
            echo '&nbsp;'.sprintf('%0d',$percentcomplete).'%';
            echo '<br style="clear:both"/>';
        }
        
        echo '<div style="display:block; float:left; width:150px;">';
        echo get_string('percentcompleteall','checklist').':&nbsp;';
        echo '</div>';
        echo '<div class="checklist_progress_outer">';
        echo '<div class="checklist_progress_inner" style="width:'.$allpercentcomplete.'%; background-image: url('.$OUTPUT->pix_url('progress','checklist').');" >&nbsp;</div>';
        echo '</div>';
        echo '&nbsp;'.sprintf('%0d',$allpercentcomplete).'%';
        echo '<br style="clear:both"/>';
    }

    function get_teachermark($itemid) {
        global $OUTPUT;

        if (!isset($this->items[$itemid])) {
            return array('','');
        }
        switch ($this->items[$itemid]->teachermark) {
        case CHECKLIST_TEACHERMARK_YES:
            return array($OUTPUT->pix_url('tick_box','checklist'),get_string('teachermarkyes','checklist'));

        case CHECKLIST_TEACHERMARK_NO:
            return array($OUTPUT->pix_url('cross_box','checklist'),get_string('teachermarkno','checklist'));

        default:
            return array($OUTPUT->pix_url('empty_box','checklist'),get_string('teachermarkundecided','checklist'));
        }
    }

    function view_items($viewother = false, $userreport = false) {
        global $DB, $OUTPUT;
        
        echo $OUTPUT->box_start('generalbox boxwidthwide boxaligncenter');

        $comments = $this->checklist->teachercomments;
        $editcomments = false;
        $thispage = new moodle_url('/mod/checklist/view.php', array('id' => $this->cm->id) );
        if ($viewother) {
            $showbars = optional_param('showbars',false,PARAM_BOOL);
            $sortby = optional_param('sortby','firstasc',PARAM_TEXT);
            if ($comments) {
                $editcomments = optional_param('editcomments', false, PARAM_BOOL);
            }
            $thispage = new moodle_url('/mod/checklist/report.php', array('id' => $this->cm->id, 'studentid' => $this->userid, 'sortby' => $sortby) );
            if ($showbars) {
                $thispage->param('showbars','on');
            }

            if (!$student = $DB->get_record('user', array('id' => $this->userid) )) {
                error('No such user!');
            }

            $info = $this->checklist->name.' ('.fullname($student, true).')';
            add_to_log($this->course->id, "checklist", "report", "report.php?id={$this->cm->id}&studentid={$this->userid}", $info, $this->cm->id);
            
            echo '<h2>'.get_string('checklistfor','checklist').' '.fullname($student, true);
            echo '&nbsp;';
            echo '<form style="display: inline;" action="'.$thispage->out_omit_querystring().'" method="get">';
            echo html_writer::input_hidden_params($thispage, array('studentid'));
            echo '<input type="submit" name="viewall" value="'.get_string('viewall','checklist').'" />';
            echo '</form>';

            if (!$editcomments) {
                echo '<form style="display: inline;" action="'.$thispage->out_omit_querystring().'" method="get">';
                echo html_writer::input_hidden_params($thispage);
                echo '<input type="hidden" name="editcomments" value="on" />';
                echo ' <input type="submit" name="viewall" value="'.get_string('addcomments','checklist').'" />';
                echo '</form>';
            }
            echo '</h2>';
        }

        echo format_text($this->checklist->intro, $this->checklist->introformat);

        $showteachermark = false;
        $showcheckbox = true;
        if ($this->canupdateown() || $viewother || $userreport) {
            $this->view_progressbar();
            $showteachermark = ($this->checklist->teacheredit == CHECKLIST_MARKING_TEACHER) || ($this->checklist->teacheredit == CHECKLIST_MARKING_BOTH);
            $showcheckbox = ($this->checklist->teacheredit == CHECKLIST_MARKING_STUDENT) || ($this->checklist->teacheredit == CHECKLIST_MARKING_BOTH);
        }
        $overrideauto = ($this->checklist->autoupdate != CHECKLIST_AUTOUPDATE_YES);

        if (!$this->items) {
            print_string('noitems','checklist');
        } else {
            $focusitem = false;
            $updateform = ($showcheckbox && $this->canupdateown() && !$viewother && !$userreport) || ($viewother && ($showteachermark || $editcomments));
            $addown = $this->canaddown() && $this->useredit;
            if ($updateform) {
                if ($this->canaddown() && !$viewother) {
                    echo '<form style="display:inline;" action="'.$thispage->out_omit_querystring().'" method="get">';
                    echo html_writer::input_hidden_params($thispage);
                    if ($addown) {
                        $thispage->param('useredit','on'); // Switch on for any other forms on this page (but off if this form submitted)
                        echo '<input type="submit" name="submit" value="'.get_string('addownitems-stop','checklist').'" />';
                    } else {
                        echo '<input type="hidden" name="useredit" value="on" />';
                        echo '<input type="submit" name="submit" value="'.get_string('addownitems','checklist').'" />';
                    }
                    echo '</form>';
                }
                echo '<form action="'.$thispage->out_omit_querystring().'" method="post">';
                echo html_writer::input_hidden_params($thispage);
                echo '<input type="hidden" name="action" value="updatechecks" />';
                echo '<input type="hidden" name="sesskey" value="'.sesskey().'" />';
            }

            if ($this->useritems) {
                reset($this->useritems);
            }

            if ($comments) {
                list($isql, $iparams) = $DB->get_in_or_equal(array_keys($this->items));
                $params = array_merge(array($this->userid), $iparams);
                $commentsunsorted = $DB->get_records_select('checklist_comment',"userid = ? AND itemid $isql", $params);
                $commentuserids = array();
                $commentusers = array();
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

            echo '<ol class="checklist" id="checklistouter">';
            $currindent = 0;
            foreach ($this->items as $item) {

                if (($item->itemoptional == CHECKLIST_OPTIONAL_DISABLED) || ($item->itemoptional == CHECKLIST_OPTIONAL_HEADING_DISABLED)) {
                    continue;
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

                if ($item->itemoptional == CHECKLIST_OPTIONAL_HEADING) {
                    $optional = ' class="itemheading '.$itemcolour.'" ';
                    $spacerimg = $OUTPUT->pix_url('check_spacer','checklist');
                } elseif ($item->itemoptional == CHECKLIST_OPTIONAL_YES) {
                    $optional = ' class="itemoptional '.$itemcolour.'" ';
                } else {
                    $optional = ' class="'.$itemcolour.'" ';
                }

                echo '<li>';
                if ($showteachermark) {
                    if ($item->itemoptional == CHECKLIST_OPTIONAL_HEADING) {
                        //echo '<img src="'.$spacerimg.'" alt="" title="" />';
                    } else {
                        if ($viewother) {
                            $selu = ($item->teachermark == CHECKLIST_TEACHERMARK_UNDECIDED) ? 'selected="selected" ' : '';
                            $sely = ($item->teachermark == CHECKLIST_TEACHERMARK_YES) ? 'selected="selected" ' : '';
                            $seln = ($item->teachermark == CHECKLIST_TEACHERMARK_NO) ? 'selected="selected" ' : '';
                        
                            echo '<select name="items['.$item->id.']" >';
                            echo '<option value="'.CHECKLIST_TEACHERMARK_UNDECIDED.'" '.$selu.'></option>';
                            echo '<option value="'.CHECKLIST_TEACHERMARK_YES.'" '.$sely.'>'.get_string('yes').'</option>';
                            echo '<option value="'.CHECKLIST_TEACHERMARK_NO.'" '.$seln.'>'.get_string('no').'</option>';
                            echo '</select>';
                        } else {
                            list($imgsrc, $titletext) = $this->get_teachermark($item->id);
                            echo '<img src="'.$imgsrc.'" alt="'.$titletext.'" title="'.$titletext.'" />';
                        }
                    }
                }
                if ($showcheckbox) {
                    if ($item->itemoptional == CHECKLIST_OPTIONAL_HEADING) {
                        //echo '<img src="'.$spacerimg.'" alt="" title="" />';
                    } else {
                        echo '<input type="checkbox" name="items[]" id='.$itemname.$checked.' value="'.$item->id.'" />';
                    }
                }
                echo '<label for='.$itemname.$optional.'>'.s($item->displaytext).'</label>';

                if ($addown) {
                    echo '&nbsp;<a href="'.$thispage->out(true, array('itemid'=>$item->id, 'sesskey'=>sesskey(), 'action'=>'startadditem') ).'">';
                    $title = '"'.get_string('additemalt','checklist').'"';
                    echo '<img src="'.$OUTPUT->pix_url('add','checklist').'" alt='.$title.' title='.$title.' /></a>';
                }

                if ($item->duetime) {
                    if ($item->duetime > time()) {
                        echo '<span class="checklist-itemdue"> '.userdate($item->duetime, get_string('strftimedate')).'</span>';
                    } else {
                        echo '<span class="checklist-itemoverdue"> '.userdate($item->duetime, get_string('strftimedate')).'</span>';
                    }
                }

                $foundcomment = false;
                if ($comments) {
                    if (array_key_exists($item->id, $comments)) {
                        $comment =  $comments[$item->id];
                        $foundcomment = true;
                        echo ' <span class="teachercomment">&nbsp;';
                        if ($comment->commentby) {
                            $userurl = new moodle_url('/user/view.php', array('id'=>$comment->commentby, 'course'=>$this->course->id) );
                            echo '<a href="'.$userurl.'">'.fullname($commentusers[$comment->commentby]).'</a>: ';
                        }
                        if ($editcomments) {
                            $outid = '';
                            if (!$focusitem) {
                                $focusitem = 'firstcomment';
                                $outid = ' id="firstcomment" ';
                            }
                            echo '<input type="text" name="teachercomment['.$item->id.']" value="'.s($comment->text).'" '.$outid.'/>';
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

                // Output any user-added items
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
                                    echo '<input type="checkbox" name="items[]" id='.$itemname.$checked.' disabled="disabled" value="'.$useritem->id.'" />';
                                }
                                echo '<form style="display:inline" action="'.$thisitemurl->out_omit_querystring().'" method="post">';
                                echo html_writer::input_hidden_params($thisitemurl);
                                echo '<input type="text" size="'.CHECKLIST_TEXT_INPUT_WIDTH.'" name="displaytext" value="'.s($text).'" id="updateitembox" />';
                                echo '<input type="submit" name="updateitem" value="'.get_string('updateitem','checklist').'" />';
                                echo '<br />';
                                echo '<textarea name="displaytextnote" rows="3" cols="25">'.s($note).'</textarea>';
                                echo '</form>';
                                echo '</div>';

                                echo '<form style="display:inline;" action="'.$thispage->out_omit_querystring().'" method="get">';
                                echo html_writer::input_hidden_params($thispage);
                                echo '<input type="submit" name="canceledititem" value="'.get_string('canceledititem','checklist').'" />';
                                echo '</form>';
                                echo '<br style="clear: both;" />';
                                echo '</li>';

                                $focusitem = 'updateitembox';
                            } else {
                                echo '<li>';
                                if ($showcheckbox) {
                                    echo '<input type="checkbox" name="items[]" id='.$itemname.$checked.' value="'.$useritem->id.'" />';
                                }
                                $splittext = explode("\n",s($useritem->displaytext),2);
                                $splittext[] = '';
                                $text = $splittext[0];
                                $note = str_replace("\n",'<br />',$splittext[1]);
                                echo '<label class="useritem" for='.$itemname.'>'.$text.'</label>';

                                if ($addown) {
                                    $baseurl = $thispage.'&amp;itemid='.$useritem->id.'&amp;sesskey='.sesskey().'&amp;action=';
                                    echo '&nbsp;<a href="'.$baseurl.'edititem">';
                                    $title = '"'.get_string('edititem','checklist').'"';
                                    echo '<img src="'.$OUTPUT->pix_url('/t/edit').'" alt='.$title.' title='.$title.' /></a>';

                                    echo '&nbsp;<a href="'.$baseurl.'deleteitem" class="deleteicon">';
                                    $title = '"'.get_string('deleteitem','checklist').'"';
                                    echo '<img src="'.$OUTPUT->pix_url('remove','checklist').'" alt='.$title.' title='.$title.' /></a>';
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
                    echo '<input type="submit" name="additem" value="'.get_string('additem','checklist').'" />';
                    echo '<br />';
                    echo '<textarea name="displaytextnote" rows="3" cols="25"></textarea>';
                    echo '</form>';
                    echo '</div>';
                    
                    echo '<form style="display:inline" action="'.$thispage->out_omit_querystring().'" method="get">';
                    echo html_writer::input_hidden_params($thispage);
                    echo '<input type="submit" name="canceledititem" value="'.get_string('canceledititem','checklist').'" />';
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
                echo '<input type="submit" name="submit" value="'.get_string('savechecks','checklist').'" />';
                if ($viewother) {
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
                echo 'if (confirm("'.get_string('confirmdeleteitem','checklist').'")) { window.location = url; } ';
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

    function print_edit_date($ts=0) {
        // TODO - use fancy JS calendar instead
        
        $id=rand();
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
        for ($i=1; $i<=31; $i++) {
            $selected = ($i == $day) ? 'selected="selected" ' : '';
            echo '<option value="'.$i.'" '.$selected.'>'.$i.'</option>';
        }
        echo '</select>';
        echo '<select name="duetime[month]" id="timeduemonth'.$id.'" >';
        for ($i=1; $i<=12; $i++) {
            $selected = ($i == $month) ? 'selected="selected" ' : '';
            echo '<option value="'.$i.'" '.$selected.'>'.userdate(gmmktime(12,0,0,$i,15,2000), "%B").'</option>';
        }
        echo '</select>';
        echo '<select name="duetime[year]" id="timedueyear'.$id.'" >';
        $today = usergetdate(time());
        $thisyear = $today['year'];
        for ($i=$thisyear-5; $i<=($thisyear + 10); $i++) {
            $selected = ($i == $year) ? 'selected="selected" ' : '';
            echo '<option value="'.$i.'" '.$selected.'>'.$i.'</option>';
        }
        echo '</select>';
        $checked = $disabled ? 'checked="checked" ' : '';
        echo '<input type="checkbox" name="duetimedisable" '.$checked.' id="timeduedisable'.$id.'" onclick="toggledate'.$id.'()" /><label for="timeduedisable'.$id.'">'.get_string('disable').' </label>'."\n";
        echo '<script type="text/javascript">'."\n";
        echo "function toggledate{$id}() {\n var disable = document.getElementById('timeduedisable{$id}').checked;\n var day = document.getElementById('timedueday{$id}');\n var month = document.getElementById('timeduemonth{$id}');\n var year = document.getElementById('timedueyear{$id}');\n";
        echo "if (disable) { \nday.setAttribute('disabled','disabled');\nmonth.setAttribute('disabled', 'disabled');\nyear.setAttribute('disabled', 'disabled');\n } ";
        echo "else {\nday.removeAttribute('disabled');\nmonth.removeAttribute('disabled');\nyear.removeAttribute('disabled');\n }";
        echo "} toggledate{$id}(); </script>\n";
    }

    function view_edit_items() {
        global $OUTPUT;

        echo $OUTPUT->box_start('generalbox boxwidthwide boxaligncenter');

        $currindent = 0;
        $addatend = true;
        $focusitem = false;
        $hasauto = false;

        $thispage = new moodle_url('/mod/checklist/edit.php', array('id'=>$this->cm->id, 'sesskey'=>sesskey()));
        if ($this->additemafter) {
            $thispage->param('additemafter', $this->additemafter);
        }
        if ($this->editdates) {
            $thispage->param('editdates', 'on');
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
                $thispage->param('itemid',$item->id);

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
                    $title = '"'.get_string('optionalitem','checklist').'"';
                    echo '<a href="'.$thispage->out(true, array('action'=>'makeheading')).'">';
                    echo '<img src="'.$OUTPUT->pix_url('empty_box','checklist').'" alt='.$title.' title='.$title.' /></a>&nbsp;';
                    $optional = ' class="itemoptional '.$itemcolour.$autoclass.'" ';
                } elseif ($item->itemoptional == CHECKLIST_OPTIONAL_HEADING) {
                    $title = '"'.get_string('headingitem','checklist').'"';
                    if (!$autoitem) {
                        echo '<a href="'.$thispage->out(true, array('action'=>'makerequired')).'">';
                    }
                    echo '<img src="'.$OUTPUT->pix_url('no_box','checklist').'" alt='.$title.' title='.$title.' />';
                    if (!$autoitem) {
                        echo '</a>';
                    }
                    echo '&nbsp;';
                    $optional = ' class="itemheading '.$itemcolour.$autoclass.'" ';
                } elseif ($item->itemoptional == CHECKLIST_OPTIONAL_DISABLED) {
                    $title = '"'.get_string('requireditem','checklist').'"';
                    echo '<img src="'.$OUTPUT->pix_url('tick_box','checklist').'" alt='.$title.' title='.$title.' />&nbsp;';
                    $optional = ' class="'.$itemcolour.$autoclass.' itemdisabled"';
                } elseif ($item->itemoptional == CHECKLIST_OPTIONAL_HEADING_DISABLED) {
                    $title = '"'.get_string('headingitem','checklist').'"';
                    echo '<img src="'.$OUTPUT->pix_url('no_box','checklist').'" alt='.$title.' title='.$title.' />&nbsp;';
                    $optional = ' class="'.$itemcolour.$autoclass.' itemdisabled"';
                } else {
                    $title = '"'.get_string('requireditem','checklist').'"';
                    echo '<a href="'.$thispage->out(true, array('action'=>'makeoptional')).'">';
                    echo '<img src="'.$OUTPUT->pix_url('tick_box','checklist').'" alt='.$title.' title='.$title.' /></a>&nbsp;';
                    $optional = ' class="'.$itemcolour.$autoclass.'"';
                }

                if (isset($item->editme)) {
                    echo '<form style="display:inline" action="'.$thispage->out_omit_querystring().'" method="post">';
                    echo '<input type="text" size="'.CHECKLIST_TEXT_INPUT_WIDTH.'" name="displaytext" value="'.s($item->displaytext).'" id="updateitembox" />';
                    echo '<input type="hidden" name="action" value="updateitem" />';
                    echo html_writer::input_hidden_params($thispage);
                    if ($this->editdates) {
                        $this->print_edit_date($item->duetime);
                    }
                    echo '<input type="submit" name="updateitem" value="'.get_string('updateitem','checklist').'" />';
                    echo '</form>';

                    $focusitem = 'updateitembox';

                    echo '<form style="display:inline" action="'.$thispage->out_omit_querystring().'" method="get">';
                    echo html_writer::input_hidden_params($thispage, array('sesskey', 'itemid') );
                    echo '<input type="submit" name="canceledititem" value="'.get_string('canceledititem','checklist').'" />';
                    echo '</form>';

                    $addatend = false;
                    
                } else {
                    echo '<label for='.$itemname.$optional.'>'.s($item->displaytext).'</label>&nbsp;';

                    echo '<a href="'.$thispage->out(true, array('action'=>'nextcolour')).'">';
                    $title = '"'.get_string('changetextcolour','checklist').'"';
                    echo '<img src="'.$OUTPUT->pix_url($nexticon,'checklist').'" alt='.$title.' title='.$title.' /></a>';

                    if (!$autoitem) {
                        echo '<a href="'.$thispage->out(true, array('action'=>'edititem')).'">';
                        $title = '"'.get_string('edititem','checklist').'"';
                        echo '<img src="'.$OUTPUT->pix_url('/t/edit').'"  alt='.$title.' title='.$title.' /></a>&nbsp;';
                    }

                    if (!$autoitem && $item->indent > 0) {
                        echo '<a href="'.$thispage->out(true, array('action'=>'unindentitem')).'">';
                        $title = '"'.get_string('unindentitem','checklist').'"';
                        echo '<img src="'.$OUTPUT->pix_url('/t/left').'" alt='.$title.' title='.$title.'  /></a>';
                    }

                    if (!$autoitem && ($item->indent < CHECKLIST_MAX_INDENT) && (($lastindent+1) > $currindent)) {
                        echo '<a href="'.$thispage->out(true, array('action'=>'indentitem')).'">';
                        $title = '"'.get_string('indentitem','checklist').'"';
                        echo '<img src="'.$OUTPUT->pix_url('/t/right').'" alt='.$title.' title='.$title.' /></a>';
                    }

                    echo '&nbsp;';
                    
                    // TODO more complex checks to take into account indentation
                    if (!$autoitem && $item->position > 1) {
                        echo '<a href="'.$thispage->out(true, array('action'=>'moveitemup')).'">';
                        $title = '"'.get_string('moveitemup','checklist').'"';
                        echo '<img src="'.$OUTPUT->pix_url('/t/up').'" alt='.$title.' title='.$title.' /></a>';
                    }

                    if (!$autoitem && $item->position < $lastitem) {
                        echo '<a href="'.$thispage->out(true, array('action'=>'moveitemdown')).'">';
                        $title = '"'.get_string('moveitemdown','checklist').'"';
                        echo '<img src="'.$OUTPUT->pix_url('/t/down').'" alt='.$title.' title='.$title.' /></a>';
                    }

                    if ($autoitem) {
                        echo '&nbsp;<a href="'.$thispage->out(true, array('action'=>'deleteitem')).'">';
                        if (($item->itemoptional == CHECKLIST_OPTIONAL_DISABLED) || ($item->itemoptional == CHECKLIST_OPTIONAL_HEADING_DISABLED)) {
                            $title = '"'.get_string('show').'"';
                            echo '<img src="'.$OUTPUT->pix_url('/t/show').'" alt='.$title.' title='.$title.' /></a>';
                        } else {
                            $title = '"'.get_string('hide').'"';
                            echo '<img src="'.$OUTPUT->pix_url('/t/hide').'" alt='.$title.' title='.$title.' /></a>';
                        }
                    } else {
                        echo '&nbsp;<a href="'.$thispage->out(true, array('action'=>'deleteitem')).'">';
                        $title = '"'.get_string('deleteitem','checklist').'"';
                        echo '<img src="'.$OUTPUT->pix_url('/t/delete').'" alt='.$title.' title='.$title.' /></a>';
                    }
                    
                    echo '&nbsp;&nbsp;&nbsp;<a href="'.$thispage->out(true, array('action'=>'startadditem')).'">';
                    $title = '"'.get_string('additemhere','checklist').'"';
                    echo '<img src="'.$OUTPUT->pix_url('add','checklist').'" alt='.$title.' title='.$title.' /></a>';
                    if ($item->duetime) {
                        if ($item->duetime > time()) {
                            echo '<span class="checklist-itemdue"> '.userdate($item->duetime, get_string('strftimedate')).'</span>';
                        } else {
                            echo '<span class="checklist-itemoverdue"> '.userdate($item->duetime, get_string('strftimedate')).'</span>';
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
                    echo '<input type="hidden" name="position" value="'.($item->position+1).'" />';
                    echo '<input type="hidden" name="indent" value="'.$item->indent.'" />';
                    echo '<img src="'.$OUTPUT->pix_url('tick_box','checklist').'" /> ';
                    echo '<input type="text" size="'.CHECKLIST_TEXT_INPUT_WIDTH.'" name="displaytext" value="" id="additembox" />';
                    if ($this->editdates) {
                        $this->print_edit_date();
                    }
                    echo '<input type="submit" name="additem" value="'.get_string('additem','checklist').'" />';
                    echo '</form>';
                        
                    echo '<form style="display:inline" action="'.$thispage->out_omit_querystring().'" method="get">';
                    echo html_writer::input_hidden_params($thispage, array('sesskey','additemafter'));
                    echo '<input type="submit" name="canceledititem" value="'.get_string('canceledititem','checklist').'" />';
                    echo '</form>';
                    echo '</li>';

                    if (!$focusitem) {
                        $focusitem = 'additembox';
                    }


                    $lastindent = $currindent;
                }
                
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
            echo '<input type="submit" name="additem" value="'.get_string('additem','checklist').'" />';
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
        echo html_writer::input_hidden_params($thispage, array('sesskey','editdates'));
        if (!$this->editdates) {
            echo '<input type="hidden" name="editdates" value="on" />';
            echo '<input type="submit" value="'.get_string('editdatesstart','checklist').'" />';
        } else {
            echo '<input type="submit" value="'.get_string('editdatesstop','checklist').'" />';
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

    function view_report() {
        global $DB, $OUTPUT;

        $showbars = optional_param('showbars', false, PARAM_BOOL);

        $thisurl = new moodle_url('/mod/checklist/report.php', array('id'=>$this->cm->id, 'sortby'=>$this->sortby) );
        if (!$this->showoptional) { $thisurl->param('action','hideoptional'); }
        if ($showbars) { $thisurl->param('showbars','on'); }

        groups_print_activity_menu($this->cm, $thisurl);
        $activegroup = groups_get_activity_group($this->cm, true);

        echo '&nbsp;&nbsp;<form style="display: inline;" action="'.$thisurl->out_omit_querystring().'" method="get" />';
        echo html_writer::input_hidden_params($thisurl, array('action'));
        if ($this->showoptional) {
            echo '<input type="hidden" name="action" value="hideoptional" />';
            echo '<input type="submit" name="submit" value="'.get_string('optionalhide','checklist').'" />';
        } else {
            echo '<input type="hidden" name="action" value="showoptional" />';
            echo '<input type="submit" name="submit" value="'.get_string('optionalshow','checklist').'" />';
        }
        echo '</form>';

        echo '&nbsp;&nbsp;<form style="display: inline;" action="'.$thisurl->out_omit_querystring().'" method="get" />';
        echo html_writer::input_hidden_params($thisurl, array('showbars'));
        if ($showbars) {
            echo '<input type="submit" name="submit" value="'.get_string('showfulldetails','checklist').'" />';
        } else {
            echo '<input type="hidden" name="showbars" value="on" />';
            echo '<input type="submit" name="submit" value="'.get_string('showprogressbars','checklist').'" />';
        }
        echo '</form>';

        echo '<br style="clear:both"/>';

        switch ($this->sortby) {
        case 'firstdesc':
            $orderby = ' ORDER BY u.firstname DESC';
            break;

        case 'lastasc':
            $orderby = ' ORDER BY u.lastname';
            break;

        case 'lastdesc':
            $orderby = ' ORDER BY u.lastname DESC';
            break;
            
        default:
            $orderby = ' ORDER BY u.firstname';
            break;
        }
        
        $ausers = false;
        if ($users = get_users_by_capability($this->context, 'mod/checklist:updateown', 'u.id', '', '', '', $activegroup, '', false)) {
            list($usql, $uparams) = $DB->get_in_or_equal(array_keys($users));
            $ausers = $DB->get_records_sql('SELECT u.id, u.firstname, u.lastname FROM {user} u WHERE u.id '.$usql.$orderby, $uparams);
        }

        if ($showbars) {
            if ($ausers) {
                // Show just progress bars
                if ($this->showoptional) {
                    $itemstocount = array();
                    foreach ($this->items as $item) {
                        if (($item->itemoptional == CHECKLIST_OPTIONAL_YES) || ($item->itemoptional == CHECKLIST_OPTIONAL_NO)) {
                            $itemstocount[] = $item->id;
                        }
                    }
                } else {
                    $itemstocount = array();
                    foreach ($this->items as $item) {
                        if ($item->itemoptional == CHECKLIST_OPTIONAL_NO) {
                            $itemstocount[] = $item->id;
                        }
                    }
                }
                $totalitems = count($itemstocount);
                list($isql, $iparams) = $DB->get_in_or_equal($itemstocount, SQL_PARAMS_NAMED);

                if ($this->checklist->teacheredit == CHECKLIST_MARKING_STUDENT) {
                    $sql = 'usertimestamp > 0 AND item '.$isql.' AND userid = :user ';
                } else {
                    $sql = 'teachermark = '.CHECKLIST_TEACHERMARK_YES.' AND item '.$isql.' AND userid = :user ';
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
                        $vslink = ' <a href="'.$thisurl->out(true, array('studentid'=>$auser->id) ).'" ';
                        $vslink .= 'alt="'.get_string('viewsinglereport','checklist').'" title="'.get_string('viewsinglereport','checklist').'">';
                        $vslink .= '<img src="'.$OUTPUT->pix_url('/t/preview').'" /></a>';
                    } else {
                        $vslink = '';
                    }
                    $userurl = new moodle_url('/user/view.php', array('id'=>$auser->id, 'course'=>$this->course->id) );
                    $userlink = '<a href="'.$userurl.'">'.fullname($auser).'</a>';
                    echo '<div style="float: left; width: 30%; text-align: right; margin-right: 8px; ">'.$userlink.$vslink.'</div>';
                    
                    echo '<div class="checklist_progress_outer">';
                    echo '<div class="checklist_progress_inner" style="width:'.$percentcomplete.'%; background-image: url('.$OUTPUT->pix_url('progress','checklist').');" >&nbsp;</div>';
                    echo '</div>';
                    echo '<div style="float:left; width: 3em;">&nbsp;'.sprintf('%0d%%',$percentcomplete).'</div>';
                    echo '<div style="float:left;">&nbsp;('.$tickeditems.'/'.$totalitems.')</div>';
                    echo '<br style="clear:both;" />';
                }
                echo '</div>';
            }
            
        } else {
            // Show full table
            $firstlink = 'sortby=firstasc';
            $lastlink = 'sortby=lastasc';
            $firstarrow = '';
            $lastarrow = '';
            if ($this->sortby == 'firstasc') {
                $firstlink = 'sortby=firstdesc';
                $firstarrow = '<img src="'.$OUTPUT->pix_url('/t/down').'" alt="'.get_string('asc').'" />';
            } elseif ($this->sortby == 'lastasc') {
                $lastlink = 'sortby=lastdesc';
                $lastarrow = '<img src="'.$OUTPUT->pix_url('/t/down').'" alt="'.get_string('asc').'" />';
            } elseif ($this->sortby == 'firstdesc') {
                $firstarrow = '<img src="'.$OUTPUT->pix_url('/t/up').'" alt="'.get_string('desc').'" />';
            } elseif ($this->sortby == 'lastdesc') {
                $lastarrow = '<img src="'.$OUTPUT->pix_url('/t/up').'" alt="'.get_string('desc').'" />';
            }
            $firstlink = preg_replace('/sortby=.*/', $firstlink, $thisurl);
            $lastlink = preg_replace('/sortby=.*/', $lastlink, $thisurl);
            $nameheading = get_string('fullname');
            $nameheading = ' <a href="'.$firstlink.'" >'.get_string('firstname').'</a> '.$firstarrow;
            $nameheading .= ' / <a href="'.$lastlink.'" >'.get_string('lastname').'</a> '.$lastarrow;

            $table = new stdClass;
            $table->head = array($nameheading);
            $table->level = array(-1);
            $table->size = array('100px');
            $table->skip = array(false);
            foreach ($this->items as $item) {
                if (($item->itemoptional == CHECKLIST_OPTIONAL_DISABLED) || ($item->itemoptional == CHECKLIST_OPTIONAL_HEADING_DISABLED)) {
                    continue;
                }
                
                $table->head[] = s($item->displaytext);
                $table->level[] = ($item->indent < 3) ? $item->indent : 2;
                $table->size[] = '80px';
                $table->skip[] = (!$this->showoptional) && ($item->itemoptional == CHECKLIST_OPTIONAL_YES);
            }

            $table->data = array();
            if ($ausers) {
                foreach ($ausers as $auser) {
                    $row = array();
                
                    $vslink = ' <a href="'.$thisurl->out(true, array('studentid'=>$auser->id) ).'" ';
                    $vslink .= 'alt="'.get_string('viewsinglereport','checklist').'" title="'.get_string('viewsinglereport','checklist').'">';
                    $vslink .= '<img src="'.$OUTPUT->pix_url('/t/preview').'" /></a>';
                    $userurl = new moodle_url('/user/view.php', array('id'=>$auser->id, 'course'=>$this->course->id) );
                    $userlink = '<a href="'.$userurl.'">'.fullname($auser).'</a>';

                    $row[] = $userlink.$vslink;

                    $sql = 'SELECT i.id, i.itemoptional, c.usertimestamp, c.teachermark FROM {checklist_item} i LEFT JOIN {checklist_check} c ';
                    $sql .= 'ON (i.id = c.item AND c.userid = ? ) WHERE i.checklist = ? AND i.userid=0 ORDER BY i.position';
                    $checks = $DB->get_records_sql($sql, array($auser->id, $this->checklist->id) );

                    foreach ($checks as $check) {
                        if (($check->itemoptional == CHECKLIST_OPTIONAL_DISABLED) || ($check->itemoptional == CHECKLIST_OPTIONAL_HEADING_DISABLED)) {
                            continue;
                        }
                
                        if ($check->itemoptional == CHECKLIST_OPTIONAL_HEADING) {
                            $row[] = array(false, false, true);
                        } else {
                            if ($check->usertimestamp > 0) {
                                $row[] = array($check->teachermark,true,false);
                            } else {
                                $row[] = array($check->teachermark,false,false);
                            }
                        }
                    }

                    $table->data[] = $row;
                }
            }
        
            $this->print_report_table($table);
        }
    }

    function print_report_table($table) {
        global $OUTPUT;
        
        $output = '';

        $output .= '<table summary="'.get_string('reporttablesummary','checklist').'"';
        $output .= ' cellpadding="5" cellspacing="1" class="generaltable boxaligncenter checklistreport">';

        $showteachermark = !($this->checklist->teacheredit == CHECKLIST_MARKING_STUDENT);
        $showstudentmark = !($this->checklist->teacheredit == CHECKLIST_MARKING_TEACHER);

        // Sort out the heading row
        $countcols = count($table->head);
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
            $output .= '<th style="vertical-align:top; align: center; width:'.$size.'" class="header c'.$key.$levelclass.'" scope="col">';
            $output .= $heading.'</th>';
        }
        $output .= '</tr>';

        // Output the data
        $tickimg = '<img src="'.$OUTPUT->pix_url('/i/tick_green_big').'" alt="'.get_string('itemcomplete','checklist').'" />';
        $teacherimg = array(CHECKLIST_TEACHERMARK_UNDECIDED => '<img src="'.$OUTPUT->pix_url('empty_box','checklist').'" alt="'.get_string('teachermarkundecided','checklist').'" />', 
                            CHECKLIST_TEACHERMARK_YES => '<img src="'.$OUTPUT->pix_url('tick_box','checklist').'" alt="'.get_string('teachermarkyes','checklist').'" />', 
                            CHECKLIST_TEACHERMARK_NO => '<img src="'.$OUTPUT->pix_url('cross_box','checklist').'" alt="'.get_string('teachermarkno','checklist').'" />');
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
            foreach ($row as $key => $item) {
                if ($table->skip[$key]) {
                    continue;
                }
                if ($key == 0) {
                    // First item is the name
                    $output .= '<td style=" text-align: left; width: '.$table->size[0].';" class="cell c0">'.$item.'</td>';
                } else {
                    $size = $table->size[$key];
                    $img = '&nbsp;';
                    $cellclass = 'level'.$table->level[$key];
                    list($teachermark, $studentmark, $heading) = $item;
                    if ($heading) {
                        $output .= '<td style=" text-align: center; width: '.$size.';" class="cell c'.$key.' reportheading">&nbsp;</td>';
                    } else {
                        if ($showteachermark) {
                            if ($teachermark == CHECKLIST_TEACHERMARK_YES) {
                                $cellclass .= '-checked';
                                $img = $teacherimg[$teachermark];
                            } elseif ($teachermark == CHECKLIST_TEACHERMARK_NO) {
                                $cellclass .= '-unchecked';
                                $img = $teacherimg[$teachermark];
                            } else {
                                $img = $teacherimg[CHECKLIST_TEACHERMARK_UNDECIDED];
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

                        $cellclass .= ' cell c'.$key;
                    
                        if ($key == $lastkey) {
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

    function view_footer() {
        global $OUTPUT;
        echo $OUTPUT->footer();
    }

    function process_view_actions() {
        $this->useredit = optional_param('useredit', false, PARAM_BOOL);
        
        $action = optional_param('action', false, PARAM_TEXT);
        if (!$action) {
            return;
        }

        if (!confirm_sesskey()) {
            error('Invalid sesskey');
        }

        $itemid = optional_param('itemid', 0, PARAM_INT);

        switch($action) {
        case 'updatechecks':
            $this->updatechecks();
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
    
    function process_edit_actions() {
        $this->editdates = optional_param('editdates', false, PARAM_BOOL);
        $additemafter = optional_param('additemafter', false, PARAM_INT);
        $removeauto = optional_param('removeauto', false, PARAM_TEXT);

        if ($removeauto) {
            // Remove any automatically generated items from the list
            // (if no longer using automatic items)
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
                $duetime = optional_param('duetime', false, PARAM_INT);
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
            $duetime = optional_param('duetime', false, PARAM_INT);
            if (optional_param('duetimedisable', false, PARAM_BOOL)) {
                $duetime = false;
            } else {
                $duetime = optional_param('duetime', false, PARAM_INT);
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

    function process_report_actions() {
        $this->showoptional = true;
        $this->sortby = optional_param('sortby', 'firstasc', PARAM_TEXT);

        $savenext = optional_param('savenext', false, PARAM_TEXT);
        $viewnext = optional_param('viewnext', false, PARAM_TEXT);
        $action = optional_param('action', false, PARAM_TEXT);
        if (!$action) {
            return;
        }

        if (!confirm_sesskey()) {
            error('Invalid sesskey');
        }

        if ($action == 'hideoptional') {
            $this->showoptional = false;
        } else if ($action == 'updatechecks' && $this->caneditother()) {
            if (!$viewnext) {
                $this->updateteachermarks();
            }
        }

        if ($viewnext || $savenext) {
            $this->getnextuserid();
        }
    }

    function additem($displaytext, $userid=0, $indent=0, $position=false, $duetime=false, $moduleid=0, $optional=CHECKLIST_OPTIONAL_NO) {
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
            if (!$this->canedit()) {
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
        $item->duetime = 0;
        if ($duetime) {
            $item->duetime = make_timestamp($duetime['year'], $duetime['month'], $duetime['day']);
        }
        $item->eventid = 0;
        $item->colour = 'black';
        $item->moduleid = $moduleid;

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
                uasort($this->items, 'checklist_itemcompare');
                if ($this->checklist->duedatesoncalendar) {
                    $this->setevent($item->id, true);
                }
            }
        }

        return $item->id;
    }

    function setevent($itemid, $add) {
        global $DB;
        
        $item = $this->items[$itemid];
        $update = false;

        if  ((!$add) || ($item->duetime == 0)) {  // Remove the event (if any)
            if (!$item->eventid) {
                return; // No event to remove
            }

            delete_event($item->eventid);
            $this->items[$itemid]->eventid = 0;
            $update = true;
            
        } else {  // Add/update event
            $event = new stdClass;
            $event->name = $item->displaytext;
            $event->description = get_string('calendardescription', 'checklist', $this->checklist->name);
            $event->courseid = $this->course->id;
            $event->modulename = 'checklist';
            $event->instance = $this->checklist->id;
            $event->eventtype = 'due';
            $event->timestart = $item->duetime;

            if ($item->eventid) {
                $event->id = $item->eventid;
                update_event($event);
            } else {
                $this->items[$itemid]->eventid = add_event($event);
                $update = true;
            }
        }

        if ($update) { // Event added or removed
            $upditem = new stdClass;
            $upditem->id = $itemid;
            $upditem->eventid = $this->items[$itemid]->eventid;
            $DB->update_record('checklist_item', $upditem);
        }
    }

    function setallevents() {
        if (!$this->items) {
            return;
        }

        $add = $this->checklist->duedatesoncalendar;
        foreach ($this->items as $key => $value) {
            $this->setevent($key, $add);
        }
    }

    function updateitemtext($itemid, $displaytext, $duetime=false) {
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
        } elseif (isset($this->useritems[$itemid])) {
            if ($this->canaddown()) {
                $this->useritems[$itemid]->displaytext = $displaytext;
                $upditem = new stdClass;
                $upditem->id = $itemid;
                $upditem->displaytext = $displaytext;
                $DB->update_record('checklist_item', $upditem);
            }
        }
    }

    function toggledisableitem($itemid) {
        global $DB;

        if (isset($this->items[$itemid])) {
            if (!$this->canedit()) {
                return;
            }

            $item = $this->items[$itemid];
            if ($item->itemoptional == CHECKLIST_OPTIONAL_DISABLED) {
                $item->itemoptional = CHECKLIST_OPTIONAL_NO;
            } elseif (($item->itemoptional == CHECKLIST_OPTIONAL_YES)||($item->itemoptional == CHECKLIST_OPTIONAL_NO)) {
                $item->itemoptional = CHECKLIST_OPTIONAL_DISABLED;
            } elseif ($item->itemoptional == CHECKLIST_OPTIONAL_HEADING_DISABLED) {
                $item->itemoptional = CHECKLIST_OPTIONAL_HEADING;
            } elseif ($item->itemoptional == CHECKLIST_OPTIONAL_HEADING) {
                $item->itemoptional = CHECKLIST_OPTIONAL_HEADING_DISABLED;
            }
            
            $upditem = new stdClass;
            $upditem->id = $itemid;
            $upditem->itemoptional = $item->itemoptional;
            $DB->update_record('checklist_item', $upditem);

            // If the item is a section heading, then show/hide all items in that section
            if ($item->itemoptional == CHECKLIST_OPTIONAL_HEADING) {
                foreach ($this->items as $it) {
                    if ($it->position <= $item->position) {
                        continue;
                    }
                    if (($it->itemoptional == CHECKLIST_OPTIONAL_HEADING) || ($it->itemoptional == CHECKLIST_OPTIONAL_HEADING_DISABLED)) {
                        break;
                    }
                    if (!$it->moduleid) {
                        continue;
                    }
                    if ($it->itemoptional == CHECKLIST_OPTIONAL_DISABLED) {
                        $it->itemoptional = CHECKLIST_OPTIONAL_NO;
                        $upditem = new stdClass;
                        $upditem->id = $it->id;
                        $upditem->itemoptional = $it->itemoptional;
                        $DB->update_record('checklist_item', $upditem);
                    }
                }

            } elseif ($item->itemoptional == CHECKLIST_OPTIONAL_HEADING_DISABLED) {
                foreach ($this->items as $it) {
                    if ($it->position <= $item->position) {
                        continue;
                    }
                    if (($it->itemoptional == CHECKLIST_OPTIONAL_HEADING) || ($it->itemoptional == CHECKLIST_OPTIONAL_HEADING_DISABLED)) {
                        break;
                    }
                    if (!$it->moduleid) {
                        continue;
                    }
                    if (($it->itemoptional == CHECKLIST_OPTIONAL_YES) || ($it->itemoptional == CHECKLIST_OPTIONAL_NO)) {
                        $it->itemoptional = CHECKLIST_OPTIONAL_DISABLED;
                        $upditem = new stdClass;
                        $upditem->id = $it->id;
                        $upditem->itemoptional = $it->itemoptional;
                        $DB->update_record('checklist_item', $upditem);
                    }
                }
            }
        }
    }

    function deleteitem($itemid) {
        global $DB;
        
        if (isset($this->items[$itemid])) {
            if (!$this->canedit()) {
                return;
            }
            $this->setevent($itemid, false); // Remove any calendar events
            unset($this->items[$itemid]);
        } elseif (isset($this->useritems[$itemid])) {
            if (!$this->canaddown()) {
                return;
            }
            unset($this->useritems[$itemid]);
        } else {
            // Item for deletion is not currently available
            return;
        }

        $DB->delete_records('checklist_item', array('id' => $itemid) );
        $DB->delete_records('checklist_check', array('item' => $itemid) );

        $this->update_item_positions();
    }

    function moveitemto($itemid, $newposition) {
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

        if (!$this->canedit()) {
            return;
        }

        $itemcount = count($this->items);
        if ($newposition < 1) {
            $newposition = 1;
        } elseif ($newposition > $itemcount) {
            $newposition = $itemcount;
        }

        $oldposition = $this->items[$itemid]->position;
        if ($oldposition == $newposition) {
            return;
        }

        if ($newposition < $oldposition) {            
            $this->update_item_positions(1, $newposition, $oldposition); // Move items down
        } else {
            $this->update_item_positions(-1, $oldposition, $newposition); // Move items up (including this one)
        }
        
        $this->items[$itemid]->position = $newposition; // Move item to new position
        uasort($this->items, 'checklist_itemcompare'); // Sort the array by position
        $upditem = new stdClass;
        $upditem->id = $itemid;
        $upditem->position = $newposition;
        $DB->update_record('checklist_item', $upditem); // Update the database
    }

    function moveitemup($itemid) {
        // TODO If indented, only allow move if suitable space for 'reparenting'

        if (!isset($this->items[$itemid])) {
            if (isset($this->useritems[$itemid])) {
                $this->moveitemto($itemid, $this->useritems[$itemid]->position - 1);
            }
            return;
        }
        $this->moveitemto($itemid, $this->items[$itemid]->position - 1);
    }

    function moveitemdown($itemid) {
        // TODO If indented, only allow move if suitable space for 'reparenting'

        if (!isset($this->items[$itemid])) {
            if (isset($this->useritems[$itemid])) {
                $this->moveitemto($itemid, $this->useritems[$itemid]->position + 1);
            }
            return;
        }
        $this->moveitemto($itemid, $this->items[$itemid]->position + 1);
    }
        
    function indentitemto($itemid, $indent) {
        global $DB;
        
        if (!isset($this->items[$itemid])) {
            // Not able to indent useritems, as they are always parent + 1
            return;
        }

        $position = $this->items[$itemid]->position;
        if ($position == 1) {
            $indent = 0;
        }
        
        if ($indent < 0) {
            $indent = 0;
        } elseif ($indent > CHECKLIST_MAX_INDENT) {
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

        // Update all 'children' of this item to new indent
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

    function indentitem($itemid) {
        if (!isset($this->items[$itemid])) {
            // Not able to indent useritems, as they are always parent + 1
            return;
        }
        $this->indentitemto($itemid, $this->items[$itemid]->indent + 1);
    }

    function unindentitem($itemid) {
        if (!isset($this->items[$itemid])) {
            // Not able to indent useritems, as they are always parent + 1
            return;
        }
        $this->indentitemto($itemid, $this->items[$itemid]->indent - 1);
    }

    function makeoptional($itemid, $optional, $heading=false) {
        global $DB;
        
        if (!isset($this->items[$itemid])) {
            return;
        }

        if ($heading) {
            $optional = 2;
        } elseif ($optional) {
            $optional = 1;
        } else {
            $optional = 0;
        }

        if ($this->items[$itemid]->moduleid) {
            $op = $this->items[$itemid]->itemoptional;
            if (($op == CHECKLIST_OPTIONAL_HEADING)||($op == CHECKLSIT_OPTIONAL_HEADING_DISABLED)||($op == CHECKLIST_OPTIONAL_DISABLED)) {
                return; // Topic headings must stay as headings (and disabled items should not change either)
            } elseif ($this->items[$itemid]->itemoptional == CHECKLIST_OPTIONAL_YES) {
                $optional = 0; // Module links cannot become headings
            } else {
                $optional = 1;
            }
        }

        $this->items[$itemid]->itemoptional = $optional;
        $upditem = new stdClass;
        $upditem->id = $itemid;
        $upditem->itemoptional = $optional;
        $DB->update_record('checklist_item', $upditem);
    }

    function nextcolour($itemid) {
        global $DB;
        
        if (!isset($this->items[$itemid])) {
            return;
        }

        switch ($this->items[$itemid]->colour) {
        case 'black':
            $nextcolour='red';
            break;
        case 'red':
            $nextcolour='orange';
            break;
        case 'orange':
            $nextcolour='green';
            break;
        case 'green':
            $nextcolour='purple';
            break;
        default:
            $nextcolour='black';
        }

        $upditem = new stdClass;
        $upditem->id = $itemid;
        $upditem->colour = $nextcolour;
        $DB->update_record('checklist_item', $upditem);
        $this->items[$itemid]->colour = $nextcolour;
    }

    function updatechecks() {
        global $DB;
        
        $newchecks = optional_param('items', array(), PARAM_INT);
        if (!is_array($newchecks)) {
            // Something has gone wrong, so update nothing
            return;
        }
        
        add_to_log($this->course->id, 'checklist', 'update checks', "report.php?id={$this->cm->id}&studentid={$this->userid}", $this->checklist->name, $this->cm->id);

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
                
                    $check = $DB->get_record('checklist_check', array('item' => $item->id, 'userid' => $this->userid) );
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
                
                    $check = $DB->get_record('checklist_check', array('item' => $item->id, 'userid' => $this->userid) );
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

    function updateteachermarks() {
        global $USER, $DB;
        
        $newchecks = optional_param('items', array(), PARAM_TEXT);
        if (!is_array($newchecks)) {
            // Something has gone wrong, so update nothing
            return;
        }

        $updategrades = false;
        if ($this->checklist->teacheredit != CHECKLIST_MARKING_STUDENT) {
            if (!$student = get_record('user', 'id', $this->userid)) {
                error('No such user!');
            }
            $info = $this->checklist->name.' ('.fullname($student, true).')';
            add_to_log($this->course->id, 'checklist', 'update checks', "report.php?id={$this->cm->id}&studentid={$this->userid}", $info, $this->cm->id);

            foreach ($newchecks as $itemid => $newval) {
                if (isset($this->items[$itemid])) {
                    $item = $this->items[$itemid];
                    if ($newval != $item->teachermark) {
                        $updategrades = true;
                        $item->teachermark = $newval;
                    
                        $newcheck = new stdClass;
                        $newcheck->teachertimestamp = time();
                        $newcheck->teachermark = $newval;
                    
                        $oldcheck = $DB->get_record('checklist_check', array('item' => $item->id, 'userid' => $this->userid) );
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

        $newcomments = optional_param('teachercomment', false, PARAM_TEXT);
        if (!$this->checklist->teachercomments || !$newcomments || !is_array($newcomments)) {
            return;
        }

        list($isql, $iparams) = $DB->get_in_or_equal(array_keys($this->items));
        $commentsunsorted = $DB->get_records_select('checklist_comment',"userid = ? AND itemid $isql", array_merge(array($this->userid), $iparams) );
        $comments = array();
        foreach ($commentsunsorted as $comment) {
            $comments[$comment->itemid] = $comment;
        }
        foreach ($newcomments as $itemid => $newcomment) {
            $newcomment = trim($newcomment);
            if ($newcomment == '') {
                if (array_key_exists($itemid, $comments)) {
                    $DB->delete_records('checklist_comment', array('id' => $comments[$itemid]->id) );
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

                        $DB->update_record('checklist_comment',$updatecomment);
                    }
                } else {
                    $addcomment = new stdClass;
                    $addcomment->itemid = $itemid;
                    $addcomment->userid = $this->userid;
                    $addcomment->commentby = $USER->id;
                    $addcomment->text = $newcomment;

                    $DB->insert_record('checklist_comment',$addcomment);
                }
            }
        }

    }

     // Update the userid to point to the next user to view
    function getnextuserid() {
        global $DB;

        $activegroup = groups_get_activity_group($this->cm, true);
        switch ($this->sortby) {
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
        if ($users = get_users_by_capability($this->context, 'mod/checklist:updateown', 'u.id', '', '', '', $activegroup, '', false)) {
            list($usql, $uparams) = $DB->get_in_or_equal(array_keys($users));
            $ausers = $DB->get_records_sql('SELECT u.id FROM {user} u WHERE u.id '.$usql.$orderby, $uparams);
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

    static function print_user_progressbar($checklistid, $userid, $width='300px', $showpercent=true, $return=false) {
        global $OUTPUT;

        list($ticked, $total) = checklist_class::get_user_progress($checklistid, $userid);
        if (!$total) {
            return;
        }
        $percent = $ticked * 100 / $total;

        // TODO - fix this now that styles.css is included
        $output = '<div class="checklist_progress_outer" style="border-width: 1px; border-style: solid; border-color: black; width: '.$width.'; background-colour: transparent; height: 15px; float: left;" >';
        $output .= '<div class="checklist_progress_inner" style="width:'.$percent.'%; background-image: url('.$OUTPUT->pix_url('progress','checklist').'); background-color: #229b15; height: 100%; background-repeat: repeat-x; background-position: top;" >&nbsp;</div>';
        $output .= '</div>';
        if ($showpercent) {
            $output .= '<div style="float:left; width: 3em;">&nbsp;'.sprintf('%0d%%', $percent).'</div>';
        }
        $output .= '<br style="clear:both;" />';
        if ($return) {
            return $output;
        } else {
            echo $output;
        }
    }

    static function get_user_progress($checklistid, $userid) {
        global $DB;
        
        $userid = intval($userid); // Just to be on the safe side...

        $checklist = $DB->get_record('checklist', array('id' => $checklistid) );
        if (!$checklist) {
            return array(false, false);
        }
        $items = $DB->get_records('checklist_item', array('checklist' => $checklist->id, 'userid' => 0, 'itemoptional' => CHECKLIST_OPTIONAL_NO), '', 'id');
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
}

function checklist_itemcompare($item1, $item2) {
    if ($item1->position < $item2->position) {
        return -1;
    } elseif ($item1->position > $item2->position) {
        return 1;
    }
    if ($item1->id < $item2->id) {
        return -1;
    } elseif ($item1->id > $item2->id) {
        return 1;
    }
    return 0;
}



?>
