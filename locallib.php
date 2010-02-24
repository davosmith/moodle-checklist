<?php

/** 
 * Stores all the functions for manipulating a checklist
 *
 * @author   David Smith <moodle@davosmith.co.uk>
 * @package  mod/checklist
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');

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
            $this->useritems = get_records_select('checklist_item', $sql, 'position');
        } else {
            $this->useritems = false;
        }

        // Load the currently checked-off items
        if ($this->userid && $this->canupdateown()) {

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
        return has_capability('mod/checklist:updateown', $this->context);
    }

    function canaddown() {
        return $this->checklist->useritemsallowed && has_capability('mod/checklist:updateown', $this->context);
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
        
    function view() {
        global $CFG;
        
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
            print_footer($course);
            die;
        }

        $this->view_header();

        print_heading(format_string($this->checklist->name));

        $this->view_tabs($currenttab);

        if ((!$this->items) && $this->canedit()) {
            redirect($CFG->wwwroot.'/mod/checklist/edit.php?checklist='.$this->checklist->id, get_string('noitems','checklist'));
        }

        add_to_log($this->course->id, 'checklist', 'view', "view.php?id={$this->cm->id}", $this->checklist->id, $this->cm->id);        

        if ($this->canupdateown()) {
            $this->process_view_actions();
        }

        $this->view_items();

        $this->view_footer();
    }


    function edit() {
        global $CFG;
        
        if (!$this->canedit()) {
            redirect($CFG->wwwroot.'/mod/checklist/view.php?checklist='.$this->context->id);
        }

        add_to_log($this->course->id, "checklist", "edit", "edit.php?id={$this->cm->id}", $this->checklist->id, $this->cm->id);

        $this->view_header();

        print_heading(format_string($this->checklist->name));

        $this->view_tabs('edit');

        $this->process_edit_actions();

        $this->view_edit_items();

        $this->view_footer();
    }

    function report() {
        global $CFG;

        if (!$this->canviewreports()) {
            redirect($CFG->wwwroot.'/mod/checklist/view.php?checklist='.$this->context->id);
        }

        add_to_log($this->course->id, "checklist", "report", "report.php?id={$this->cm->id}", $this->checklist->id, $this->cm->id);

        $this->view_header();

        print_heading(format_string($this->checklist->name));

        $this->view_tabs('report');

        $this->process_report_actions();

        $this->view_report();
        
        $this->view_footer();
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
            $row[] = new tabobject('view', "$CFG->wwwroot/mod/checklist/view.php?checklist={$this->checklist->id}", get_string('view', 'checklist'));
        } elseif ($this->canpreview()) {
            $row[] = new tabobject('preview', "$CFG->wwwroot/mod/checklist/view.php?checklist={$this->checklist->id}", get_string('preview', 'checklist'));
        }
        if ($this->canviewreports()) {
            $row[] = new tabobject('report', "$CFG->wwwroot/mod/checklist/report.php?checklist={$this->checklist->id}", get_string('report', 'checklist'));
        }
        if ($this->canedit()) {
            $row[] = new tabobject('edit', "$CFG->wwwroot/mod/checklist/edit.php?checklist={$this->checklist->id}", get_string('edit', 'checklist'));
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
        
        $totalitems = 0;
        $requireditems = 0;
        $completeitems = 0;
        $allcompleteitems = 0;
        foreach ($this->items as $item) {
            if (!$item->itemoptional) {
                $requireditems++;
                if ($item->checked) {
                    $completeitems++;
                    $allcompleteitems++;
                }
            } elseif ($item->checked) {
                $allcompleteitems++;
            }
            $totalitems++;
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

    function view_items() {
        global $CFG;
        
        print_box_start('generalbox boxwidthwide boxaligncenter');

        echo '<p>'.format_string($this->checklist->intro, $this->checklist->introformat).'</p>';

        if ($this->canupdateown()) {
            $this->view_progressbar();
        }
        
        if (!$this->items) {
            print_string('noitems','checklist');
        } else {
            $updateform = $this->canupdateown();
            $addown = $this->canaddown() && $this->useredit;
            if ($updateform) {
                if ($this->canaddown()) {
                    echo '<form style="display:inline;" action="'.$CFG->wwwroot.'/mod/checklist/view.php" method="get">';
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
                echo '<form action="'.$CFG->wwwroot.'/mod/checklist/view.php" method="post">';
                echo '<input type="hidden" name="checklist" value="'.$this->checklist->id.'" />';
                echo '<input type="hidden" name="action" value="updatechecks" />';
                echo '<input type="hidden" name="sesskey" value="'.sesskey().'" />';
                if ($addown) {
                    echo '<input type="hidden" name="useredit" value="on" />';
                }
            }

            if ($this->useritems) {
                reset($this->useritems);
            }

            echo '<ol class="checklist">';
            $currindent = 0;
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
                $checked = ($updateform && $item->checked) ? ' checked="checked" ' : '';
                $optional = $item->itemoptional ? ' class="itemoptional" ' : '';
                echo '<li><input type="checkbox" name='.$itemname.' id='.$itemname.$checked.' />';
                echo '<label for='.$itemname.$optional.'>'.s($item->displaytext).'</label>';

                if ($addown) {
                    $baseurl = $CFG->wwwroot.'/mod/checklist/view.php?checklist='.$this->checklist->id.'&amp;itemid='.$item->id.'&amp;sesskey='.sesskey().'&amp;action=';
                    echo '&nbsp;<a href="'.$baseurl.'startadditem" />';
                    $title = '"'.get_string('additemalt','checklist').'"';
                    echo '<img src="'.$CFG->wwwroot.'/mod/checklist/images/add.png" alt='.$title.' title='.$title.' /></a>';
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
                                echo '<li><input type="checkbox" name='.$itemname.' id='.$itemname.$checked.' disabled="disabled" />';
                                echo '<form style="display:inline" action="'.$CFG->wwwroot.'/mod/checklist/view.php" method="post">';
                                echo '<input type="hidden" name="action" value="updateitem" />';
                                echo '<input type="hidden" name="id" value="'.$this->cm->id.'" />';
                                echo '<input type="hidden" name="itemid" value="'.$useritem->id.'" />';
                                echo '<input type="hidden" name="sesskey" value="'.sesskey().'" />';
                                echo '<input type="text" name="displaytext" value="'.s($useritem->displaytext).'" />';
                                echo '<input type="submit" name="updateitem" value="'.get_string('updateitem','checklist').'" />';
                                echo '</form>';
                                echo '<form style="display:inline" action="'.$CFG->wwwroot.'/mod/checklist/view.php" method="get">';
                                echo '<input type="hidden" name="id" value="'.$this->cm->id.'" />';
                                echo '<input type="hidden" name="useredit" value="on" />';
                                echo '<input type="submit" name="canceledititem" value="'.get_string('canceledititem','checklist').'" />';
                                echo '</form>';
                                echo '</li>';
                            } else {
                                echo '<li><input type="checkbox" name='.$itemname.' id='.$itemname.$checked.' />';
                                echo '<label class="useritem" for='.$itemname.'>'.s($useritem->displaytext).'</label>';

                                if ($addown) {
                                    $baseurl = $CFG->wwwroot.'/mod/checklist/view.php?checklist='.$this->checklist->id.'&amp;itemid='.$useritem->id.'&amp;sesskey='.sesskey().'&amp;action=';
                                    echo '&nbsp;<a href="'.$baseurl.'edititem" />';
                                    $title = '"'.get_string('edititem','checklist').'"';
                                    echo '<img src="'.$CFG->pixpath.'/t/edit.gif" alt='.$title.' title='.$title.' /></a>';

                                    echo '&nbsp;<a href="'.$baseurl.'deleteitem" />';
                                    $title = '"'.get_string('deleteitem','checklist').'"';
                                    echo '<img src="'.$CFG->wwwroot.'/mod/checklist/images/remove.png" alt='.$title.' title='.$title.' /></a>';
                                }

                                echo '</li>';
                            }
                            $useritem = next($this->useritems);
                        }
                        echo '</ol>';
                    }
                }

                if ($addown && isset($item->addafter)) {
                    echo '<ol class="checklist"><li>';
                    echo '<form action="'.$CFG->wwwroot.'/mod/checklist/view.php" method="post">';
                    echo '<input type="hidden" name="action" value="additem" />';
                    echo '<input type="hidden" name="id" value="'.$this->cm->id.'" />';
                    echo '<input type="hidden" name="position" value="'.$item->position.'" />';
                    echo '<input type="hidden" name="sesskey" value="'.sesskey().'" />';
                    echo '<input type="checkbox" disabled="disabled" />';
                    echo '<input type="text" name="displaytext" value="" />';
                    echo '<input type="submit" name="additem" value="'.get_string('additem','checklist').'" />';
                    echo '</form>';
                    echo '<form style="display:inline" action="'.$CFG->wwwroot.'/mod/checklist/view.php" method="get">';
                    echo '<input type="hidden" name="id" value="'.$this->cm->id.'" />';
                    echo '<input type="hidden" name="useredit" value="on" />';
                    echo '<input type="submit" name="canceledititem" value="'.get_string('canceledititem','checklist').'" />';
                    echo '</form>';
                    echo '</li></ol>';
                }
            }
            echo '</ol>';

            if ($updateform) {
                echo '<input type="submit" name="submit" value="'.get_string('savechecks','checklist').'" />';
                echo '</form>';
            }
        }

        print_box_end();
    }

    function view_edit_items() {
        global $CFG;
        
        print_box_start('generalbox boxwidthwide boxaligncenter');
        
        $currindent = 0;
        $addatend = true;
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
                $baseurl = $CFG->wwwroot.'/mod/checklist/edit.php?checklist='.$this->checklist->id.'&amp;itemid='.$item->id.'&amp;sesskey='.sesskey().'&amp;action=';

                echo '<li>';
                if ($item->itemoptional) {
                    $title = '"'.get_string('optionalitem','checklist').'"';
                    echo '<a href="'.$baseurl.'makerequired" />';
                    echo '<img src="'.$CFG->wwwroot.'/mod/checklist/images/optional.png" alt='.$title.' title='.$title.' /></a>&nbsp;';
                    $optional = ' class="itemoptional" ';
                } else {
                    $title = '"'.get_string('requireditem','checklist').'"';
                    echo '<a href="'.$baseurl.'makeoptional" />';
                    echo '<img src="'.$CFG->wwwroot.'/mod/checklist/images/required.png" alt='.$title.' title='.$title.' /></a>&nbsp;';
                    $optional = '';
                }

                if (isset($item->editme)) {
                    echo '<form style="display:inline" action="'.$CFG->wwwroot.'/mod/checklist/edit.php" method="post">';
                    echo '<input type="hidden" name="action" value="updateitem" />';
                    echo '<input type="hidden" name="id" value="'.$this->cm->id.'" />';
                    echo '<input type="hidden" name="itemid" value="'.$item->id.'" />';
                    echo '<input type="hidden" name="sesskey" value="'.sesskey().'" />';
                    echo '<input type="text" name="displaytext" value="'.s($item->displaytext).'" />';
                    echo '<input type="submit" name="updateitem" value="'.get_string('updateitem','checklist').'" />';
                    echo '</form>';
                    echo '<form style="display:inline" action="'.$CFG->wwwroot.'/mod/checklist/edit.php" method="get">';
                    echo '<input type="hidden" name="id" value="'.$this->cm->id.'" />';
                    echo '<input type="submit" name="canceledititem" value="'.get_string('canceledititem','checklist').'" />';
                    echo '</form>';
                } else {
                    echo '<label for='.$itemname.$optional.'>'.s($item->displaytext).'</label>&nbsp;';

                    echo '<a href="'.$baseurl.'edititem" />';
                    $title = '"'.get_string('edititem','checklist').'"';
                    echo '<img src="'.$CFG->pixpath.'/t/edit.gif"  alt='.$title.' title='.$title.' /></a>&nbsp;';

                    if ($item->indent > 0) {
                        echo '<a href="'.$baseurl.'unindentitem" />';
                        $title = '"'.get_string('unindentitem','checklist').'"';
                        echo '<img src="'.$CFG->pixpath.'/t/left.gif" alt='.$title.' title='.$title.'  /></a>';
                    }

                    if (($item->indent < CHECKLIST_MAX_INDENT) && (($lastindent+1) > $currindent)) {
                        echo '<a href="'.$baseurl.'indentitem" />';
                        $title = '"'.get_string('indentitem','checklist').'"';
                        echo '<img src="'.$CFG->pixpath.'/t/right.gif" alt='.$title.' title='.$title.' /></a>';
                    }

                    echo '&nbsp;';
                    
                    // TODO more complex checks to take into account indentation
                    if ($item->position > 1) {
                        echo '<a href="'.$baseurl.'moveitemup" />';
                    $title = '"'.get_string('moveitemup','checklist').'"';
                    echo '<img src="'.$CFG->pixpath.'/t/up.gif" alt='.$title.' title='.$title.' /></a>';
                    }

                    if ($item->position < $lastitem) {
                        echo '<a href="'.$baseurl.'moveitemdown" />';
                    $title = '"'.get_string('moveitemdown','checklist').'"';
                    echo '<img src="'.$CFG->pixpath.'/t/down.gif" alt='.$title.' title='.$title.' /></a>';
                    }

                    echo '&nbsp;<a href="'.$baseurl.'deleteitem" />';
                    $title = '"'.get_string('deleteitem','checklist').'"';
                    echo '<img src="'.$CFG->pixpath.'/t/delete.gif" alt='.$title.' title='.$title.' /></a>';
                    
                    echo '&nbsp;&nbsp;&nbsp;<a href="'.$baseurl.'startadditem" />';
                    $title = '"'.get_string('additemhere','checklist').'"';
                    echo '<img src="'.$CFG->wwwroot.'/mod/checklist/images/add.png" alt='.$title.' title='.$title.' /></a>';

                    if (isset($item->addafter)) {
                        $addatend = false;
                        echo '<li>';
                        echo '<form style="display:inline;" action="'.$CFG->wwwroot.'/mod/checklist/edit.php" method="post">';
                        echo '<input type="hidden" name="action" value="additem" />';
                        echo '<input type="hidden" name="id" value="'.$this->cm->id.'" />';
                        echo '<input type="hidden" name="position" value="'.($item->position+1).'" />';
                        echo '<input type="hidden" name="indent" value="'.$item->indent.'" />';
                        echo '<input type="hidden" name="sesskey" value="'.sesskey().'" />';
                        echo '<input type="checkbox" disabled="disabled" />';
                        echo '<input type="text" name="displaytext" value="" />';
                        echo '<input type="submit" name="additem" value="'.get_string('additem','checklist').'" />';
                        echo '</form>';
                        echo '<form style="display:inline" action="'.$CFG->wwwroot.'/mod/checklist/edit.php" method="get">';
                        echo '<input type="hidden" name="id" value="'.$this->cm->id.'" />';
                        echo '<input type="submit" name="canceledititem" value="'.get_string('canceledititem','checklist').'" />';
                        echo '</form>';
                        echo '</li>';
                    }

                    $lastindent = $currindent;
                }
                
                echo '</li>';
            }
        }
        if ($addatend) {
            echo '<li>';
            echo '<form action="'.$CFG->wwwroot.'/mod/checklist/edit.php" method="post">';
            echo '<input type="hidden" name="action" value="additem" />';
            echo '<input type="hidden" name="id" value="'.$this->cm->id.'" />';
            echo '<input type="hidden" name="indent" value="'.$currindent.'" />';
            echo '<input type="hidden" name="sesskey" value="'.sesskey().'" />';
            echo '<input type="text" name="displaytext" value="" />';
            echo '<input type="submit" name="additem" value="'.get_string('additem','checklist').'" />';
            echo '</form>';
            echo '</li>';
        }
        echo '</ol>';
        while ($currindent) {
            $currindent--;
            echo '</ol>';
        }

        print_box_end();
    }

    function view_report() {
        global $CFG;

        $thisurl = $CFG->wwwroot.'/mod/checklist/report.php?checklist='.$this->checklist->id;
        groups_print_activity_menu($this->cm, $thisurl);
        $activegroup = groups_get_activity_group($this->cm, true);

        echo '&nbsp;&nbsp;<form style="display: inline;" action="'.$CFG->wwwroot.'/mod/checklist/report.php" method="get" />';
        echo '<input type="hidden" name="checklist" value="'.$this->checklist->id.'" />';
        if ($this->showoptional) {
            echo '<input type="hidden" name="action" value="hideoptional" />';
            echo '<input type="submit" name="submit" value="'.get_string('optionalhide','checklist').'" />';
        } else {
            echo '<input type="hidden" name="action" value="showoptional" />';
            echo '<input type="submit" name="submit" value="'.get_string('optionalshow','checklist').'" />';
        }
        echo '</form>';
        
        echo '<br style="clear:both"/>';

        $table = new stdClass;
        $table->head = array(get_string('fullname'));
        $table->level = array(-1);
        $table->size = array('100px');
        $table->skip = array(false);
        foreach ($this->items as $item) {
            $table->head[] = s($item->displaytext);
            $table->level[] = ($item->indent < 3) ? $item->indent : 2;
            $table->size[] = '80px';
            $table->skip[] = (!$this->showoptional) && $item->itemoptional;
        }

        $ausers = false;
        if ($users = get_users_by_capability($this->context, 'mod/checklist:updateown', 'u.id', '', '', '', $activegroup, '', false)) {
            $users = array_keys($users);
            $ausers = get_records_sql('SELECT u.id, u.firstname, u.lastname FROM '.$CFG->prefix.'user u WHERE u.id IN ('.implode(',',$users).') ');
        }

        $table->data = array();
        if ($ausers) {
            foreach ($ausers as $auser) {
                $row = array();
                $row[] = fullname($auser);

                $sql = 'SELECT i.id, c.usertimestamp FROM '.$CFG->prefix.'checklist_item i LEFT JOIN '.$CFG->prefix.'checklist_check c ';
                $sql .= 'ON (i.id = c.item AND c.userid = '.$auser->id.') WHERE i.checklist = '.$this->checklist->id.' AND i.userid=0 ORDER BY i.position';
                $checks = get_records_sql($sql);

                foreach ($checks as $check) {
                    if ($check->usertimestamp > 0) {
                        $row[] = true;
                    } else {
                        $row[] = false;
                    }
                }

                $table->data[] = $row;
            }
        }
        
        $this->print_report_table($table);
    }

    function print_report_table($table) {
        global $CFG;
        
        $output = '';

        $output .= '<table summary="'.get_string('reporttablesummary','checklist').'"';
        $output .= ' cellpadding="5" cellspacing="1" class="generaltable boxaligncenter checklistreport">';

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
                    if ($item) {
                        $cellclass .= '-checked';
                        $img = $tickimg;
                    }
                    if ($key == $lastkey) {
                        $cellclass .= ' lastcol';
                    }
                
                    $output .= '<td style=" text-align: center; width: '.$size.';" class="'.$cellclass.'">'.$img.'</td>';
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
            if (isset($this->items[$itemid])) {
                $this->items[$itemid]->addafter = true;
            }
            break;
            
        case 'edititem':
            if ($this->useritems && isset($this->useritems[$itemid])) {
                $this->useritems[$itemid]->editme = true;
            }
            break;

        case 'additem':
            $displaytext = optional_param('displaytext', '', PARAM_TEXT);
            $position = optional_param('position', false, PARAM_INT);
            $this->additem($displaytext, $this->userid, 0, $position);
            $item = $this->get_item_at_position($position);
            if ($item) {
                $item->addafter = true;
            }
            break;

        case 'deleteitem':
            $this->deleteitem($itemid);
            break;

        case 'updateitem':
            $displaytext = optional_param('displaytext', '', PARAM_TEXT);
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
        $action = optional_param('action', false, PARAM_TEXT);
        if (!$action) {
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
            $this->additem($displaytext, 0, $indent, $position);
            break;
        case 'startadditem':
            if (isset($this->items[$itemid])) {
                $this->items[$itemid]->addafter = true;
            }
            break;
        case 'edititem':
            if (isset($this->items[$itemid])) {
                $this->items[$itemid]->editme = true;
            }
            break;
        case 'updateitem':
            $displaytext = optional_param('displaytext', '', PARAM_TEXT);
            $this->updateitemtext($itemid, $displaytext);
            break;
        case 'deleteitem':
            $this->deleteitem($itemid);
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
            
        default:
            error('Invalid action - "'.s($action).'"');
        }
    }

    function process_report_actions() {
        $this->showoptional = true;
        
        $action = optional_param('action', false, PARAM_TEXT);
        if (!$action) {
            return;
        }

        if ($action == 'hideoptional') {
            $this->showoptional = false;
        }
    }

    function additem($displaytext, $userid=0, $indent=0, $position=false) {
        $displaytext = trim($displaytext);
        if ($displaytext == '') {
            return;
        }

        if ($userid) {
            if (!$this->canaddown()) {
                return;
            }
        } else {
            if (!$this->canedit()) {
                return;
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
        $item->itemoptional = false;

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
                    $item->addafter = true;
                    $this->update_item_positions(1, $position);
                }
                $this->items[$item->id] = $item;
                uasort($this->items, 'checklist_itemcompare');
            }
        }
    }

    function updateitemtext($itemid, $displaytext) {
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
                update_record('checklist_item', $upditem);
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

    function deleteitem($itemid) {
        if (isset($this->items[$itemid])) {
            if (!$this->canedit()) {
                return;
            }
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
        foreach ($this->items as $item) {
            if ($item->position > $position) {
                if ($item->indent > $oldindent) {
                    $item->indent += $adjust;
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

    function makeoptional($itemid, $optional) {
        if (!isset($this->items[$itemid])) {
            return;
        }

        $this->items[$itemid]->itemoptional = $optional;
        $upditem = new stdClass;
        $upditem->id = $itemid;
        $upditem->optional = $optional;
        update_record('checklist_item', $upditem);
    }

    function updatechecks() {
        $newchecks = array();
        
        foreach ($_REQUEST as $param => $val) {
            if (substr($param, 0, 4) == 'item') {
                $id = intval(substr($param, 4));
                $newval = clean_param($param, PARAM_BOOL);

                $newchecks[$id] = $newval;
                
            }
        }

        if ($this->items) {
            foreach ($this->items as $item) {
                $newval = isset($newchecks[$item->id]) && $newchecks[$item->id];

                if ($newval != $item->checked) {
                    $item->checked = $newval;
                
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
        if ($this->useritems) {
            foreach ($this->useritems as $item) {
                $newval = isset($newchecks[$item->id]) && $newchecks[$item->id];

                if ($newval != $item->checked) {
                    $item->checked = $newval;
                
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