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
        
        $sql = 'checklist = '.$this->checklist->id;
        $sql .= ' AND userid = 0';
        $this->items = get_records_select('checklist_item', $sql, 'position');

        if ($this->userid) {
            $sql = 'checklist = '.$this->checklist->id;
            $sql .= ' AND userid = '.$this->userid;
            $this->useritems = get_records_select('checklist_item', $sql, 'position');
        } else {
            $this->useritems = false;
        }

        // Makes sure all items are numbered sequentially, starting at 1
        $this->update_item_positions();

        if ($this->userid) {

            $sql = 'SELECT i.id, c.usertimestamp FROM '.$CFG->prefix.'checklist_item i LEFT JOIN '.$CFG->prefix.'checklist_check c ';
            $sql .= 'ON (i.id = c.item AND c.userid = '.$this->userid.') WHERE i.checklist = '.$this->checklist->id;

            // TODO - display the teacher's mark

            $checks = get_records_sql($sql);

            foreach ($checks as $check) {
                $id = $check->id;
                
                if (isset($this->items[$id])) {
                    $this->items[$id]->checked = $check->usertimestamp > 0;
                } elseif (isset($this->useritems[$id])) {
                    $this->useritems[$id] = $check->usertimestamp > 0;
                } else {
                    error('Non-existant item has been checked');
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

        foreach($this->items as $item) {
            if ($pos == $start) {
                $pos += $move;
                $start = -1;
            }
            if ($item->position != $pos) {
                $oldpos = $item->position;
                $item->position = $pos;
                update_record('checklist_item', $item);
                if ($oldpos == $end) {
                    break;
                }
            }
            $pos++;
        }
    }

    function canupdateown() {
        return has_capability('mod/checklist:updateown', $this->context);
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
        // TODO - check sesskey()
        
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
        // TODO - check sesskey()
        
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

        //$this->process_edit_actions();

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

    function view_items() {
        // TODO tick & disable parent item if all children ticked (+rename, so does not change mark)
        // TODO tick & disable children items, if parent ticked
        
        global $CFG;
        
        print_box_start('generalbox boxwidthnormal boxaligncenter');

        echo '<p>'.format_string($this->checklist->intro, $this->checklist->introformat).'</p>';
        
        if (!$this->items) {
            print_string('noitems','checklist');
        } else {
            $updateform = $this->canupdateown();
            if ($updateform) {
                echo '<form action="'.$CFG->wwwroot.'/mod/checklist/view.php" method="post">';
                echo '<input type="hidden" name="checklist" value="'.$this->checklist->id.'" />';
                echo '<input type="hidden" name="action" value="updatechecks" />';
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
                echo '<li><input type="checkbox" name='.$itemname.' id='.$itemname.$checked.' />';
                echo '<label for='.$itemname.'>'.s($item->displaytext).'</label>';
                echo '</li>';
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
        
        print_box_start('generalbox boxwidthnormal boxaligncenter');
        
        echo '<ol class="checklist">';
        if ($this->items) {
            $lastitem = count($this->items);
            $currindent = 0;
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
                echo '<li><input type="checkbox" name='.$itemname.' id='.$itemname.' disabled="disabled" />';

                if (isset($item->editme)) {
                    echo '<form style="display:inline" action="'.$CFG->wwwroot.'/mod/checklist/edit.php" method="post">';
                    echo '<input type="hidden" name="action" value="updateitem" />';
                    echo '<input type="hidden" name="id" value="'.$this->cm->id.'" />';
                    echo '<input type="hidden" name="itemid" value="'.$item->id.'" />';
                    echo '<input type="text" name="displaytext" value="'.$item->displaytext.'" />';
                    echo '<input type="submit" name="updateitem" value="'.get_string('updateitem','checklist').'" />';
                    echo '</form>';
                    echo '<form style="display:inline" action="'.$CFG->wwwroot.'/mod/checklist/edit.php" method="get">';
                    echo '<input type="hidden" name="id" value="'.$this->cm->id.'" />';
                    echo '<input type="submit" name="canceledititem" value="'.get_string('canceledititem','checklist').'" />';
                    echo '</form>';
                } else {
                    echo '<label for='.$itemname.'>'.s($item->displaytext).'</label>&nbsp;';

                    $baseurl = $CFG->wwwroot.'/mod/checklist/edit.php?checklist='.$this->checklist->id.'&amp;itemid='.$item->id.'&amp;action=';

                    echo '<a href="'.$baseurl.'edititem" />';
                    echo '<img src="'.$CFG->pixpath.'/t/edit.gif" alt="'.get_string('edititem','checklist').'" /></a>&nbsp;';

                    if ($item->indent > 0) {
                        echo '<a href="'.$baseurl.'unindentitem" />';
                        echo '<img src="'.$CFG->pixpath.'/t/left.gif" alt="'.get_string('unindentitem','checklist').'" /></a>';
                    }

                    if (($item->indent < CHECKLIST_MAX_INDENT) && (($lastindent+1) > $currindent)) {
                        echo '<a href="'.$baseurl.'indentitem" />';
                        echo '<img src="'.$CFG->pixpath.'/t/right.gif" alt="'.get_string('indentitem','checklist').'" /></a>';
                    }

                    echo '&nbsp;';
                    
                    // TODO more complex checks once there is indentation to worry about as well
                    if ($item->position > 1) {
                        echo '<a href="'.$baseurl.'moveitemup" />';
                        echo '<img src="'.$CFG->pixpath.'/t/up.gif" alt="'.get_string('moveitemup','checklist').'" /></a>';
                    }

                    if ($item->position < $lastitem) {
                        echo '<a href="'.$baseurl.'moveitemdown" />';
                        echo '<img src="'.$CFG->pixpath.'/t/down.gif" alt="'.get_string('moveitemdown','checklist').'" /></a>';
                    }

                    echo '&nbsp;<a href="'.$baseurl.'deleteitem" />';
                    echo '<img src="'.$CFG->pixpath.'/t/delete.gif" alt="'.get_string('moveitemdown','checklist').'" /></a>';
                    
                    $lastindent = $currindent;
                }
                
                echo '</li>';
            }
        }
        echo '<li>';
        echo '<form action="'.$CFG->wwwroot.'/mod/checklist/edit.php" method="post">';
        echo '<input type="hidden" name="action" value="additem" />';
        echo '<input type="hidden" name="id" value="'.$this->cm->id.'" />';
        echo '<input type="hidden" name="indent" value="'.$currindent.'" />';
        echo '<input type="text" name="displaytext" value="" />';
        echo '<input type="submit" name="additem" value="'.get_string('additem','checklist').'" />';
        echo '</form>';
        echo '</li>';
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
        echo '<br style="clear:both"/>';
        $activegroup = groups_get_activity_group($this->cm, true);

        $table = new stdClass;
        $table->head = array(get_string('fullname'));
        $table->level = array(-1);
        $table->size = array('100px');
        foreach ($this->items as $item) {
            $table->head[] = s($item->displaytext);
            $table->level[] = ($item->indent < 3) ? $item->indent : 2;
            $table->size[] = '80px';
        }

        if ($users = get_users_by_capability($this->context, 'mod/checklist:updateown', 'u.id', '', '', '', $activegroup, '', false)) {
            $users = array_keys($users);
        }
        $ausers = get_records_sql('SELECT u.id, u.firstname, u.lastname FROM '.$CFG->prefix.'user u WHERE u.id IN ('.implode(',',$users).') ');

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
        $tickimg = '<img src="'.$CFG->pixpath.'/i/tick_green_big.gif" />';
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
        $action = optional_param('action', false, PARAM_TEXT);
        if (!$action) {
            return;
        }
        
        switch($action) {
        case 'updatechecks':
            $this->updatechecks();
            break;

        default:
            error('Invalid action - "'.s($action).'"');
        }
    }
    
    function process_edit_actions() {
        $action = optional_param('action', false, PARAM_TEXT);
        if (!$action) {
            return;
        }
        $itemid = optional_param('itemid', 0, PARAM_INT);

        switch ($action) {
        case 'additem':
            $displaytext = optional_param('displaytext', '', PARAM_TEXT);
            $indent = optional_param('indent', 0, PARAM_INT);
            $this->additem($displaytext, 0, $indent);
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
        default:
            error('Invalid action - "'.s($action).'"');
        }
    }

    function additem($displaytext, $userid=0, $indent=0, $position=false) {
        $displaytext = trim($displaytext);
        if ($displaytext == '') {
            return;
        }
        
        $item = new Object();
        $item->checklist = $this->checklist->id;
        $item->displaytext = $displaytext;
        if ($position) {
            $item->position = $position;
        } else {
            $item->position = count($this->items) + 1;
        }
        $item->indent = $indent;
        $item->userid = $userid;

        $item->id = insert_record('checklist_item', $item);
        if ($item->id) {
            if ($userid) {
                $this->useritems[$item->id] = $item;
                if ($position) {
                    uasort($this->useritems, 'checklist_itemcompare');
                }
            } else {
                if ($position) {
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
                $this->items[$itemid]->displaytext = $displaytext;
                update_record('checklist_item', $this->items[$itemid]);
            }
        } elseif (isset($this->useritems[$itemid])) {
            if ($this->canupdateown()) {
                $this->useritems[$itemid]->displaytext = $displaytext;
                update_record('checklist_item', $this->useritems[$itemid]);
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
            if (!$this->canupdateown()) {
                return;
            }
            unset($this->items[$itemid]);
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
                    update_record('checklist_item', $this->useritems[$itemid]);
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
        update_record('checklist_item', $this->items[$itemid]); // Update the database
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
        update_record('checklist_item', $this->items[$itemid]);

        // Update all 'children' of this item to new indent
        foreach ($this->items as $item) {
            if ($item->position > $position) {
                if ($item->indent > $oldindent) {
                    $item->indent += $adjust;
                    update_record('checklist_item', $item);
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

    function updatechecks() {
        $newchecks = array();
        
        foreach ($_REQUEST as $param => $val) {
            if (substr($param, 0, 4) == 'item') {
                $id = intval(substr($param, 4));
                $newval = clean_param($param, PARAM_BOOL);

                $newchecks[$id] = $newval;
                
            }
        }

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
                    
                    $check = new Object();
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