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
    var $sortby;
    var $additemafter;
    var $editdates;

    function checklist_class($cmid='staticonly', $userid=0, $checklist=NULL, $cm=NULL, $course=NULL) {
        //UT
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
        //UT
        global $DB;
        
        // Load all shared checklist items
        $sql = 'checklist = ? ';
        $sql .= ' AND userid = 0';
        $this->items = $DB->get_records('checklist_item', array('checklist' => $this->checklist->id, 'userid' => 0), 'position');

        // Makes sure all items are numbered sequentially, starting at 1
        $this->update_item_positions();

        // Load student's own checklist items
        if ($this->userid && $this->canaddown()) {
            //UT
            $sql = 'checklist = ? ';//.$this->checklist->id;
            $sql .= ' AND userid = ? ';//.$this->userid;
            $this->useritems = $DB->get_records('checklist_item', array('checklist' => $this->checklist->id, 'userid' => $this->userid), 'position, id');
        } else {
            //UT
            $this->useritems = false;
        }

        // Load the currently checked-off items
        if ($this->userid) { // && ($this->canupdateown() || $this->canviewreports() )) {
            //UT

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
        //UT
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
        //UT
        
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
        //UT
        global $USER;
        return ($this->userid == $USER->id) && has_capability('mod/checklist:updateown', $this->context);
    }

    function canaddown() {
        //UT
        global $USER;
        return $this->checklist->useritemsallowed && ($this->userid == $USER->id) && has_capability('mod/checklist:updateown', $this->context);
    }

    function canpreview() {
        //UT
        return has_capability('mod/checklist:preview', $this->context);
    }

    function canedit() {
        //UT
        return has_capability('mod/checklist:edit', $this->context);
    }

    function canviewreports() {
        //UT
        return has_capability('mod/checklist:viewreports', $this->context);
    }
        
    function view() {
        //UT
        global $CFG, $OUTPUT;
        
        $this->view_header();

        echo $OUTPUT->heading(format_string($this->checklist->name));

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
            echo $OUTPUT->footer();
            die;
        }

        $this->view_tabs($currenttab);

        if ((!$this->items) && $this->canedit()) {
            redirect($CFG->wwwroot.'/mod/checklist/edit.php?id='.$this->cm->id, get_string('noitems','checklist'));
        }

        add_to_log($this->course->id, 'checklist', 'view', "view.php?id={$this->cm->id}", $this->checklist->id, $this->cm->id);        

        if ($this->canupdateown()) {
            $this->process_view_actions();
        }

        $this->view_items();

        $this->view_footer();
    }


    function edit() {
        //UT
        global $CFG, $OUTPUT;
        
        if (!$this->canedit()) {
            redirect($CFG->wwwroot.'/mod/checklist/view.php?id='.$this->cm->id);
        }

        add_to_log($this->course->id, "checklist", "edit", "edit.php?id={$this->cm->id}", $this->checklist->id, $this->cm->id);

        $this->view_header();

        echo $OUTPUT->heading(format_string($this->checklist->name));

        $this->view_tabs('edit');

        $this->process_edit_actions();

        $this->view_edit_items();

        $this->view_footer();
    }

    function report() {
        //UT
        global $CFG, $OUTPUT;

        if (!$this->canviewreports()) {
            redirect($CFG->wwwroot.'/mod/checklist/view.php?id='.$this->cm->id);
        }

        $this->view_header();

        echo $OUTPUT->heading(format_string($this->checklist->name));

        $this->view_tabs('report');

        if ((!$this->items) && $this->canedit()) {
            redirect($CFG->wwwroot.'/mod/checklist/edit.php?id='.$this->cm->id, get_string('noitems','checklist'));
        }

        add_to_log($this->course->id, "checklist", "report", "report.php?id={$this->cm->id}", $this->checklist->id, $this->cm->id);

        $this->process_report_actions();

        if ($this->userid) {
            //UT
            $this->view_items(true);
        } else {
            //UT
            $this->view_report();
        }
        
        $this->view_footer();
    }

    function user_complete() {
        //UT
        $this->view_items(false, true);
    }

    function view_header() {
        //UT
        /*$navlinks = array();
        $navlinks[] = array('name' => $this->strchecklists, 'link' => "index.php?id={$this->course->id}", 'type' => 'activity');
        $navlinks[] = array('name' => format_string($this->checklist->name), 'link' => '', 'type' => 'activityinstance');

        $navigation = build_navigation($navlinks);

        print_header_simple($this->pagetitle, '', $navigation, '', '', true,
        update_module_button($this->cm->id, $this->course->id, $this->strchecklist), navmenu($this->course, $this->cm));*/

        global $PAGE, $OUTPUT;

        $PAGE->set_title($this->pagetitle);
        $PAGE->set_heading($this->course->fullname);

        echo $OUTPUT->header();
    }

    function view_tabs($currenttab) {
        //UT
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
        //UT
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
            if (!$item->itemoptional) {
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
        //UT
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
        //UT
        global $CFG, $DB, $OUTPUT;
        
        echo $OUTPUT->box_start('generalbox boxwidthwide boxaligncenter');

        $comments = $this->checklist->teachercomments;
        $editcomments = false;
        $thispage = $CFG->wwwroot.'/mod/checklist/view.php?id='.$this->cm->id;
        if ($viewother) {
            //UT
            $showbars = optional_param('showbars',false,PARAM_BOOL);
            if ($comments) {
                //UT
                $editcomments = optional_param('editcomments', false, PARAM_BOOL);
            }
            $thispage = $CFG->wwwroot.'/mod/checklist/report.php?id='.$this->cm->id;
            if (!$student = $DB->get_record('user', array('id' => $this->userid) )) {
                error('No such user!');
            }
            echo '<h2>'.get_string('checklistfor','checklist').' '.fullname($student, true);
            echo '&nbsp;<form style="display: inline;" action="'.$thispage.'" method="get">';
            echo '<input type="hidden" name="id" value="'.$this->cm->id.'" />';
            echo $showbars ? '<input type="hidden" name="showbars" value="on" />' : '';
            echo '<input type="submit" name="viewall" value="'.get_string('viewall','checklist').'" />';
            echo '</form>';

            if (!$editcomments) {
                //UT
                echo '<form style="display: inline;" action="'.$thispage.'" method="get">';
                echo '<input type="hidden" name="id" value="'.$this->cm->id.'" />';
                echo $showbars ? '<input type="hidden" name="showbars" value="on" />' : '';
                echo '<input type="hidden" name="editcomments" value="on" />';
                echo '<input type="hidden" name="studentid" value="'.$this->userid.'" />';
                echo ' <input type="submit" name="viewall" value="'.get_string('addcomments','checklist').'" />';
                echo '</form>';
            }
            echo '</h2>';
        }

        echo '<p>'.format_string($this->checklist->intro, $this->checklist->introformat).'</p>';

        $showteachermark = false;
        $showcheckbox = true;
        if ($this->canupdateown() || $viewother || $userreport) {
            //UT
            $this->view_progressbar();
            $showteachermark = ($this->checklist->teacheredit == CHECKLIST_MARKING_TEACHER) || ($this->checklist->teacheredit == CHECKLIST_MARKING_BOTH);
            $showcheckbox = ($this->checklist->teacheredit == CHECKLIST_MARKING_STUDENT) || ($this->checklist->teacheredit == CHECKLIST_MARKING_BOTH);
        }

        if (!$this->items) {
            //UT
            print_string('noitems','checklist');
        } else {
            //UT
            $focusitem = false;
            $updateform = ($showcheckbox && $this->canupdateown() && !$viewother && !$userreport) || ($viewother && ($showteachermark || $editcomments));
            $addown = $this->canaddown() && $this->useredit;
            if ($updateform) {
                //UT
                if ($this->canaddown() && !$viewother) {
                    //UT
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
                //UT
                list($isql, $iparams) = $DB->get_in_or_equal(array_keys($this->items));
                $params = array_merge(array($this->userid), $iparams);
                $commentsunsorted = $DB->get_records_select('checklist_comment',"userid = ? AND itemid $isql", $params);
                $commentuserids = array();
                $commentusers = array();
                if (!empty($commentsunsorted)) {
                    //UT
                    $comments = array();
                    foreach ($commentsunsorted as $comment) {
                        $comments[$comment->itemid] = $comment;
                        if ($comment->commentby) {
                            $commentuserids[] = $comment->commentby;
                        }
                    }
                    // This line may not be quite right - need to test it carefully TODO FIXME
                    list($csql, $cparams) = $DB->get_in_or_equal(array_unique($commentuserids, SORT_NUMERIC));
                    $commentusers = $DB->get_records_select('user', 'id '.$csql, $cparams);
                } else {
                    $comments = false;
                }
            }

            echo '<ol class="checklist" id="checklistouter">';
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
                $checked = (($updateform || $viewother || $userreport) && $item->checked) ? ' checked="checked" ' : '';
                if ($viewother || $userreport) {
                    $checked .= ' disabled="disabled" ';
                }
                $optional = $item->itemoptional ? ' class="itemoptional" ' : '';
                echo '<li>';
                if ($showteachermark) {
                    //UT
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
                if ($showcheckbox) {
                    echo '<input type="checkbox" name="items[]" id='.$itemname.$checked.' value="'.$item->id.'" />';
                }
                echo '<label for='.$itemname.$optional.'>'.s($item->displaytext).'</label>';

                if ($addown) {
                    //UT
                    $baseurl = $thispage.'&amp;itemid='.$item->id.'&amp;sesskey='.sesskey().'&amp;action=';
                    echo '&nbsp;<a href="'.$baseurl.'startadditem">';
                    $title = '"'.get_string('additemalt','checklist').'"';
                    echo '<img src="'.$OUTPUT->pix_url('add','checklist').'" alt='.$title.' title='.$title.' /></a>';
                }

                if ($item->duetime) {
                    //UT
                    if ($item->duetime > time()) {
                        echo '<span class="itemdue"> '.date('j M Y', $item->duetime).'</span>';
                    } else {
                        echo '<span class="itemoverdue"> '.date('j M Y', $item->duetime).'</span>';
                    }
                }

                $foundcomment = false;
                if ($comments) {
                    //UT
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
                    //UT
                    $useritem = current($this->useritems);

                    if ($useritem && ($useritem->position == $item->position)) {
                        echo '<ol class="checklist">';
                        while ($useritem && ($useritem->position == $item->position)) {
                            $itemname = '"item'.$useritem->id.'"';
                            $checked = ($updateform && $useritem->checked) ? ' checked="checked" ' : '';
                            if (isset($useritem->editme)) {
                                //UT
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
                                echo '<input type="text" name="displaytext" value="'.s($text).'" id="updateitembox" />';
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
                                //UT
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
                                    //UT
                                    $baseurl = $thispage.'&amp;itemid='.$useritem->id.'&amp;sesskey='.sesskey().'&amp;action=';
                                    echo '&nbsp;<a href="'.$baseurl.'edititem">';
                                    $title = '"'.get_string('edititem','checklist').'"';
                                    echo '<img src="'.$OUTPUT->pix_url('/t/edit').'" alt='.$title.' title='.$title.' /></a>';

                                    echo '&nbsp;<a href="'.$baseurl.'deleteitem" class="deleteicon">';
                                    $title = '"'.get_string('deleteitem','checklist').'"';
                                    echo '<img src="'.$OUTPUT->pix_url('remove','checklist').'" alt='.$title.' title='.$title.' /></a>';
                                }
                                if ($note != '') {
                                    //UT
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
                    //UT
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
                    echo '<input type="text" name="displaytext" value="" id="additembox" />';
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
                //UT
                echo '<input type="submit" name="submit" value="'.get_string('savechecks','checklist').'" />';
                echo '</form>';
            }

            if ($focusitem) {
                //UT
                echo '<script type="text/javascript">document.getElementById("'.$focusitem.'").focus();</script>';
            }

            if ($addown) {
                //UT
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
        //UT
        $id=rand();
        if ($ts == 0) {
            $disabled = true;
            $date = getdate();
        } else {
            $disabled = false;
            $date = getdate($ts);
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
        $today = getdate();
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
        //UT
        global $CFG, $OUTPUT;

        // WHY NOT WORKING FIXME
        echo $OUTPUT->box_start('generalbox boxwidthwide boxaligncenter');

        $currindent = 0;
        $addatend = true;
        $focusitem = false;
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

                echo '<li>';
                if ($item->itemoptional) {
                    $title = '"'.get_string('optionalitem','checklist').'"';
                    echo '<a href="'.$baseurl.'makerequired">';
                    echo '<img src="'.$OUTPUT->pix_url('optional','checklist').'" alt='.$title.' title='.$title.' /></a>&nbsp;';
                    $optional = ' class="itemoptional" ';
                } else {
                    $title = '"'.get_string('requireditem','checklist').'"';
                    echo '<a href="'.$baseurl.'makeoptional">';
                    echo '<img src="'.$OUTPUT->pix_url('required','checklist').'" alt='.$title.' title='.$title.' /></a>&nbsp;';
                    $optional = '';
                }

                if (isset($item->editme)) {
                    echo '<form style="display:inline" action="'.$CFG->wwwroot.'/mod/checklist/edit.php" method="post">';
                    echo '<input type="hidden" name="action" value="updateitem" />';
                    echo '<input type="hidden" name="id" value="'.$this->cm->id.'" />';
                    echo '<input type="hidden" name="itemid" value="'.$item->id.'" />';
                    echo '<input type="hidden" name="sesskey" value="'.sesskey().'" />';
                    echo '<input type="text" name="displaytext" value="'.s($item->displaytext).'" id="updateitembox" />';
                    if ($this->editdates) {
                        echo '<input type="hidden" name="editdates" value="on" />';
                        $this->print_edit_date($item->duetime);
                    }
                    echo '<input type="submit" name="updateitem" value="'.get_string('updateitem','checklist').'" />';
                    echo '</form>';

                    $focusitem = 'updateitembox';

                    echo '<form style="display:inline" action="'.$CFG->wwwroot.'/mod/checklist/edit.php" method="get">';
                    echo '<input type="hidden" name="id" value="'.$this->cm->id.'" />';
                    echo '<input type="hidden" name="editdates" value="on" />';
                    if ($this->additemafter) {
                        echo '<input type="hidden" name="additemafter" value="'.$this->additemafter.'" />';
                    }
                    echo '<input type="submit" name="canceledititem" value="'.get_string('canceledititem','checklist').'" />';
                    echo '</form>';
                } else {
                    echo '<label for='.$itemname.$optional.'>'.s($item->displaytext).'</label>&nbsp;';

                    echo '<a href="'.$baseurl.'edititem">';
                    $title = '"'.get_string('edititem','checklist').'"';
                    echo '<img src="'.$OUTPUT->pix_url('/t/edit').'"  alt='.$title.' title='.$title.' /></a>&nbsp;';

                    if ($item->indent > 0) {
                        echo '<a href="'.$baseurl.'unindentitem">';
                        $title = '"'.get_string('unindentitem','checklist').'"';
                        echo '<img src="'.$OUTPUT->pix_url('/t/left').'" alt='.$title.' title='.$title.'  /></a>';
                    }

                    if (($item->indent < CHECKLIST_MAX_INDENT) && (($lastindent+1) > $currindent)) {
                        echo '<a href="'.$baseurl.'indentitem">';
                        $title = '"'.get_string('indentitem','checklist').'"';
                        echo '<img src="'.$OUTPUT->pix_url('/t/right').'" alt='.$title.' title='.$title.' /></a>';
                    }

                    echo '&nbsp;';
                    
                    // TODO more complex checks to take into account indentation
                    if ($item->position > 1) {
                        echo '<a href="'.$baseurl.'moveitemup">';
                    $title = '"'.get_string('moveitemup','checklist').'"';
                    echo '<img src="'.$OUTPUT->pix_url('/t/up').'" alt='.$title.' title='.$title.' /></a>';
                    }

                    if ($item->position < $lastitem) {
                        echo '<a href="'.$baseurl.'moveitemdown">';
                    $title = '"'.get_string('moveitemdown','checklist').'"';
                    echo '<img src="'.$OUTPUT->pix_url('/t/down').'" alt='.$title.' title='.$title.' /></a>';
                    }

                    echo '&nbsp;<a href="'.$baseurl.'deleteitem">';
                    $title = '"'.get_string('deleteitem','checklist').'"';
                    echo '<img src="'.$OUTPUT->pix_url('/t/delete').'" alt='.$title.' title='.$title.' /></a>';
                    
                    echo '&nbsp;&nbsp;&nbsp;<a href="'.$baseurl.'startadditem">';
                    $title = '"'.get_string('additemhere','checklist').'"';
                    echo '<img src="'.$OUTPUT->pix_url('add','checklist').'" alt='.$title.' title='.$title.' /></a>';
                    if ($item->duetime) {
                        if ($item->duetime > time()) {
                            echo '<span class="itemdue"> '.date('j M Y', $item->duetime).'</span>';
                        } else {
                            echo '<span class="itemoverdue"> '.date('j M Y', $item->duetime).'</span>';
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
                        echo '<input type="text" name="displaytext" value="" id="additembox" />';
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
        if ($addatend) {
            echo '<li>';
            echo '<form action="'.$CFG->wwwroot.'/mod/checklist/edit.php" method="post">';
            echo '<input type="hidden" name="action" value="additem" />';
            echo '<input type="hidden" name="id" value="'.$this->cm->id.'" />';
            echo '<input type="hidden" name="indent" value="'.$currindent.'" />';
            echo '<input type="hidden" name="sesskey" value="'.sesskey().'" />';
            echo '<input type="text" name="displaytext" value="" id="additembox" />';
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
        echo '</form>';

        if ($focusitem) {
            echo '<script type="text/javascript">document.getElementById("'.$focusitem.'").focus();</script>';
        }

        echo $OUTPUT->box_end();
    }

    function view_report() {
        //UT
        global $CFG, $DB, $OUTPUT;

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
            //UT
            if ($ausers) {
                // Show just progress bars
                if ($this->showoptional) {
                    $itemstocount = array_keys($this->items);
                } else {
                    $itemstocount = array();
                    foreach ($this->items as $item) {
                        if (!$item->itemoptional) {
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
                    //UT
                    if ($totalitems) {
                        $iparams['user'] = $auser->id;
                        $tickeditems = $DB->count_records_select('checklist_check', $sql, $iparams);
                        $percentcomplete = ($tickeditems * 100) / $totalitems;
                    } else {
                        $percentcomplete = 0;
                        $tickeditems = 0;
                    }

                    $vslink = ' <a href="'.$thisurl.'&amp;studentid='.$auser->id.'" ';
                    $vslink .= 'alt="'.get_string('viewsinglereport','checklist').'" title="'.get_string('viewsinglereport','checklist').'">';
                    $vslink .= '<img src="'.$OUTPUT->pix_url('/t/preview').'" /></a>';
                    $userlink = '<a href="'.$CFG->wwwroot.'/user/view.php?id='.$auser->id.'&amp;course='.$this->course->id.'">'.fullname($auser).'</a>';
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
            //UT
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
                $table->head[] = s($item->displaytext);
                $table->level[] = ($item->indent < 3) ? $item->indent : 2;
                $table->size[] = '80px';
                $table->skip[] = (!$this->showoptional) && $item->itemoptional;
            }

            $table->data = array();
            if ($ausers) {
                foreach ($ausers as $auser) {
                    //UT
                    $row = array();
                
                    $vslink = ' <a href="'.$thisurl.'&amp;studentid='.$auser->id.'" ';
                    $vslink .= 'alt="'.get_string('viewsinglereport','checklist').'" title="'.get_string('viewsinglereport','checklist').'" />';
                    $vslink .= '<img src="'.$OUTPUT->pix_url('/t/preview').'" /></a>';
                    $userlink = '<a href="'.$CFG->wwwroot.'/user/view.php?id='.$auser->id.'&amp;course='.$this->course->id.'">'.fullname($auser).'</a>';

                    $row[] = $userlink.$vslink;

                    $sql = 'SELECT i.id, c.usertimestamp, c.teachermark FROM {checklist_item} i LEFT JOIN {checklist_check} c ';
                    $sql .= 'ON (i.id = c.item AND c.userid = ? ) WHERE i.checklist = ? AND i.userid=0 ORDER BY i.position';
                    $checks = $DB->get_records_sql($sql, array($auser->id, $this->checklist->id) );

                    foreach ($checks as $check) {
                        //UT
                        if ($check->usertimestamp > 0) {
                            $row[] = array($check->teachermark,true);
                        } else {
                            $row[] = array($check->teachermark,false);
                        }
                    }

                    $table->data[] = $row;
                }
            }
        
            $this->print_report_table($table);
        }
    }

    function print_report_table($table) {
        //UT
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
                    $cellclass = 'cell c'.$key.' level'.$table->level[$key];
                    list($teachermark, $studentmark) = $item;
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
            $output .= '</tr>';
        }
        
        $output .= '</table>';

        echo $output;
    }

    function view_footer() {
        global $OUTPUT;
        //UT
        echo $OUTPUT->footer();
    }

    function process_view_actions() {
        //UT
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
        //UT
        $this->editdates = optional_param('editdates', false, PARAM_BOOL);
        $additemafter = optional_param('additemafter', false, PARAM_INT);
        
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

        if ($additemafter) {
            $this->additemafter = $additemafter;
        }
    }

    function process_report_actions() {
        //UT
        $this->showoptional = true;
        $this->sortby = optional_param('sortby', 'firstasc', PARAM_TEXT);
        
        $action = optional_param('action', false, PARAM_TEXT);
        if (!$action) {
            return;
        }

        if ($action == 'hideoptional') {
            $this->showoptional = false;
        } else if ($action == 'updatechecks') {
            $this->updateteachermarks();
        }
    }

    function additem($displaytext, $userid=0, $indent=0, $position=false, $duetime=false) {
        //UT
        global $DB;
        
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
        $item->duetime = 0;
        if ($duetime) {
            $item->duetime = gmmktime(0,0,0, $duetime['month'], $duetime['day'], $duetime['year']);
        }
        $item->eventid = 0;

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
    }

    function setevent($itemid, $add) {
        //UT
        global $DB;
        
        $item = $this->items[$itemid];
        $update = false;

        if  ((!$add) || ($item->duetime == 0)) {  // Remove the event (if any)
            //UT
            if (!$item->eventid) {
                return; // No event to remove
            }

            delete_event($item->eventid);
            $this->items[$itemid]->eventid = 0;
            $update = true;
            
        } else {  // Add/update event
            //UT
            $event = new stdClass;
            $event->name = $item->displaytext;
            $event->description = get_string('calendardescription', 'checklist', $this->checklist->name);
            $event->courseid = $this->course->id;
            $event->modulename = 'checklist';
            $event->instance = $this->checklist->id;
            $event->eventtype = 'due';
            $event->timestart = $item->duetime;

            if ($item->eventid) {
                //UT
                $event->id = $item->eventid;
                update_event($event);
            } else {
                //UT
                $this->items[$itemid]->eventid = add_event($event);
                $update = true;
            }
        }

        if ($update) { // Event added or removed
            //UT
            $upditem = new stdClass;
            $upditem->id = $itemid;
            $upditem->eventid = $this->items[$itemid]->eventid;
            $DB->update_record('checklist_item', $upditem);
        }
    }

    function setallevents() {
        //UT
        if (!$this->items) {
            return;
        }

        //UT
        $add = $this->checklist->duedatesoncalendar;
        foreach ($this->items as $key => $value) {
            $this->setevent($key, $add);
        }
    }

    function updateitemtext($itemid, $displaytext, $duetime=false) {
        //UT
        global $DB;

        $displaytext = trim($displaytext);
        if ($displaytext == '') {
            return;
        }

        if (isset($this->items[$itemid])) {
            //UT
            if ($this->canedit()) {
                $this->items[$itemid]->displaytext = $displaytext;
                $upditem = new stdClass;
                $upditem->id = $itemid;
                $upditem->displaytext = $displaytext;

                $upditem->duetime = 0;
                if ($duetime) {
                    $upditem->duetime = gmmktime(0,0,0, $duetime['month'], $duetime['day'], $duetime['year']);
                }
                $this->items[$itemid]->duetime = $upditem->duetime;

                $DB->update_record('checklist_item', $upditem);

                if ($this->checklist->duedatesoncalendar) {
                    $this->setevent($itemid, true);
                }
            }
        } elseif (isset($this->useritems[$itemid])) {
            //UT
            if ($this->canaddown()) {
                $this->useritems[$itemid]->displaytext = $displaytext;
                $upditem = new stdClass;
                $upditem->id = $itemid;
                $upditem->displaytext = $displaytext;
                $DB->update_record('checklist_item', $upditem);
            }
        }
    }

    function deleteitem($itemid) {
        //UT
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

        $DB->delete_records('checklist_item', array('id', $itemid) );
        $DB->delete_records('checklist_check', array('item', $itemid) );

        $this->update_item_positions();
    }

    function moveitemto($itemid, $newposition) {
        //UT
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
        //UT

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
        //UT

        if (!isset($this->items[$itemid])) {
            if (isset($this->useritems[$itemid])) {
                $this->moveitemto($itemid, $this->useritems[$itemid]->position + 1);
            }
            return;
        }
        $this->moveitemto($itemid, $this->items[$itemid]->position + 1);
    }
        
    function indentitemto($itemid, $indent) {
        //UT
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
        //UT

        if (!isset($this->items[$itemid])) {
            // Not able to indent useritems, as they are always parent + 1
            return;
        }
        $this->indentitemto($itemid, $this->items[$itemid]->indent + 1);
    }

    function unindentitem($itemid) {
        //UT

        if (!isset($this->items[$itemid])) {
            // Not able to indent useritems, as they are always parent + 1
            return;
        }
        $this->indentitemto($itemid, $this->items[$itemid]->indent - 1);
    }

    function makeoptional($itemid, $optional) {
        //UT
        global $DB;
        
        if (!isset($this->items[$itemid])) {
            return;
        }

        $this->items[$itemid]->itemoptional = $optional;
        $upditem = new stdClass;
        $upditem->id = $itemid;
        $upditem->itemoptional = $optional;
        $DB->update_record('checklist_item', $upditem);
    }

    function updatechecks() {
        //UT
        global $DB;
        
        $newchecks = optional_param('items', array(), PARAM_INT);
        if (!is_array($newchecks)) {
            // Something has gone wrong, so update nothing
            return;
        }
        
        if ($this->items) {
            foreach ($this->items as $item) {
                $newval = in_array($item->id, $newchecks);

                if ($newval != $item->checked) {
                    $item->checked = $newval;
                
                    $check = $DB->get_record('checklist_check', array('item' => $item->id, 'userid' => $this->userid) );
                    if ($check) {
                        //UT
                        if ($newval) {
                            $check->usertimestamp = time();
                        } else {
                            $check->usertimestamp = 0;
                        }
                        
                        $DB->update_record('checklist_check', $check);

                    } else {
                        //UT
                        $check = new stdClass;
                        $check->item = $item->id;
                        $check->userid = $this->userid;
                        $check->usertimestamp = time();
                        $check->teachertimestamp = 0;
                        $check->teachermark = CHECKLIST_TEACHERMARK_UNDECIDED;
                    
                        echo "Add <br/>";
                        $check->id = $DB->insert_record('checklist_check', $check);
                    }
                    checklist_update_grades($this->checklist, $this->userid);
                }
            }
        }
        if ($this->useritems) {
            foreach ($this->useritems as $item) {
                $newval = in_array($item->id, $newchecks);

                if ($newval != $item->checked) {
                    $item->checked = $newval;
                
                    $check = $DB->get_record('checklist_check', array('item' => $item->id, 'userid' => $this->userid) );
                    if ($check) {
                        //UT
                        if ($newval) {
                            $check->usertimestamp = time();
                        } else {
                            $check->usertimestamp = 0;
                        }
                        $DB->update_record('checklist_check', $check);

                    } else {
                        //UT
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
        //UT
        global $USER, $DB;
        
        $newchecks = optional_param('items', array(), PARAM_TEXT);
        if (!is_array($newchecks)) {
            // Something has gone wrong, so update nothing
            return;
        }

        if ($this->checklist->teacheredit != CHECKLIST_MARKING_STUDENT) {

            //TODO - update this to set the array key to the itemid
            foreach ($newchecks as $newcheck) {
                list($itemid, $newval) = explode(':',$newcheck, 2);
            
                if (isset($this->items[$itemid])) {
                    $item = $this->items[$itemid];
                    if ($newval != $item->teachermark) {
                        $item->teachermark = $newval;
                    
                        $newcheck = new stdClass;
                        $newcheck->teachertimestamp = time();
                        $newcheck->teachermark = $newval;
                    
                        $oldcheck = $DB->get_record_select('checklist_check', array('item' => $item->id, 'userid' => $this->userid) );
                        if ($oldcheck) {
                            //UT
                            $newcheck->id = $oldcheck->id;
                            $DB->update_record('checklist_check', $newcheck);
                        } else {
                            //UT
                            $newcheck->item = $itemid;
                            $newcheck->userid = $this->userid;
                            $newcheck->id = $DB->insert_record('checklist_check', $newcheck);
                        }
                        checklist_update_grades($this->checklist, $this->userid);
                    }
                }
            }
        }

        $newcomments = optional_param('teachercomment', false, PARAM_TEXT);
        if (!$this->checklist->teachercomments || !$newcomments || !is_array($newcomments)) {
            return;
        }
        //UT

        list($isql, $iparams) = $DB->get_in_or_equal(array_keys($this->items));
        $commentsunsorted = $DB->get_records_select('checklist_comment',"userid = ? AND itemid $isql", array_merge(array($this->userid), $iparams) );
        $comments = array();
        foreach ($commentsunsorted as $comment) {
            $comments[$comment->itemid] = $comment;
        }
        foreach ($newcomments as $itemid => $newcomment) {
            $newcomment = trim($newcomment);
            if ($newcomment == '') {
                //UT
                if (array_key_exists($itemid, $comments)) {
                    $DB->delete_records('checklist_comment', array('id' => $comments[$itemid]->id) );
                    unset($comments[$itemid]); // Should never be needed, but just in case...
                }
            } else {
                if (array_key_exists($itemid, $comments)) {
                    //UT
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
                    //UT
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

    /* static function - avoiding 'static' keyword for PHP 4 compatibility */
    function print_user_progressbar($checklistid, $userid, $width='300px', $showpercent=true, $return=false) {
        //UT
        global $OUTPUT;

        list($ticked, $total) = checklist_class::get_user_progress($checklistid, $userid);
        if (!$total) {
            return;
        }
        $percent = $ticked * 100 / $total;

        // Sadly 'styles.php' will not be included from outside the module, so I have to hard-code the styles here
        $output = '<div class="checklist_progress_outer" style="border-width: 1px; border-style: solid; border-color: black; width: '.$width.'; background-colour: transparent; height: 15px; float: left;" >';
        $output .= '<div class="checklist_progress_inner" style="width:'.$percent.'%; background-image: url('.$OUTPUT->pix_url('progress','checklist').'); background-color: #229b15; height: 100%; background-repeat: repeat-x; background-position: top;" >&nbsp;</div>';
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
        //UT
        global $DB;
        
        $userid = intval($userid); // Just to be on the safe side...

        $checklist = $DB->get_record('checklist', array('id' => $checklistid) );
        if (!$checklist) {
            return array(false, false);
        }
        $items = $DB->get_records('checklist_item', array('checklist' => $checklist->id, 'userid' => 0, 'itemoptional' => 0), '', 'id');
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
    //UT
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