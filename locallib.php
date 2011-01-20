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
        global $COURSE;

        if ($cmid == 'staticonly') {
            //use static functions only!
            return;
        }

        $this->userid = $userid;

        global $CFG;

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
        } else if (! $this->course = get_record('course', 'id', $this->cm->course)) {
            error('Course is misconfigured');
        }

        if ($checklist) {
            $this->checklist = $checklist;
        } else if (! $this->checklist = get_record('checklist', 'id', $this->cm->instance)) {
            error('assignment ID was incorrect');
        }

        $this->strchecklist = get_string('modulename', 'checklist');
        $this->strchecklists = get_string('modulenameplural', 'checklist');
        $this->pagetitle = strip_tags($this->course->shortname.': '.$this->strchecklist.': '.format_string($this->checklist->name,true));

        $this->get_items();

        if ($this->checklist->autopopulate) {
            $this->update_items_from_course();
        }
    }

    /**
     * Get an array of the items in a checklist
     * 
     */
    function get_items() {
        global $CFG;
        
        // Load all shared checklist items
        $sql = 'checklist = '.$this->checklist->id;
        $sql .= ' AND userid = 0';
        $this->items = get_records_select('checklist_item', $sql, 'position');

        // Makes sure all items are numbered sequentially, starting at 1
        $this->update_item_positions();

        // Load student's own checklist items
        if ($this->userid && $this->canaddown()) {
            $sql = 'checklist = '.$this->checklist->id;
            $sql .= ' AND userid = '.$this->userid;
            $this->useritems = get_records_select('checklist_item', $sql, 'position, id');
        } else {
            $this->useritems = false;
        }

        if ($this->items) {
            foreach ($this->items as $key=>$item) {
                $this->items[$key]->checked = false;
            }
        }
        if ($this->useritems) {
            foreach ($this->useritems as $key=>$item) {
                $this->items[$key]->checked = false;
            }
        }

        // Load the currently checked-off items
        if ($this->userid) { // && ($this->canupdateown() || $this->canviewreports() )) {

            $sql = 'SELECT i.id, c.usertimestamp, c.teachermark FROM '.$CFG->prefix.'checklist_item i LEFT JOIN '.$CFG->prefix.'checklist_check c ';
            $sql .= 'ON (i.id = c.item AND c.userid = '.$this->userid.') WHERE i.checklist = '.$this->checklist->id;

            $checks = get_records_sql($sql);

            if ($checks) {
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

        $changes = false;

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
                $modname = $mods->cms[$cmid]->modname;
                if ($modname == 'label') {
                    continue; // Ignore any labels
                }
                if ($modname == 'assignment' || $modname == 'quiz' || $modname == 'forum') {
                    $showscore = true;
                } else {
                    $showscore = false;
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
                    $this->items[$item->id]->stillexists = true;
                    $this->items[$item->id]->showscore = $showscore;
                    if ($item->position != $nextpos) {
                        //echo 'reposition '.$item->displaytext.' => '.$nextpos.'<br/>';
                        $this->moveitemto($item->id, $nextpos);
                        reset($this->items);
                    }
                    if ($item->displaytext != $modname) {
                        $this->updateitemtext($item->id, addslashes($modname));
                    }
                } else {
                    $name = addslashes($modname);
                    //echo '+++adding item '.$name.' at '.$nextpos.'<br/>';
                    $itemid = $this->additem($name, 0, 0, $nextpos, false, $cmid);
                    $changes = true;
                    reset($this->items);
                    $this->items[$itemid]->stillexists = true;
                    $this->items[$itemid]->showscore = $showscore;
                    $this->items[$itemid]->checked = false;
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

        if ($changes) {
            update_all_checks_from_completion_scores();
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
        $pos = 1;

        if (!$this->items) {
            return;
        }
        foreach($this->items as $key=>$item) {
            if ($pos == $start) {
                $pos += $move;
                $start = -1;
            }
            if ($item->position != $pos) {
                $oldpos = $item->position;
                $this->items[$key]->position = $pos;
                $upditem = new stdClass;
                $upditem->id = $item->id;
                $upditem->position = $pos;
                update_record('checklist_item', $upditem);
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
        global $CFG;
        
        $this->view_header();

        print_heading(format_string($this->checklist->name));

        if ($this->canupdateown()) {
            $currenttab = 'view';
        } elseif ($this->canpreview()) {
            $currenttab = 'preview';
        } else {
            $loginurl = $CFG->wwwroot.'/login/index.php';
            if (!empty($CFG->loginhttps)) {
                $loginurl = str_replace('http:','https:', $loginurl);
            }
            echo '<br/>';
            notice_yesno('<p>' . get_string('guestsno', 'checklist') . "</p>\n\n</p>" .
                         get_string('liketologin') . '</p>', $loginurl, get_referer(false));
            print_footer($this->course);
            die;
        }

        $this->view_tabs($currenttab);

        if ((!$this->items) && $this->canedit()) {
            redirect($CFG->wwwroot.'/mod/checklist/edit.php?id='.$this->cm->id, get_string('noitems','checklist'));
        }

        add_to_log($this->course->id, 'checklist', 'view', "view.php?id={$this->cm->id}", addslashes($this->checklist->name), $this->cm->id);        

        if ($this->canupdateown()) {
            $this->process_view_actions();
        }

        $this->view_items();

        $this->view_footer();
    }


    function edit() {
        global $CFG;
        
        if (!$this->canedit()) {
            redirect($CFG->wwwroot.'/mod/checklist/view.php?id='.$this->cm->id);
        }

        add_to_log($this->course->id, "checklist", "edit", "edit.php?id={$this->cm->id}", addslashes($this->checklist->name), $this->cm->id);

        $this->view_header();

        print_heading(format_string($this->checklist->name));

        $this->view_tabs('edit');

        $this->process_edit_actions();

        if ($this->checklist->autopopulate) {
            $this->update_items_from_course();
        }

        $this->view_edit_items();

        $this->view_footer();
    }

    function report() {
        global $CFG;

        if (!$this->canviewreports()) {
            redirect($CFG->wwwroot.'/mod/checklist/view.php?id='.$this->cm->id);
        }

        if (!$this->caneditother()) {
            $this->userid = false;
        }

        $this->view_header();

        print_heading(format_string($this->checklist->name));

        $this->view_tabs('report');

        if ((!$this->items) && $this->canedit()) {
            redirect($CFG->wwwroot.'/mod/checklist/edit.php?id='.$this->cm->id, get_string('noitems','checklist'));
        }

        $this->process_report_actions();

        if ($this->userid) {
            $this->view_items(true);
        } else {
            add_to_log($this->course->id, "checklist", "report", "report.php?id={$this->cm->id}", addslashes($this->checklist->name), $this->cm->id);
            $this->view_report();
        }
        
        $this->view_footer();
    }

    function user_complete() {
        $this->view_items(false, true);
    }

    function view_header() {
        $navlinks = array();
        $navlinks[] = array('name' => $this->strchecklists, 'link' => "index.php?id={$this->course->id}", 'type' => 'activity');
        $navlinks[] = array('name' => format_string($this->checklist->name), 'link' => '', 'type' => 'activityinstance');

        $navigation = build_navigation($navlinks);

        print_header_simple($this->pagetitle, '', $navigation, '', '', true,
                            update_module_button($this->cm->id, $this->course->id, $this->strchecklist), navmenu($this->course, $this->cm));
    }

    function view_tabs($currenttab) {
        global $CFG;
        
        $tabs = array();
        $row = array();
        $inactive = array();
        $activated = array();

        if ($this->canupdateown()) {
            $row[] = new tabobject('view', "$CFG->wwwroot/mod/checklist/view.php?id={$this->cm->id}", get_string('view', 'checklist'));
        } elseif ($this->canpreview()) {
            $row[] = new tabobject('preview', "$CFG->wwwroot/mod/checklist/view.php?id={$this->cm->id}", get_string('preview', 'checklist'));
        }
        if ($this->canviewreports()) {
            $row[] = new tabobject('report', "$CFG->wwwroot/mod/checklist/report.php?id={$this->cm->id}", get_string('report', 'checklist'));
        }
        if ($this->canedit()) {
            $row[] = new tabobject('edit', "$CFG->wwwroot/mod/checklist/edit.php?id={$this->cm->id}", get_string('edit', 'checklist'));
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
        global $CFG;

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
            echo '<div class="checklist_progress_inner" style="width:'.$percentcomplete.'%; background-image: url('.$CFG->wwwroot.'/mod/checklist/images/progress.gif);" >&nbsp;</div>';
            echo '</div>';
            echo '&nbsp;'.sprintf('%0d',$percentcomplete).'%';
            echo '<br style="clear:both"/>';
        }
        
        echo '<div style="display:block; float:left; width:150px;">';
        echo get_string('percentcompleteall','checklist').':&nbsp;';
        echo '</div>';
        echo '<div class="checklist_progress_outer">';
        echo '<div class="checklist_progress_inner" style="width:'.$allpercentcomplete.'%; background-image: url('.$CFG->wwwroot.'/mod/checklist/images/progress.gif);" >&nbsp;</div>';
        echo '</div>';
        echo '&nbsp;'.sprintf('%0d',$allpercentcomplete).'%';
        echo '<br style="clear:both"/>';
    }

    function get_teachermark($itemid) {
        global $CFG;

        if (!isset($this->items[$itemid])) {
            return array('','');
        }
        $basepath = $CFG->wwwroot.'/mod/checklist/images/';
        switch ($this->items[$itemid]->teachermark) {
        case CHECKLIST_TEACHERMARK_YES:
            return array($basepath.'tick_box.gif',get_string('teachermarkyes','checklist'));

        case CHECKLIST_TEACHERMARK_NO:
            return array($basepath.'cross_box.gif',get_string('teachermarkno','checklist'));

        default:
            return array($basepath.'empty_box.gif',get_string('teachermarkundecided','checklist'));
        }
    }

    function view_items($viewother = false, $userreport = false) {
        global $CFG;
        
        print_box_start('generalbox boxwidthwide boxaligncenter');

        $comments = $this->checklist->teachercomments;
        $editcomments = false;
        $thispage = $CFG->wwwroot.'/mod/checklist/view.php?id='.$this->cm->id;
        if ($viewother) {
            $showbars = optional_param('showbars',false,PARAM_BOOL);
            if ($comments) {
                $editcomments = optional_param('editcomments', false, PARAM_BOOL);
            }
            $thispage = $CFG->wwwroot.'/mod/checklist/report.php?id='.$this->cm->id;
            if (!$student = get_record('user', 'id', $this->userid)) {
                error('No such user!');
            }

            $info = addslashes($this->checklist->name).' ('.fullname($student, true).')';
            add_to_log($this->course->id, "checklist", "report", "report.php?id={$this->cm->id}&studentid={$this->userid}", $info, $this->cm->id);
            
            echo '<h2>'.get_string('checklistfor','checklist').' '.fullname($student, true);
            echo '&nbsp;<form style="display: inline;" action="'.$thispage.'" method="get">';
            echo '<input type="hidden" name="id" value="'.$this->cm->id.'" />';
            echo $showbars ? '<input type="hidden" name="showbars" value="on" />' : '';
            echo '<input type="hidden" name="sortby" value="'.$this->sortby.'" />';
            echo '<input type="submit" name="viewall" value="'.get_string('viewall','checklist').'" />';
            echo '</form>';

            if (!$editcomments) {
                echo '<form style="display: inline;" action="'.$thispage.'" method="get">';
                echo '<input type="hidden" name="id" value="'.$this->cm->id.'" />';
                echo $showbars ? '<input type="hidden" name="showbars" value="on" />' : '';
                echo '<input type="hidden" name="sortby" value="'.$this->sortby.'" />';
                echo '<input type="hidden" name="editcomments" value="on" />';
                echo '<input type="hidden" name="studentid" value="'.$this->userid.'" />';
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
                    echo '<form style="display:inline;" action="'.$thispage.'" method="get">';
                    echo '<input type="hidden" name="id" value="'.$this->cm->id.'" />';
                    if ($addown) {
                        echo '<input type="hidden" name="useredit" value="off" />';
                        echo '<input type="submit" name="submit" value="'.get_string('addownitems-stop','checklist').'" />';
                    } else {
                        echo '<input type="hidden" name="useredit" value="on" />';
                        echo '<input type="submit" name="submit" value="'.get_string('addownitems','checklist').'" />';
                    }
                    echo '</form>';
                }
                echo '<form action="'.$thispage.'" method="post">';
                echo '<input type="hidden" name="id" value="'.$this->cm->id.'" />';
                echo '<input type="hidden" name="action" value="updatechecks" />';
                echo '<input type="hidden" name="sesskey" value="'.sesskey().'" />';
                if ($viewother) {
                    echo '<input type="hidden" name="studentid" value="'.$this->userid.'" />';
                }
                if ($addown) {
                    echo '<input type="hidden" name="useredit" value="on" />';
                }
            }

            if ($this->useritems) {
                reset($this->useritems);
            }

            if ($comments) {
                $itemids = implode(',',array_keys($this->items));
                $commentsunsorted = get_records_select('checklist_comment',"userid = {$this->userid} AND itemid IN ({$itemids})");
                $commentuserids = array();
                $commentusers = array();
                if ($commentsunsorted) {
                    $comments = array();
                    foreach ($commentsunsorted as $comment) {
                        $comments[$comment->itemid] = $comment;
                        if ($comment->commentby) {
                            $commentuserids[] = $comment->commentby;
                        }
                    }
                    if (!empty($commentuserids)) {
                        $commentuserids = implode(",",array_unique($commentuserids, SORT_NUMERIC));
                        $commentusers = get_records_select('user', 'id IN ('.$commentuserids.')');
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
                    $spacerimg = $CFG->wwwroot.'/mod/checklist/images/check_spacer.gif';
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
                        
                            echo '<select name="items[]" >';
                            echo '<option value="'.$item->id.':'.CHECKLIST_TEACHERMARK_UNDECIDED.'" '.$selu.'></option>';
                            echo '<option value="'.$item->id.':'.CHECKLIST_TEACHERMARK_YES.'" '.$sely.'>'.get_string('yes').'</option>';
                            echo '<option value="'.$item->id.':'.CHECKLIST_TEACHERMARK_NO.'" '.$seln.'>'.get_string('no').'</option>';
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
                    $baseurl = $thispage.'&amp;itemid='.$item->id.'&amp;sesskey='.sesskey().'&amp;action=';
                    echo '&nbsp;<a href="'.$baseurl.'startadditem">';
                    $title = '"'.get_string('additemalt','checklist').'"';
                    echo '<img src="'.$CFG->wwwroot.'/mod/checklist/images/add.png" alt='.$title.' title='.$title.' /></a>';
                }

                if ($item->duetime) {
                    if ($item->duetime > time()) {
                        echo '<span class="itemdue"> '.userdate($item->duetime, get_string('strftimedate')).'</span>';
                    } else {
                        echo '<span class="itemoverdue"> '.userdate($item->duetime, get_string('strftimedate')).'</span>';
                    }
                }

                $foundcomment = false;
                if ($comments) {
                    if (array_key_exists($item->id, $comments)) {
                        $comment =  $comments[$item->id];
                        $foundcomment = true;
                        echo ' <span class="teachercomment">&nbsp;';
                        if ($comment->commentby) {
                            echo '<a href="'.$CFG->wwwroot.'/user/view.php?id='.$comment->commentby.'&amp;course='.$this->course->id.'">'.fullname($commentusers[$comment->commentby]).'</a>: ';
                        }
                        if ($editcomments) {
                            echo '<input type="text" name="teachercomment['.$item->id.']" value="'.s($comment->text).'" />';
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
                        echo '<ol class="checklist">';
                        while ($useritem && ($useritem->position == $item->position)) {
                            $itemname = '"item'.$useritem->id.'"';
                            $checked = ($updateform && $useritem->checked) ? ' checked="checked" ' : '';
                            if (isset($useritem->editme)) {
                                $itemtext = explode("\n", $useritem->displaytext, 2);
                                $itemtext[] = '';
                                $text = $itemtext[0];
                                $note = $itemtext[1];
                                echo '<li>';
                                echo '<div style="float: left;">';
                                if ($showcheckbox) {
                                    echo '<input type="checkbox" name="items[]" id='.$itemname.$checked.' disabled="disabled" value="'.$useritem->id.'" />';
                                }
                                echo '<form style="display:inline" action="'.$thispage.'" method="post">';
                                echo '<input type="hidden" name="action" value="updateitem" />';
                                echo '<input type="hidden" name="id" value="'.$this->cm->id.'" />';
                                echo '<input type="hidden" name="itemid" value="'.$useritem->id.'" />';
                                echo '<input type="hidden" name="sesskey" value="'.sesskey().'" />';
                                echo '<input type="text" size="'.CHECKLIST_TEXT_INPUT_WIDTH.'" name="displaytext" value="'.s($text).'" id="updateitembox" />';
                                echo '<input type="submit" name="updateitem" value="'.get_string('updateitem','checklist').'" />';
                                echo '<br />';
                                echo '<textarea name="displaytextnote" rows="3" cols="25">'.s($note).'</textarea>';
                                echo '</form>';
                                echo '</div>';
                                echo '<form style="display:inline;" action="'.$thispage.'" method="get">';
                                echo '<input type="hidden" name="id" value="'.$this->cm->id.'" />';
                                echo '<input type="hidden" name="useredit" value="on" />';
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
                                    echo '<img src="'.$CFG->pixpath.'/t/edit.gif" alt='.$title.' title='.$title.' /></a>';

                                    echo '&nbsp;<a href="'.$baseurl.'deleteitem" class="deleteicon">';
                                    $title = '"'.get_string('deleteitem','checklist').'"';
                                    echo '<img src="'.$CFG->wwwroot.'/mod/checklist/images/remove.png" alt='.$title.' title='.$title.' /></a>';
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
                    echo '<ol class="checklist"><li>';
                    echo '<div style="float: left;">';
                    echo '<form action="'.$thispage.'" method="post">';
                    echo '<input type="hidden" name="action" value="additem" />';
                    echo '<input type="hidden" name="id" value="'.$this->cm->id.'" />';
                    echo '<input type="hidden" name="position" value="'.$item->position.'" />';
                    echo '<input type="hidden" name="sesskey" value="'.sesskey().'" />';
                    if ($showcheckbox) {
                        echo '<input type="checkbox" disabled="disabled" />';
                    }
                    echo '<input type="text" size="'.CHECKLIST_TEXT_INPUT_WIDTH.'" name="displaytext" value="" id="additembox" />';
                    echo '<input type="submit" name="additem" value="'.get_string('additem','checklist').'" />';
                    echo '<br />';
                    echo '<textarea name="displaytextnote" rows="3" cols="25"></textarea>';
                    echo '</form>';
                    echo '</div>';
                    echo '<form style="display:inline" action="'.$thispage.'" method="get">';
                    echo '<input type="hidden" name="id" value="'.$this->cm->id.'" />';
                    echo '<input type="hidden" name="useredit" value="on" />';
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
                echo '<input type="hidden" name="sortby" value="'.$this->sortby.'" />';
                if ($viewother) {
                    echo '<input type="submit" name="savenext" value="'.get_string('saveandnext').'" />';
                    echo '<input type="submit" name="viewnext" value="'.get_string('next').'" />';
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

        print_box_end();
    }

    function print_edit_date($ts=0) {
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
        global $CFG;
        
        print_box_start('generalbox boxwidthwide boxaligncenter');

        $currindent = 0;
        $addatend = true;
        $focusitem = false;
        $hasauto = false;

        if ($this->checklist->autopopulate && $this->checklist->autoupdate) {
            $url = "{$CFG->wwwroot}/mod/checklist/edit.php?id={$this->cm->id}&amp;sesskey=".sesskey();
            $url .= ($this->additemafter) ? '&amp;additemafter='.$this->additemafter : '';
            $url .= ($this->editdates) ? '&amp;editdates=on' : '';
            echo "<form action='$url' method='POST'>";
            echo '<input type="submit" name="update_complete_score" value="'.get_string('updatecompletescore','checklist').'" />';
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
                $baseurl = $CFG->wwwroot.'/mod/checklist/edit.php?id='.$this->cm->id.'&amp;itemid='.$item->id.'&amp;sesskey='.sesskey();
                $baseurl .= ($this->additemafter) ? '&amp;additemafter='.$this->additemafter : '';
                $baseurl .= ($this->editdates) ? '&amp;editdates=on' : '';
                $baseurl .= '&amp;action=';

                switch ($item->colour) {
                case 'red':
                    $itemcolour = 'itemred';
                    $nexticon = 'colour_orange.gif';
                    break;
                case 'orange':
                    $itemcolour = 'itemorange';
                    $nexticon = 'colour_green.gif';
                    break;
                case 'green':
                    $itemcolour = 'itemgreen';
                    $nexticon = 'colour_purple.gif';
                    break;
                case 'purple':
                    $itemcolour = 'itempurple';
                    $nexticon = 'colour_black.gif';
                    break;
                default:
                    $itemcolour = 'itemblack';
                    $nexticon = 'colour_red.gif';
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
                    echo '<a href="'.$baseurl.'makeheading">';
                    echo '<img src="'.$CFG->wwwroot.'/mod/checklist/images/empty_box.gif" alt='.$title.' title='.$title.' /></a>&nbsp;';
                    $optional = ' class="itemoptional '.$itemcolour.$autoclass.'" ';
                } elseif ($item->itemoptional == CHECKLIST_OPTIONAL_HEADING) {
                    $title = '"'.get_string('headingitem','checklist').'"';
                    if (!$autoitem) { echo '<a href="'.$baseurl.'makerequired">'; }
                    echo '<img src="'.$CFG->wwwroot.'/mod/checklist/images/no_box.gif" alt='.$title.' title='.$title.' />';
                    if (!$autoitem) { echo '</a>'; }
                    echo '&nbsp;';
                    $optional = ' class="itemheading '.$itemcolour.$autoclass.'" ';
                } elseif ($item->itemoptional == CHECKLIST_OPTIONAL_DISABLED) {
                    $title = '"'.get_string('requireditem','checklist').'"';
                    echo '<img src="'.$CFG->wwwroot.'/mod/checklist/images/tick_box.gif" alt='.$title.' title='.$title.' />&nbsp;';
                    $optional = ' class="'.$itemcolour.$autoclass.' itemdisabled"';
                } elseif ($item->itemoptional == CHECKLIST_OPTIONAL_HEADING_DISABLED) {
                    $title = '"'.get_string('headingitem','checklist').'"';
                    echo '<img src="'.$CFG->wwwroot.'/mod/checklist/images/no_box.gif" alt='.$title.' title='.$title.' />&nbsp;';
                    $optional = ' class="'.$itemcolour.$autoclass.' itemdisabled"';
                } else {
                    $title = '"'.get_string('requireditem','checklist').'"';
                    echo '<a href="'.$baseurl.'makeoptional">';
                    echo '<img src="'.$CFG->wwwroot.'/mod/checklist/images/tick_box.gif" alt='.$title.' title='.$title.' /></a>&nbsp;';
                    $optional = ' class="'.$itemcolour.$autoclass.'"';
                }

                if (isset($item->editme)) {
                    echo '<form style="display:inline" action="'.$CFG->wwwroot.'/mod/checklist/edit.php" method="post">';
                    echo '<input type="hidden" name="action" value="updateitem" />';
                    echo '<input type="hidden" name="id" value="'.$this->cm->id.'" />';
                    echo '<input type="hidden" name="itemid" value="'.$item->id.'" />';
                    echo '<input type="hidden" name="sesskey" value="'.sesskey().'" />';
                    echo '<input type="text" size="'.CHECKLIST_TEXT_INPUT_WIDTH.'" name="displaytext" value="'.s($item->displaytext).'" id="updateitembox" />';
                    if ($this->editdates) {
                        echo '<input type="hidden" name="editdates" value="on" />';
                        $this->print_edit_date($item->duetime);
                    }
                    echo '<input type="submit" name="updateitem" value="'.get_string('updateitem','checklist').'" />';
                    echo '</form>';

                    $focusitem = 'updateitembox';

                    echo '<form style="display:inline" action="'.$CFG->wwwroot.'/mod/checklist/edit.php" method="get">';
                    echo '<input type="hidden" name="id" value="'.$this->cm->id.'" />';
                    if ($this->editdates) {
                        echo '<input type="hidden" name="editdates" value="on" />';
                    }
                    if ($this->additemafter) {
                        echo '<input type="hidden" name="additemafter" value="'.$this->additemafter.'" />';
                    }
                    echo '<input type="submit" name="canceledititem" value="'.get_string('canceledititem','checklist').'" />';
                    echo '</form>';
                } else {
                    echo '<label for='.$itemname.$optional.'>'.s($item->displaytext).'</label>&nbsp;';

                    echo '<a href="'.$baseurl.'nextcolour">';
                    $title = '"'.get_string('changetextcolour','checklist').'"';
                    echo '<img src="'.$CFG->wwwroot.'/mod/checklist/images/'.$nexticon.'" alt='.$title.' title='.$title.' /></a>';

                    if (!$autoitem) {
                        echo '<a href="'.$baseurl.'edititem">';
                        $title = '"'.get_string('edititem','checklist').'"';
                        echo '<img src="'.$CFG->pixpath.'/t/edit.gif"  alt='.$title.' title='.$title.' /></a>&nbsp;';
                    }

                    if (!$autoitem && $item->indent > 0) {
                        echo '<a href="'.$baseurl.'unindentitem">';
                        $title = '"'.get_string('unindentitem','checklist').'"';
                        echo '<img src="'.$CFG->pixpath.'/t/left.gif" alt='.$title.' title='.$title.'  /></a>';
                    }

                    if (!$autoitem && ($item->indent < CHECKLIST_MAX_INDENT) && (($lastindent+1) > $currindent)) {
                        echo '<a href="'.$baseurl.'indentitem">';
                        $title = '"'.get_string('indentitem','checklist').'"';
                        echo '<img src="'.$CFG->pixpath.'/t/right.gif" alt='.$title.' title='.$title.' /></a>';
                    }

                    echo '&nbsp;';
                    
                    // TODO more complex checks to take into account indentation
                    if (!$autoitem && $item->position > 1) {
                        echo '<a href="'.$baseurl.'moveitemup">';
                    $title = '"'.get_string('moveitemup','checklist').'"';
                    echo '<img src="'.$CFG->pixpath.'/t/up.gif" alt='.$title.' title='.$title.' /></a>';
                    }

                    if (!$autoitem && $item->position < $lastitem) {
                        echo '<a href="'.$baseurl.'moveitemdown">';
                    $title = '"'.get_string('moveitemdown','checklist').'"';
                    echo '<img src="'.$CFG->pixpath.'/t/down.gif" alt='.$title.' title='.$title.' /></a>';
                    }

                    if ($autoitem) {
                        echo '&nbsp;&nbsp;<a href="'.$baseurl.'deleteitem">';
                        if (($item->itemoptional == CHECKLIST_OPTIONAL_DISABLED) || ($item->itemoptional == CHECKLIST_OPTIONAL_HEADING_DISABLED)) {
                            $title = '"'.get_string('show').'"';
                            echo '<img src="'.$CFG->pixpath.'/t/show.gif" alt='.$title.' title='.$title.' /></a>';
                        } else {
                            $title = '"'.get_string('hide').'"';
                            echo '<img src="'.$CFG->pixpath.'/t/hide.gif" alt='.$title.' title='.$title.' /></a>';
                        }

                        if (isset($item->showscore) && $item->showscore) {
                            echo '&nbsp;<span class="itemauto">';
                            print_string('gradetocomplete','checklist');
                            echo "&nbsp;<select name='complete_score[$item->id]'>";
                            if ($item->complete_score == 0) {
                                echo '<option value="0" selected="selected">'.get_string('anygrade','checklist').'</option>';
                            } else {
                                echo '<option value="0">'.get_string('anygrade','checklist').'</option>';
                            }
                            for ($sc = 100; $sc > 0; $sc--) {
                                if ($item->complete_score == $sc) {
                                    echo "<option value='$sc' selected='selected'>$sc</option>";
                                } else {
                                    echo "<option value='$sc'>$sc</option>";
                                }
                            }
                            echo '</select></span>';
                        }

                    } else {
                        echo '&nbsp;<a href="'.$baseurl.'deleteitem">';
                        $title = '"'.get_string('deleteitem','checklist').'"';
                        echo '<img src="'.$CFG->pixpath.'/t/delete.gif" alt='.$title.' title='.$title.' /></a>';
                    }

                    echo '&nbsp;&nbsp;&nbsp;<a href="'.$baseurl.'startadditem">';
                    $title = '"'.get_string('additemhere','checklist').'"';
                    echo '<img src="'.$CFG->wwwroot.'/mod/checklist/images/add.png" alt='.$title.' title='.$title.' /></a>';
                    if ($item->duetime) {
                        if ($item->duetime > time()) {
                            echo '<span class="itemdue"> '.userdate($item->duetime, get_string('strftimedate')).'</span>';
                        } else {
                            echo '<span class="itemoverdue"> '.userdate($item->duetime, get_string('strftimedate')).'</span>';
                        }
                    }

                    if ($this->additemafter == $item->id) {
                        $addatend = false;
                        echo '<li>';
                        echo '<form style="display:inline;" action="'.$CFG->wwwroot.'/mod/checklist/edit.php" method="post">';
                        echo '<input type="hidden" name="action" value="additem" />';
                        echo '<input type="hidden" name="id" value="'.$this->cm->id.'" />';
                        echo '<input type="hidden" name="position" value="'.($item->position+1).'" />';
                        echo '<input type="hidden" name="indent" value="'.$item->indent.'" />';
                        echo '<input type="hidden" name="sesskey" value="'.sesskey().'" />';
                        echo '<input type="checkbox" disabled="disabled" />';
                        echo '<input type="text" size="'.CHECKLIST_TEXT_INPUT_WIDTH.'" name="displaytext" value="" id="additembox" />';
                        if ($this->editdates) {
                            echo '<input type="hidden" name="editdates" value="on" />';
                            $this->print_edit_date();
                        }
                        echo '<input type="submit" name="additem" value="'.get_string('additem','checklist').'" />';
                        echo '</form>';
                        echo '<form style="display:inline" action="'.$CFG->wwwroot.'/mod/checklist/edit.php" method="get">';
                        echo '<input type="hidden" name="id" value="'.$this->cm->id.'" />';
                        if ($this->editdates) {
                            echo '<input type="hidden" name="editdates" value="on" />';
                        }
                        echo '<input type="submit" name="canceledititem" value="'.get_string('canceledititem','checklist').'" />';
                        echo '</form>';
                        echo '</li>';

                        if (!$focusitem) {
                            $focusitem = 'additembox';
                        }
                    }

                    $lastindent = $currindent;
                }
                
                echo '</li>';
            }
        }
        if ($this->checklist->autopopulate && $this->checklist->autoupdate) {
            echo '</form>';
        }

        if ($addatend) {
            echo '<li>';
            echo '<form action="'.$CFG->wwwroot.'/mod/checklist/edit.php" method="post">';
            echo '<input type="hidden" name="action" value="additem" />';
            echo '<input type="hidden" name="id" value="'.$this->cm->id.'" />';
            echo '<input type="hidden" name="indent" value="'.$currindent.'" />';
            echo '<input type="hidden" name="sesskey" value="'.sesskey().'" />';
            echo '<input type="text" size="'.CHECKLIST_TEXT_INPUT_WIDTH.'" name="displaytext" value="" id="additembox" />';
            if ($this->editdates) {
                echo '<input type="hidden" name="editdates" value="on" />';
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

        echo '<form action="'.$CFG->wwwroot.'/mod/checklist/edit.php" method="get">';
        echo '<input type="hidden" name="id" value="'.$this->cm->id.'" />';
        if ($this->additemafter) {
            echo '<input type="hidden" name="additemafter" value="'.$this->additemafter.'" />';
        }
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

        print_box_end();
    }

    function view_report() {
        global $CFG;

        $showbars = optional_param('showbars', false, PARAM_BOOL);

        $thisurl = $CFG->wwwroot.'/mod/checklist/report.php?id='.$this->cm->id;
        $thisurl .= $this->showoptional ? '' : '&amp;action=hideoptional';
        $thisurl .= $showbars ? '&amp;showbars=on' : '';
        $thisurl .= '&amp;sortby='.$this->sortby;

        groups_print_activity_menu($this->cm, $thisurl);
        $activegroup = groups_get_activity_group($this->cm, true);

        echo '&nbsp;&nbsp;<form style="display: inline;" action="'.$CFG->wwwroot.'/mod/checklist/report.php" method="get" />';
        echo '<input type="hidden" name="id" value="'.$this->cm->id.'" />';
        echo '<input type="hidden" name="sortby" value="'.$this->sortby.'" />';
        echo $showbars ? '<input type="hidden" name="showbars" value="on" />' : '';
        if ($this->showoptional) {
            echo '<input type="hidden" name="action" value="hideoptional" />';
            echo '<input type="submit" name="submit" value="'.get_string('optionalhide','checklist').'" />';
        } else {
            echo '<input type="hidden" name="action" value="showoptional" />';
            echo '<input type="submit" name="submit" value="'.get_string('optionalshow','checklist').'" />';
        }
        echo '</form>';
        

        echo '&nbsp;&nbsp;<form style="display: inline;" action="'.$CFG->wwwroot.'/mod/checklist/report.php" method="get" />';
        echo '<input type="hidden" name="id" value="'.$this->cm->id.'" />';
        echo '<input type="hidden" name="sortby" value="'.$this->sortby.'" />';
        echo $this->showoptional ? '' : '<input type="hidden" name="action" value="hideoptional" />';
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
            $users = array_keys($users);
            $ausers = get_records_sql('SELECT u.id, u.firstname, u.lastname FROM '.$CFG->prefix.'user u WHERE u.id IN ('.implode(',',$users).') '.$orderby);
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
                $itemstocount = implode(',',$itemstocount);

                if ($this->checklist->teacheredit == CHECKLIST_MARKING_STUDENT) {
                    $sql = 'usertimestamp > 0 AND item IN ('.$itemstocount.') AND userid = ';
                } else {
                    $sql = 'teachermark = '.CHECKLIST_TEACHERMARK_YES.' AND item IN ('.$itemstocount.') AND userid = ';
                }
                echo '<div>';
                foreach ($ausers as $auser) {
                    if ($totalitems) {
                        $tickeditems = count_records_select('checklist_check', $sql.$auser->id);
                        $percentcomplete = ($tickeditems * 100) / $totalitems;
                    } else {
                        $percentcomplete = 0;
                        $tickeditems = 0;
                    }

                    $vslink = ' <a href="'.$thisurl.'&amp;studentid='.$auser->id.'" ';
                    $vslink .= 'alt="'.get_string('viewsinglereport','checklist').'" title="'.get_string('viewsinglereport','checklist').'">';
                    $vslink .= '<img src="'.$CFG->pixpath.'/t/preview.gif" /></a>';
                    $userlink = '<a href="'.$CFG->wwwroot.'/user/view.php?id='.$auser->id.'&amp;course='.$this->course->id.'">'.fullname($auser).'</a>';
                    echo '<div style="float: left; width: 30%; text-align: right; margin-right: 8px; ">'.$userlink.$vslink.'</div>';
                    
                    echo '<div class="checklist_progress_outer">';
                    echo '<div class="checklist_progress_inner" style="width:'.$percentcomplete.'%; background-image: url('.$CFG->wwwroot.'/mod/checklist/images/progress.gif);" >&nbsp;</div>';
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
                $firstarrow = '<img src="'.$CFG->pixpath.'/t/down.gif" alt="'.get_string('asc').'" />';
            } elseif ($this->sortby == 'lastasc') {
                $lastlink = 'sortby=lastdesc';
                $lastarrow = '<img src="'.$CFG->pixpath.'/t/down.gif" alt="'.get_string('asc').'" />';
            } elseif ($this->sortby == 'firstdesc') {
                $firstarrow = '<img src="'.$CFG->pixpath.'/t/up.gif" alt="'.get_string('desc').'" />';
            } elseif ($this->sortby == 'lastdesc') {
                $lastarrow = '<img src="'.$CFG->pixpath.'/t/up.gif" alt="'.get_string('desc').'" />';
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

                    if ($this->caneditother()) {
                        $vslink = ' <a href="'.$thisurl.'&amp;studentid='.$auser->id.'" ';
                        $vslink .= 'alt="'.get_string('viewsinglereport','checklist').'" title="'.get_string('viewsinglereport','checklist').'" />';
                        $vslink .= '<img src="'.$CFG->pixpath.'/t/preview.gif" /></a>';
                    } else {
                        $vslink = '';
                    }
                    $userlink = '<a href="'.$CFG->wwwroot.'/user/view.php?id='.$auser->id.'&amp;course='.$this->course->id.'">'.fullname($auser).'</a>';

                    $row[] = $userlink.$vslink;

                    $sql = 'SELECT i.id, i.itemoptional, c.usertimestamp, c.teachermark FROM '.$CFG->prefix.'checklist_item i LEFT JOIN '.$CFG->prefix.'checklist_check c ';
                    $sql .= 'ON (i.id = c.item AND c.userid = '.$auser->id.') WHERE i.checklist = '.$this->checklist->id.' AND i.userid=0 ORDER BY i.position';
                    $checks = get_records_sql($sql);

                    foreach ($checks as $check) {
                        if (($check->itemoptional == CHECKLIST_OPTIONAL_DISABLED) || ($check->itemoptional == CHECKLIST_OPTIONAL_HEADING_DISABLED)) {
                            continue;
                        }
                
                        if ($check->itemoptional == CHECKLIST_OPTIONAL_HEADING) {
                            $row[] = array(false, false, true);
                        } else {
                            if ($check->usertimestamp > 0) {
                                $row[] = array($check->teachermark,true, false);
                            } else {
                                $row[] = array($check->teachermark,false, false);
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
        global $CFG;
        
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
        $tickimg = '<img src="'.$CFG->pixpath.'/i/tick_green_big.gif" alt="'.get_string('itemcomplete','checklist').'" />';
        $teacherimg = array(CHECKLIST_TEACHERMARK_UNDECIDED => '<img src="'.$CFG->wwwroot.'/mod/checklist/images/empty_box.gif" alt="'.get_string('teachermarkundecided','checklist').'" />', 
                            CHECKLIST_TEACHERMARK_YES => '<img src="'.$CFG->wwwroot.'/mod/checklist/images/tick_box.gif" alt="'.get_string('teachermarkyes','checklist').'" />', 
                            CHECKLIST_TEACHERMARK_NO => '<img src="'.$CFG->wwwroot.'/mod/checklist/images/cross_box.gif" alt="'.get_string('teachermarkno','checklist').'" />');
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
                    $cellclass = 'cell c'.$key.' level'.$table->level[$key];
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
        print_footer($this->course);
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
        $update_complete_scores = optional_param('update_complete_score', false, PARAM_TEXT);

        if ($removeauto) {
            // Remove any automatically generated items from the list
            // (if no longer using automatic items)
            if (!confirm_sesskey()) {
                error('Invalid sesskey');
            }
            $this->removeauto();
            return;
        }

        if ($update_complete_scores) {
            if (!confirm_sesskey()) {
                error('Invalid sesskey');
            }
            $this->update_complete_scores();
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
            if (($this->checklist->autopopulate) && ($this->items[$itemid]->moduleid)) {
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

    function additem($displaytext, $userid=0, $indent=0, $position=false, $duetime=false, $moduleid=0, $optional=0) {
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

        $item->id = insert_record('checklist_item', $item);
        $item->displaytext = stripslashes($displaytext);
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
            $event->description = get_string('calendardescription', 'checklist', addslashes($this->checklist->name));
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
            update_record('checklist_item', $upditem);
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
        $displaytext = trim($displaytext);
        if ($displaytext == '') {
            return;
        }

        if (isset($this->items[$itemid])) {
            if ($this->canedit()) {
                $this->items[$itemid]->displaytext = stripslashes($displaytext);
                $upditem = new stdClass;
                $upditem->id = $itemid;
                $upditem->displaytext = $displaytext;

                $upditem->duetime = 0;
                if ($duetime) {
                    $upditem->duetime = make_timestamp($duetime['year'], $duetime['month'], $duetime['day']);
                }
                $this->items[$itemid]->duetime = $upditem->duetime;

                update_record('checklist_item', $upditem);

                if ($this->checklist->duedatesoncalendar) {
                    $this->setevent($itemid, true);
                }
            }
        } elseif (isset($this->useritems[$itemid])) {
            if ($this->canaddown()) {
                $this->useritems[$itemid]->displaytext = stripslashes($displaytext);
                $upditem = new stdClass;
                $upditem->id = $itemid;
                $upditem->displaytext = $displaytext;
                update_record('checklist_item', $upditem);
            }
        }
    }

    function toggledisableitem($itemid) {
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
            update_record('checklist_item', $upditem);

            if ($item->itemoptional == CHECKLIST_OPTIONAL_HEADING) {
                foreach ($this->items as $key=>$it) {
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
                        $this->items[$key]->itemoptional = CHECKLIST_OPTIONAL_NO;
                        $upditem = new stdClass;
                        $upditem->id = $it->id;
                        $upditem->itemoptional = $it->itemoptional;
                        update_record('checklist_item', $upditem);
                    }
                }

            } elseif ($item->itemoptional == CHECKLIST_OPTIONAL_HEADING_DISABLED) {
                foreach ($this->items as $key=>$it) {
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
                        $this->items[$key]->itemoptional = CHECKLIST_OPTIONAL_DISABLED;
                        $upditem = new stdClass;
                        $upditem->id = $it->id;
                        $upditem->itemoptional = $it->itemoptional;
                        update_record('checklist_item', $upditem);
                    }
                }
            }
        }
    }

    function deleteitem($itemid) {
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

        delete_records('checklist_item', 'id', $itemid);
        delete_records('checklist_check', 'item', $itemid);

        $this->update_item_positions();
    }

    function moveitemto($itemid, $newposition) {
        if (!isset($this->items[$itemid])) {
            if (isset($this->useritems[$itemid])) {
                if ($this->canupdateown()) {
                    $this->useritems[$itemid]->position = $newposition;
                    $upditem = new stdClass;
                    $upditem->id = $itemid;
                    $upditem->position = $newposition;
                    update_record('checklist_item', $upditem);
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
        update_record('checklist_item', $upditem); // Update the database
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
        update_record('checklist_item', $upditem);

        // Update all 'children' of this item to new indent
        foreach ($this->items as $key=>$item) {
            if ($item->position > $position) {
                if ($item->indent > $oldindent) {
                    $this->items[$key]->indent += $adjust;
                    $upditem = new stdClass;
                    $upditem->id = $item->id;
                    $upditem->indent = $item->indent;
                    update_record('checklist_item', $upditem);
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
            if ($this->items[$itemid]->itemoptional == CHECKLIST_OPTIONAL_HEADING) {
                return; // Topic headings must stay as headings
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
        update_record('checklist_item', $upditem);
    }

    function nextcolour($itemid) {
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
        update_record('checklist_item', $upditem);
        $this->items[$itemid]->colour = $nextcolour;
    }

    function updatechecks() {
        $newchecks = optional_param('items', array(), PARAM_INT);

        if (!is_array($newchecks)) {
            // Something has gone wrong, so update nothing
            return;
        }

        add_to_log($this->course->id, 'checklist', 'update checks', "report.php?id={$this->cm->id}&studentid={$this->userid}", addslashes($this->checklist->name), $this->cm->id);
        
        $updategrades = false;
        if ($this->items) {
            foreach ($this->items as $key=>$item) {
                if (($this->checklist->autoupdate == CHECKLIST_AUTOUPDATE_YES) && ($item->moduleid)) {
                    continue; // Shouldn't get updated anyway, but just in case...
                }
                    
                $newval = in_array($item->id, $newchecks);

                if ($newval != $item->checked) {
                    $updategrades = true;
                    $this->items[$key]->checked = $newval;
                
                    $check = get_record_select('checklist_check', 'item = '.$item->id.' AND userid = '.$this->userid);
                    if ($check) {
                        if ($newval) {
                            $check->usertimestamp = time();
                        } else {
                            $check->usertimestamp = 0;
                        }
                        update_record('checklist_check', $check);

                    } else {
                    
                        $check = new stdClass;
                        $check->item = $item->id;
                        $check->userid = $this->userid;
                        $check->usertimestamp = time();
                        $check->teachertimestamp = 0;
                        $check->teachermark = CHECKLIST_TEACHERMARK_UNDECIDED;
                    
                        $check->id = insert_record('checklist_check', $check);
                    }
                }
            }
        }
        if ($updategrades) {
            checklist_update_grades($this->checklist, $this->userid);
        }
        
        if ($this->useritems) {
            foreach ($this->useritems as $key=>$item) {
                $newval = in_array($item->id, $newchecks);

                if ($newval != $item->checked) {
                    $this->useritems[$key]->checked = $newval;
                
                    $check = get_record_select('checklist_check', 'item = '.$item->id.' AND userid = '.$this->userid);
                    if ($check) {
                        if ($newval) {
                            $check->usertimestamp = time();
                        } else {
                            $check->usertimestamp = 0;
                        }
                        update_record('checklist_check', $check);

                    } else {
                    
                        $check = new stdClass;
                        $check->item = $item->id;
                        $check->userid = $this->userid;
                        $check->usertimestamp = time();
                        $check->teachertimestamp = 0;
                        $check->teachermark = CHECKLIST_TEACHERMARK_UNDECIDED;
                    
                        $check->id = insert_record('checklist_check', $check);
                    }
                }
            }
        }
    }

    function updateteachermarks() {
        global $USER;
        
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
            $info = addslashes($this->checklist->name).' ('.fullname($student, true).')';
            add_to_log($this->course->id, 'checklist', 'update checks', "report.php?id={$this->cm->id}&studentid={$this->userid}", $info, $this->cm->id);

            foreach ($newchecks as $newcheck) {
                list($itemid, $newval) = explode(':',$newcheck, 2);
            
                if (isset($this->items[$itemid])) {
                    $item =& $this->items[$itemid];
                    if ($newval != $item->teachermark) {
                        $updategrades = true;
                        $item->teachermark = $newval;
                    
                        $newcheck = new stdClass;
                        $newcheck->teachertimestamp = time();
                        $newcheck->teachermark = $newval;
                    
                        $oldcheck = get_record_select('checklist_check', 'item = '.$item->id.' AND userid = '.$this->userid);
                        if ($oldcheck) {
                            $newcheck->id = $oldcheck->id;
                            update_record('checklist_check', $newcheck);
                        } else {
                            $newcheck->item = $itemid;
                            $newcheck->userid = $this->userid;
                            $newcheck->id = insert_record('checklist_check', $newcheck);
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

        $itemids = implode(',',array_keys($this->items));
        $commentsunsorted = get_records_select('checklist_comment',"userid = {$this->userid} AND itemid IN ({$itemids})");
        $comments = array();
        if ($commentsunsorted) {
            foreach ($commentsunsorted as $comment) {
                $comments[$comment->itemid] = $comment;
            }
        }
        foreach ($newcomments as $itemid => $newcomment) {
            $newcomment = trim($newcomment);
            if ($newcomment == '') {
                if (array_key_exists($itemid, $comments)) {
                    delete_records('checklist_comment', 'id', $comments[$itemid]->id);
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

                        update_record('checklist_comment',$updatecomment);
                    }
                } else {
                    $addcomment = new stdClass;
                    $addcomment->itemid = $itemid;
                    $addcomment->userid = $this->userid;
                    $addcomment->commentby = $USER->id;
                    $addcomment->text = $newcomment;

                    insert_record('checklist_comment',$addcomment);
                }
            }
        }

    }

    function update_complete_scores() {
        if (!$this->checklist->autopopulate || !$this->checklist->autoupdate) {
            return;
        }

        $newscores = optional_param('complete_score', false, PARAM_INT);
        if (!$newscores || !is_array($newscores)) {
            return;
        }

        $changed = false;
        foreach ($newscores as $itemid=>$newscore) {
            if (!isset($this->items[$itemid])) {
                continue;
            }
            $item =& $this->items[$itemid];
            if (!$item->moduleid) {
                continue;
            }

            if ($item->complete_score != $newscore) {
                $item->complete_score = $newscore;
                $upditem = new stdClass;
                $upditem->id = $item->id;
                $upditem->complete_score = $newscore;
                update_record('checklist_item', $upditem);
                $changed = true;
            }
        }

        if ($changed) {
            $this->update_all_checks_from_completion_scores();
        }
    }

    function update_all_checks_from_completion_scores() {
        global $CFG;

        $users = get_users_by_capability($this->context, 'mod/checklist:updateown', 'u.id', '', '', '', '', '', false);
        if (!$users) {
            return;
        }
        $users = implode(',',array_keys($users));
        $updategrades = false;
        
        // Get a list of all the checklist items with a module linked to them (and no score needed to complete them)
        $sql = "SELECT cm.id AS cmid, m.name AS mod_name, i.id AS itemid
        FROM {$CFG->prefix}modules m, {$CFG->prefix}course_modules cm, {$CFG->prefix}checklist_item i
        WHERE m.id = cm.module AND cm.id = i.moduleid AND i.moduleid > 0 AND i.checklist = {$this->checklist->id} AND i.complete_score = 0";

        $items = get_records_sql($sql);
        if ($items) {
            foreach ($items as $item) {
                $logaction = '';
                $logaction2 = false;

                switch($item->mod_name) {
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
                $sql .= "FROM {$CFG->prefix}log ";
                $sql .= "WHERE cmid = {$item->cmid} AND (action = '{$logaction}'";
                if ($logaction2) {
                    $sql .= " OR action = '{$logaction2}'";
                }
                $sql .= ") AND userid IN ($users)";
                $log_entries = get_records_sql($sql);

                if (!$log_entries) {
                    continue;
                }

                foreach ($log_entries as $entry) {
                    //echo "User: {$entry->userid} has completed '{$item->mod_name}' with cmid {$item->cmid}, so updating checklist item {$item->itemid}<br />\n";

                    $check = get_record('checklist_check', 'item', $item->itemid, 'userid', $entry->userid);
                    if ($check) {
                        if ($check->usertimestamp) {
                            continue;
                        }
                        $check->usertimestamp = time();
                        update_record('checklist_check', $check);
                        $updategrades = true;
                    } else {
                        $check = new stdClass;
                        $check->item = $item->itemid;
                        $check->userid = $entry->userid;
                        $check->usertimestamp = time();
                        $check->teachertimestamp = 0;
                        $check->teachermark = CHECKLIST_TEACHERMARK_UNDECIDED;
                    
                        $check->id = insert_record('checklist_check', $check);
                        $updategrades = true;
                    }
                }
            }
        }

        // Get a list of all the items which care about the score
        $sql = "SELECT cm.id AS cmid, cm.instance AS instanceid, m.name AS mod_name, i.id AS itemid, i.complete_score AS complete_score
        FROM {$CFG->prefix}modules m, {$CFG->prefix}course_modules cm, {$CFG->prefix}checklist_item i
        WHERE m.id = cm.module AND cm.id = i.moduleid AND i.moduleid > 0 AND i.checklist = {$this->checklist->id} AND i.complete_score > 0";

        $items = get_records_sql($sql);
        if ($items) {
            // For each item, get a list of users with their grades
            foreach ($items as $item) {
                $sql = 'SELECT gg.userid AS userid, gg.rawgrade';
                $sql .= " FROM {$CFG->prefix}grade_grades gg, {$CFG->prefix}grade_items gi";
                $sql .= " WHERE gg.itemid = gi.id AND gi.itemmodule = '{$item->mod_name}' AND gi.iteminstance = {$item->instanceid}";
                $sql .= " AND gg.userid IN ($users)";
                $grades = get_records_sql($sql);

                if ($grades) {
                    foreach ($grades as $grade) {
                        $complete = $grade->rawgrade >= $item->complete_score;
                        $check = get_record('checklist_check', 'item', $item->itemid, 'userid', $grade->userid);
                        if ($check) {
                            if ($complete) {
                                if ($check->usertimestamp) {
                                    continue;
                                }
                                $check->usertimestamp = time();
                                update_record('checklist_check', $check);
                                $updategrades = true;
                            } else {
                                if ($check->usertimestamp == 0) {
                                    continue;
                                }
                                $check->usertimestamp = 0;
                                update_record('checklist_check', $check);
                                $updategrades = true;
                            }
                        } else {
                            if (!$complete) {
                                continue;
                            }
                            $check = new stdClass;
                            $check->item = $item->itemid;
                            $check->userid = $grade->userid;
                            $check->usertimestamp = time();
                            $check->teachertimestamp = 0;
                            $check->teachermark = CHECKLIST_TEACHERMARK_UNDECIDED;
                    
                            $check->id = insert_record('checklist_check', $check);
                            $updategrades = true;
                        }
                    }
                }
            }
        }

        if ($updategrades) {
            checklist_update_grades($this->checklist);
        }
    }

    // Update the userid to point to the next user to view
    function getnextuserid() {
        global $CFG;

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
            $users = array_keys($users);
            $ausers = get_records_sql('SELECT u.id FROM '.$CFG->prefix.'user u WHERE u.id IN ('.implode(',',$users).') '.$orderby);
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

    /* static function - avoiding 'static' keyword for PHP 4 compatibility */
    function print_user_progressbar($checklistid, $userid, $width='300px', $showpercent=true, $return=false) {
        global $CFG;

        list($ticked, $total) = checklist_class::get_user_progress($checklistid, $userid);
        if (!$total) {
            return;
        }
        $percent = $ticked * 100 / $total;

        // Sadly 'styles.php' will not be included from outside the module, so I have to hard-code the styles here
        $output = '<div class="checklist_progress_outer" style="border-width: 1px; border-style: solid; border-color: black; width: '.$width.'; background-colour: transparent; height: 15px; float: left;" >';
        $output .= '<div class="checklist_progress_inner" style="width:'.$percent.'%; background-image: url('.$CFG->wwwroot.'/mod/checklist/images/progress.gif); background-color: #229b15; height: 100%; background-repeat: repeat-x; background-position: top;" >&nbsp;</div>';
        $output .= '</div>';
        if ($showpercent) {
            $output .= '<div style="float:left; width: 3em;">&nbsp;'.sprintf('%0d%%', $percent).'</div>';
        }
        //        echo '<div style="float:left;">&nbsp;('.$ticked.'/'.$total.')</div>';
        $output .= '<br style="clear:both;" />';
        if ($return) {
            return $output;
        } else {
            echo $output;
        }
    }

    function get_user_progress($checklistid, $userid) {
        $userid = intval($userid); // Just to be on the safe side...

        $checklist = get_record('checklist', 'id', $checklistid);
        if (!$checklist) {
            return array(false, false);
        }
        if (!$items = get_records_select('checklist_item',"checklist = $checklist->id AND userid = 0 AND itemoptional = ".CHECKLIST_OPTIONAL_NO, '', 'id')) {
            return array(false, false);
        }
        $total = count($items);
        $itemlist = implode(',',array_keys($items));

        $sql = "userid = $userid AND item IN ($itemlist) AND ";
        if ($checklist->teacheredit == CHECKLIST_MARKING_STUDENT) {
            $sql .= 'usertimestamp > 0';
        } else {
            $sql .= 'teachermark = '.CHECKLIST_TEACHERMARK_YES;
        }
        $ticked = count_records_select('checklist_check', $sql);

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
