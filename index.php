<?php

///////////////////////////////////////////////////////////////////////////
//                                                                       //
// NOTICE OF COPYRIGHT                                                   //
//                                                                       //
// Moodle - Modular Object-Oriented Dynamic Learning Environment         //
//          http://moodle.com                                            //
//                                                                       //
// Copyright (C) 1999 onwards  Martin Dougiamas  http://moodle.com       //
//                                                                       //
// This program is free software; you can redistribute it and/or modify  //
// it under the terms of the GNU General Public License as published by  //
// the Free Software Foundation; either version 2 of the License, or     //
// (at your option) any later version.                                   //
//                                                                       //
// This program is distributed in the hope that it will be useful,       //
// but WITHOUT ANY WARRANTY; without even the implied warranty of        //
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the         //
// GNU General Public License for more details:                          //
//                                                                       //
//          http://www.gnu.org/copyleft/gpl.html                         //
//                                                                       //
///////////////////////////////////////////////////////////////////////////


require_once(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');
require_once($CFG->dirroot.'/grade/lib.php');


$courseid = required_param('id', PARAM_INT);                   // course id

if (!$course = get_record('course', 'id', $courseid)) {
    print_error('nocourseid');
}

require_login($course->id);
$context = get_context_instance(CONTEXT_COURSE, $course->id);

$viewall = has_capability('gradereport/checklist:viewall', $context);
$viewdistrict = has_capability('gradereport/checklist:viewdistrict', $context);
if (!$viewall && !$viewdistrict) {
    error('You do not have permission to view this report');
}

// Build navigation
$strgrades = get_string('grades');
$strchkgrades = get_string('modulename', 'gradereport_checklist');

$navigation = grade_build_nav(__FILE__, $strchkgrades, $course->id);

/// Print header
$release = explode(' ', $CFG->release);
$relver = explode('.', $release[0]);
if (intval($relver[0]) == 1 && intval($relver[1]) == 9 && intval($relver[2]) < 5) {
    print_header_simple($strgrades.':'.$strchkgrades, ':'.$strgrades, $navigation, '', '', true);
    print_grade_plugin_selector($courseid, 'report', 'checklist');
    print_heading($strchkgrades);
} else {
    print_grade_page_head($COURSE->id, 'report', 'checklist', $strchkgrades, false, null);
}

/*
if (has_capability('gradereport/updfgrades:viewall', $context)) {
    groups_print_course_menu($course, $thisurl);
    $activegroup = groups_get_course_group($course, true);

    echo '<div id="reportoptions" class="generalbox boxaligncenter boxwidthnormal">';
    echo '<form action="'.$thisurl.'" method="get">';
    echo get_string('show','gradereport_updfgrades').'<br/>';
    echo '<input type="hidden" name="id" value="'.$course->id.'" />';
    if ($studentid) {
        echo '<input type="hidden" name="studentid" value="'.$studentid.'" />';
    }
    if ($showdays) {
        echo '<input type="hidden" name="showdays" value="'.$showdays.'" />';
    }
    echo '<div style="float: left">';
    echo '<input type="checkbox" id="showgrades" name="showgrades" '.($showgrades?'checked="checked" ':'').'/><label for="showgrades">'.get_string('showgrades','gradereport_updfgrades').'</label><br />';
    echo '<input type="checkbox" id="showcomments" name="showcomments" '.($showcomments?'checked="checked" ':'').'/><label for="showcomments">'.get_string('showcomments','gradereport_updfgrades').'</label><br />';
    echo '<input type="checkbox" id="showlate" name="showlate" '.($showlate?'checked="checked" ':'').'/><label for="showlate">'.get_string('showlate','gradereport_updfgrades').'</label><br/>';
    echo '</div>';
    echo '<div style="float: left">';
    foreach ($categories as $cid => $cname) {
        $checked = 'checked="checked"';
        if ($catselected) {
            if (!in_array($cid, $catselected)) {
                $checked = '';
            }
        }
        echo '<input type="checkbox" id="cat'.$cid.'" name="category[]" value="'.$cid.'" '.$checked.' /><label for="cat'.$cid.'" >'.s($cname).'</label><br/>';
    }
    echo '</div>';
    echo '<br clear="both" />';
    echo '<input type="submit" name="update" value="Update" /><br />';
    echo '</form>';
    echo '</div>';

    // Select only certain days to show
    echo '<form action="'.$thisurl.'" method="get">';
    echo '<input type="hidden" name="id" value="'.$course->id.'" />';
    if ($studentid) { echo '<input type="hidden" name="studentid" value="'.$studentid.'" />'; }
    if ($showdays) { echo '<input type="hidden" name="showdays" value="'.$showdays.'" />'; }
    if ($catselected) {
        foreach ($catselected as $catid) {
            echo '<input type="hidden" name="category[]" value="'.$catid.'" />';
        }
    }
    if ($showgrades) { echo '<input type="hidden" name="showgrades" value="on" />'; }
    if ($showcomments) { echo '<input type="hidden" name="showcomments" value="on" />'; }
    if ($showlate) { echo '<input type="hidden" name="showlate" value="on" />'; }

    $timeperiods = array( 0=>get_string('showall'),
                          1=>get_string('today'),
                          2=>get_string('2days','gradereport_updfgrades'),
                          3=>get_string('3days','gradereport_updfgrades'),
                          5=>get_string('5days','gradereport_updfgrades'),
                          7=>get_string('1week','gradereport_updfgrades'),
                          14=>get_string('2weeks','gradereport_updfgrades'),
                          21=>get_string('3weeks','gradereport_updfgrades'),
                          31=>get_string('1month','gradereport_updfgrades'),
                          62=>get_string('2months','gradereport_updfgrades'));

    echo '<label for="showdays" >'.get_string('selecttimeframe','gradereport_updfgrades').'</label> ';
    echo '<select name="showdays" id="showdays">';
    foreach ($timeperiods as $time => $name) {
        $selected = ($showdays == $time) ? ' selected = "selected" ' : '';
        echo '<option value="'.$time.'" '.$selected.'>'.$name.'</option>';
    }
    echo '</select>';
    echo '<input type="submit" value="'.get_string('update').'" id="showdayssubmit" />';
    echo '</form>';

    $params = array('courseid'=>$course->id, 'itemtype'=>'mod', 'itemmodule'=>'assignment', 'outcomeid'=>null);
    if ($catselected) {
        $items = array();
        foreach ($catselected as $catid) {
            $extraitems = grade_item::fetch_all($params + array('categoryid'=>$catid));
            if ($extraitems) {
                $items += $extraitems;
            }
        }
    } else {
        $items = grade_item::fetch_all($params);
    }

    if (!$items) {
        print_string('noassignments','gradereport_updfgrades');
        print_footer($course);
        die;
    }

    $asskeys = array();
    foreach ($items as $item) {
        $asskeys[] = $item->iteminstance;
    }
    $asskeys = implode(',',$asskeys);
    $assignments = get_records_sql('SELECT a.id, a.timedue, a.name, a.grade, a.assignmenttype FROM '.$CFG->prefix.'assignment a WHERE a.id IN ('.$asskeys.') ORDER BY a.name');

    if (!$assignments) {
        $assignments = array();
    }

    if ($users = get_users_by_capability($context, 'mod/assignment:submit', 'u.id', '', '', '', $activegroup, '', false)) {
        $users = array_keys($users);
    }

    if (!$showdays) {

        $showalllink = '';
        if ($studentid) {
            if (in_array($studentid, $users)) {
                $users = array($studentid);
                $showalllink = '<br/><a href="'.$thisurl.'&amp;studentid=0">'.get_string('showallstudents','gradereport_updfgrades').'</a><br/>';
            } else {
                $studentid = 0;
            }
        }

        $tablecolumns = array('fullname');
        $tableheaders = array(get_string('fullname'));

        $scales_list = array();
        foreach ($assignments as $assignment) {
            $tablecolumns[] = 'asn'.$assignment->id;

            $link = $CFG->wwwroot.'/mod/assignment/submissions.php?a='.$assignment->id;

            if ($studentid) {
                $link .= '&amp;mode=single&amp;offset=0&amp;userid='.$studentid;
                $header = link_to_popup_window ($link, 'grade'.$studentid, s($assignment->name), 600, 780,
                                                s($assignment->name), 'none', true, 'button'.$studentid);
            } else {
                $header = '<a href="'.$link.'" target="_blank" >'.$assignment->name.'</a>';
            }
            $tableheaders[] = $header;
            if ($assignment->grade < 0) {
                $scales_list[] = -$assignment->grade;
            }
        }
        $scales_list = implode(',', array_unique($scales_list));
        $scales_array = array();
        if (!empty($scales_list)) {
            $scales_array = get_records_list('scale', 'id', $scales_list);
        }

        $table = new flexible_table('mod-assignment-uploadpdf-report');

        $table->define_columns($tablecolumns);
        $table->define_headers($tableheaders);
        $table->define_baseurl($thisurl);


        $table->sortable(true, 'firstname');
        $table->collapsible(true);
        //$table->initialbars(true);
        $table->column_suppress('fullname');

        $table->column_class('fullname','fullname');
        $table->set_attribute('cellspacing', '0');
        $table->set_attribute('id', 'attempts');
        $table->set_attribute('class', 'submissions');
        $table->set_attribute('width', '100%');

        foreach ($tablecolumns as $tc) {
            if (substr($tc,0,3) == 'asn') {
                $table->no_sorting($tc);
            }
        }

        $table->setup();

        $sort = '';
        if ($sort = $table->get_sql_sort()) {
            $sort = ' ORDER BY '.$sort;
        }

        $ausers = get_records_sql('SELECT u.id, u.firstname, u.lastname FROM '.$CFG->prefix.'user u WHERE u.id IN ('.implode(',',$users).') '.$sort);
        $lateletter = get_string('lateletter','gradereport_updfgrades');

        if ($ausers) {

            foreach ($ausers as $auser) {

                //$userlink = '<a href="'.$CFG->wwwroot.'/usr/view.php?id='.$auser->id.'&amp;course='.$course->id.'">'.fullname($auser).'</a>';
                if ($studentid) {
                    $userlink = fullname($auser);
                } else {
                    $userlink = '<a href="'.$thisurl.'&amp;studentid='.$auser->id.'">'.fullname($auser).'</a>';
                }
                $results = get_records_sql('SELECT s.assignment, s.grade, s.submissioncomment, s.timemodified, s.data2 FROM '.$CFG->prefix.'assignment_submissions s '.
                                           'WHERE s.userid = '.$auser->id.' AND s.assignment IN ('.$asskeys.')');
                $row = array($userlink);
                foreach ($assignments as $assignment) {
                    if ($results && array_key_exists($assignment->id, $results)) {
                        $output = '';
                        $result = $results[$assignment->id];
                        if ($showgrades) {
                            if ($result->grade < 0) {
                                $output .= '-';
                            } else {
                                if ($assignment->grade < 0) {
                                    if (!empty($scales_array[-$assignment->grade])) {
                                        $scale = $scales_array[-$assignment->grade];
                                        $values = explode(',', $scale->scale);
                                        $result->grade = (int)$result->grade;
                                        if (!empty($values[$result->grade-1])) {
                                            $output .= s($values[$result->grade-1]);
                                        } else {
                                            $output .= get_string('gradeerror','gradereport_updfgrades');
                                        }
                                    } else {
                                        $output .= get_string('gradeerror','gradereport_updfgrades');
                                    }
                                } else {
                                    $output .= $result->grade;
                                }
                            }
                        }
                        if ($showcomments) {
                            $fulltext = strip_tags($result->submissioncomment);
                            $comment = '<span title="'.$fulltext.'">'.shorten_text($fulltext,25).'</span>';
                            if ($showgrades && ($comment != '')) {
                                $output .= '; ';
                            }
                            $output .= $comment;
                        }
                        if ($showlate) {
                            if ($assignment->timedue) {
                                if ($result->grade >= 0) {
                                    $completed = true; // Always treat assignments as submitted once they have a grade
                                    // (to stop marked unsubmitted, but marked assignments from cluttering up 'lateness' displays)
                                } elseif ($assignment->assignmenttype == 'uploadpdf') {
                                    $completed = $result->data2 == 'submitted' || $result->data2 == 'responded';
                                } else {
                                    $completed = $result->timemodified > 0;
                                }
                                if (!$completed) {
                                    $dayslate = time() - $assignment->timedue;
                                    if ($dayslate > DAYSECS) {
                                        $dayslate = floor($dayslate/DAYSECS);
                                        $output .= ' <span class="late">('.$lateletter.$dayslate.'+)</span>';
                                    }
                                } else {
                                    $dayslate = $result->timemodified - $assignment->timedue;
                                    if ($dayslate > DAYSECS) {
                                        $dayslate = floor($dayslate/DAYSECS);
                                        $output .= ' <span class="late">('.$lateletter.$dayslate.')</span>';
                                    }
                                }
                            }
                        }

                        $row[] = $output;
                    } else {
                        $dayslate = 0;
                        if ($assignment->timedue) {
                            $secslate = time() - $assignment->timedue;
                            if ($secslate > DAYSECS) {
                                $dayslate = floor($secslate/DAYSECS);
                            }
                        }
                        if ($showlate && $dayslate) {
                            $output = '<span class="late">('.$lateletter.$dayslate.'+)</span>';
                        } else {
                            $output = '-';
                        }

                        $row[] = $output;
                    }
                }
                $table->add_data($row);
            }
        }

        $table->print_html();

        echo $showalllink;
        if (!$studentid) {
            echo '<br/>';
            print_string('showsingle','gradereport_updfgrades');
        }
    } else {
        // showdays

        $showtimemarked = time() - ($showdays * 24 * 60 * 60);
        $sort = '';
        $userlist = implode(',',$users);
        $ausers = get_records_sql('SELECT u.id, u.firstname, u.lastname FROM '.$CFG->prefix.'user u WHERE u.id IN ('.$userlist.') '.$sort);

        $table = new stdClass;
        $table->head = array(get_string('fullname'), get_string('grade'), get_string('feedback'));
        $table->size = array('80px', '30px', '100px');

        if ($ausers) {
            $scales_list = array();
            foreach ($assignments as $assignment) {
                if ($assignment->grade < 0) {
                    $scales_list[] = -$assignment->grade;
                }
            }
            $scales_list = implode(',', array_unique($scales_list));
            $scales_array = array();
            if (!empty($scales_list)) {
                $scales_array = get_records_list('scale', 'id', $scales_list);
            }

            $shownresults = false;
            foreach ($assignments as $assignment) {
                $results = get_records_sql('SELECT s.userid, s.grade, s.submissioncomment FROM '.$CFG->prefix.'assignment_submissions s '.
                                           'JOIN '.$CFG->prefix.'user u ON s.userid = u.id '.
                                           'WHERE s.userid IN ('.$userlist.') AND s.assignment = '.$assignment->id.' AND s.timemarked > '.$showtimemarked.' AND s.grade != -1 '.
                                           'ORDER BY u.firstname');
                if ($results) {
                    $table->data = array();
                    $shownresults = true;
                    print_heading(s($assignment->name));
                    foreach ($results as $result) {
                        $row = array();
                        $row[] = fullname($ausers[$result->userid]);
                        if ($assignment->grade < 0) {
                            if (!empty($scales_array[-$assignment->grade])) {
                                $scale = $scales_array[-$assignment->grade];
                                $values = explode(',', $scale->scale);
                                $result->grade = (int)$result->grade;
                                if (!empty($values[$result->grade-1])) {
                                    $row[] = s($values[$result->grade-1]);
                                } else {
                                    $row[] = get_string('gradeerror','updfgrades');
                                }
                            }
                        } else {
                            $row[] = $result->grade;
                        }
                        $row[] = s($result->submissioncomment);
                        $table->data[] = $row;
                    }
                    print_table($table);
                }
            }
            if (!$shownresults) {
                print_string('nomarkingintime','gradereport_updfgrades');
            }
        }
    }
} else {
    // Student view (does not have ability to view all grades)

    $table = new stdClass;
    $table->head = array(get_string('modulename','assignment'), get_string('submitted','gradereport_updfgrades'), get_string('grade'), get_string('feedback'));
    $table->size = array('80px', '30px', '30px', '150px');
    $params = array('courseid'=>$course->id, 'itemtype'=>'mod', 'itemmodule'=>'assignment', 'outcomeid'=>null);
    $tickimg = '<img src="'.$CFG->pixpath.'/i/tick_green_big.gif" alt="'.get_string('submitted','gradereport_updfgrades').'" />';
    $crossimg = '<img src="'.$CFG->pixpath.'/i/cross_red_big.gif" alt="'.get_string('notsubmitted','gradereport_updfgrades').'" />';
    $assignmentmodule = get_record('modules', 'name', 'assignment', '', '', '', '', 'id');
    if (!$assignmentmodule) {
        error('Assignment module type not installed!');
        die;
    }
    $assignmentmodule = $assignmentmodule->id;

    $canviewhidden = has_capability('moodle/grade:viewhidden', $context);

    $itemsshown = false;

    foreach ($categories as $cid => $cname) {
        $table->data = array();

        $items = grade_item::fetch_all($params + array('categoryid'=>$cid));
        if (!$items) {
            continue;
        }
        $asskeys = array();
        foreach ($items as $item) {
            if ($canviewhidden or !$item->is_hidden()) {
                $asskeys[] = $item->iteminstance;
            }
        }
        if (count($asskeys) == 0) {
            continue;
        }
        $asskeys = implode(',',$asskeys);
        $select = 'SELECT a.id, a.timedue, a.name, a.grade, a.assignmenttype FROM '.$CFG->prefix.'assignment a ';
        $where = 'WHERE a.id IN ('.$asskeys.')';
        $order = ' ORDER BY a.name';
        if (!$canviewhidden) {
            $select .= ', '.$CFG->prefix.'course_modules cm ';
            $where .= ' AND cm.instance = a.id AND cm.visible = 1 AND cm.module = '.$assignmentmodule;
        }
        $assignments = get_records_sql($select.$where.$order);
        if (!$assignments) {
            continue;
        }

        $scales_list = array();
        foreach ($assignments as $assignment) {
            $scales_list[] = -$assignment->grade;
        }
        $scales_list = implode(',', array_unique($scales_list));
        $scales_array = array();
        if (!empty($scales_list)) {
            $scales_array = get_records_list('scale', 'id', $scales_list);
        }

        $select = 'SELECT s.assignment, s.grade, s.submissioncomment, s.timemodified, s.data2 FROM '.$CFG->prefix.'assignment_submissions s ';
        $where = 'WHERE s.userid = '.$USER->id.' AND s.assignment IN ('.$asskeys.')';
        $results = get_records_sql($select.$where);

        if (!$results) {
            $results = array();
        }

        print_heading(s($cname));

        foreach ($assignments as $assignment) {
            $row = array();
            $row[] = '<a href="'.$CFG->wwwroot.'/mod/assignment/view.php?a='.$assignment->id.'" target="_blank" >'.s($assignment->name).'</a>';

            if (array_key_exists($assignment->id, $results)) {
                $result = $results[$assignment->id];

                if ($result->grade >= 0) {
                    $completed = true;
                } elseif ($assignment->assignmenttype == 'uploadpdf') {
                    $completed = $result->data2 == 'submitted' || $result->data2 == 'responded';
                } else {
                    $completed = $result->timemodified > 0;
                }
                $subout = '';
                if ($completed) {
                    $subout = $tickimg;
                    if ($assignment->timedue) {
                        $dayslate = $result->timemodified - $assignment->timedue;
                        if ($dayslate > DAYSECS) {
                            $dayslate = floor($dayslate/DAYSECS);
                            $subout .= ' <span class="late">('.$dayslate.' '.get_string('dayslate','gradereport_updfgrades').')</span>';
                        }
                    }
                } else {
                    if ($assignment->timedue) {
                        $dayslate = time() - $assignment->timedue;
                        if ($dayslate > DAYSECS) {
                            $dayslate = floor($dayslate/DAYSECS);
                            $subout = $crossimg;
                            $subout .= ' <span class="late">('.$dayslate.' '.get_string('dayslate','gradereport_updfgrades').')</span>';
                        } else {
                            $daystogo = floor(-$dayslate/DAYSECS);
                            if ($daystogo < 1) {
                                $subout = get_string('duenow','gradereport_updfgrades');
                            } else {
                                $subout = get_string('duein','gradereport_updfgrades',$daystogo);
                            }
                        }
                    }
                }

                $row[] = $subout;
                if ($result->grade < 0) {
                    $row[] = '-';
                } else {
                    if ($assignment->grade < 0) {
                        if (!empty($scales_array[-$assignment->grade])) {
                            $scale = $scales_array[-$assignment->grade];
                            $values = explode(',', $scale->scale);
                            $result->grade = (int)$result->grade;
                            if (!empty($values[$result->grade-1])) {
                                $row[] = s($values[$result->grade-1]);
                            } else {
                                $row[] = get_string('gradeerror','gradereport_updfgrades');
                            }
                        } else {
                            $row[] = get_string('gradeerror','gradereport_updfgrades');
                        }
                    } else {
                        $row[] = $result->grade;
                    }
                }
                $row[] = s($result->submissioncomment);
            } else {
                // No submission yet
                $subout = '';
                if ($assignment->timedue) {
                    $dayslate = time() - $assignment->timedue;
                    if ($dayslate > DAYSECS) {
                        $dayslate = floor($dayslate/DAYSECS);
                        $subout = $crossimg;
                        $subout .= ' <span class="late">('.$dayslate.' '.get_string('dayslate','gradereport_updfgrades').')</span>';
                    } else {
                        $daystogo = floor(-$dayslate/DAYSECS);
                        if ($daystogo < 1) {
                            $subout = get_string('duenow','gradereport_updfgrades');
                        } else {
                            $subout = get_string('duein','gradereport_updfgrades',$daystogo);
                        }
                    }
                }
                $row[] = $subout;
                $row[] = ''; // Grade
                $row[] = ''; // Feedback
            }

            $table->data[] = $row;
        }

        print_table($table);
        $itemsshown = true;
    }

    if (!$itemsshown) {
        print_string('noassignmentstoshow','gradereport_updfgrades');
    }
}
*/

print_footer($course);

?>
