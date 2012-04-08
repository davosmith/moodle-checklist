<?php

/*
//TDMU - all
*/

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');

$id = required_param('id', PARAM_INT); // course module id

$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', 30, PARAM_INT);
$sortby = optional_param('sortby', '', PARAM_TEXT);
$showoptional = optional_param('showoptional', '', PARAM_TEXT);

if (! $cm = get_coursemodule_from_id('checklist', $id)) {
    error('Course Module ID was incorrect');
}

if (! $course = get_record('course', 'id', $cm->course)) {
    error('Course is misconfigured');
}

if (! $checklist = get_record('checklist', 'id', $cm->instance)) {
    error('Course module is incorrect');
}

require_login($course, true, $cm);

$context = get_context_instance(CONTEXT_MODULE, $cm->id);
if (!has_capability('mod/checklist:edit', $context)) {
    error('You do not have permission to export items from this checklist');
}

$items = get_records_select('checklist_item', "checklist = {$checklist->id} AND userid = 0", 'position');
if (!$items) {
    error(get_string('noitems', 'checklist'));
}

if (strpos($CFG->wwwroot, 'https://') === 0) { //https sites - watch out for IE! KB812935 and KB316431
    @header('Cache-Control: max-age=10');
    @header('Expires: '. gmdate('D, d M Y H:i:s', 0) .' GMT');
    @header('Pragma: ');
} else { //normal http - prevent caching at all cost
    @header('Cache-Control: private, must-revalidate, pre-check=0, post-check=0, max-age=0');
    @header('Expires: '. gmdate('D, d M Y H:i:s', 0) .' GMT');
    @header('Pragma: no-cache');
}

$strchecklist = get_string('checklist', 'checklist');

//header("Content-Type: application/download\n");
header("Content-Type: application/doc");
$downloadfilename = clean_filename("{$course->shortname} $strchecklist {$checklist->name}");
header("Content-Disposition: attachment; filename=\"{$downloadfilename}.doc\"");

echo '<html lang="uk" xml:lang="uk" dir="ltr" xmlns="http://www.w3.org/1999/xhtml">';
echo '<head><meta content="text/html; charset=utf-8" http-equiv="Content-Type"></head>';
echo '<body>';

	switch ($sortby) {
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

$activegroup = groups_get_activity_group($cm, true);
//var_dump($activegroup); //debug
$ausers = false;
if ($users = get_users_by_capability($context, 'mod/checklist:updateown', 'u.id', $orderby, '', '', $activegroup, '', false)) {
//var_dump($activegroup); //debug
    $users = array_keys($users);
//var_dump($users); //debug
    $users = array_slice($users, $page*$perpage, $perpage);
//var_dump($users); //debug
    $ausers = get_records_sql('SELECT u.id, u.firstname, u.lastname FROM '.$CFG->prefix.'user u WHERE u.id IN ('.implode(',',$users).') ORDER BY '.$orderby);
}

//var_dump($ausers); //debug

//Pprepare table data
$nameheading = get_string('classbookoredrnum', 'checklist');
$table = new stdClass;
$table->head = array($nameheading);
$table->level = array(-1);
$table->size = array('20px');
$table->skip = array(false);

$nameheading = get_string('fullname');
$table->head[] = s($nameheading);
$table->level[] = -1;
$table->size[] = '200px';
$table->skip[] = false;

foreach ($items as $item) {
	if ($item->hidden) {
    	continue;
    }
    $nameheading = get_string('classbookcheckcoltitle', 'checklist');
	$table->head[] = s($nameheading);
    $table->level[] = ($item->indent < 3) ? $item->indent : 2;
    $table->size[] = '150px';
    $table->skip[] = (!$showoptional) && ($item->itemoptional == CHECKLIST_OPTIONAL_YES);//?
}

$table->data = array();
if ($ausers) {
	$rcount=0;
	foreach ($ausers as $auser) {
		$rcount++;
        $row = array();

        $row[] =$rcount;
		$row[] = fullname($auser);
		//TDMU:origin
        //$sql = 'SELECT i.id, i.itemoptional, i.hidden, c.usertimestamp, c.teachermark FROM '.$CFG->prefix.'checklist_item i LEFT JOIN '.$CFG->prefix.'checklist_check c ';
		//TDMU:select with teacher ID and teacher date
		$sql = 'SELECT i.id, i.itemoptional, i.hidden, c.usertimestamp, c.teachermark, c.teacherid, c.teachertimestamp FROM '.$CFG->prefix.'checklist_item i LEFT JOIN '.$CFG->prefix.'checklist_check c ';					
        $sql .= 'ON (i.id = c.item AND c.userid = '.$auser->id.') WHERE i.checklist = '.$checklist->id.' AND i.userid=0 ORDER BY i.position';
        $checks = get_records_sql($sql);

        foreach ($checks as $check) {
        	if ($check->hidden) {
            	continue;
            }
            if ($check->itemoptional == CHECKLIST_OPTIONAL_HEADING) {
            	$row[] = array(false, false, true, 0, 0, 0);//TDMU: added last paramether
            } else {
            	if ($check->usertimestamp > 0) {
                	$row[] = array($check->teachermark,true, false, $auser->id, $check->id, 0); //TDMU: added last paramether
                } else {
                	$row[] = array($check->teachermark,false, false, $auser->id, $check->id, $check->teachertimestamp); //TDMU: added last paramether
                }
        	}
    	}
    $table->data[] = $row;
	}
}
//TDMU: 1-st level of footing - level of the mastering info
$namefooting = '-';
$table->levfooter = array($namefooting);

$namefooting = get_string('classbooklevelmasttitle', 'checklist');
$table->levfooter[] = s($namefooting);

foreach ($items as $item) {
	if ($item->hidden) {
		continue;
    }
    $table->levfooter[] = s('___');//TODO:maybe will nedd add one field to DB to store it!
}
//TDMU: 2-st level of footing - names of checklis items
$namefooting = '-';
$table->footer = array($namefooting);

$namefooting = get_string('classbookprakskilltitle', 'checklist');
$table->footer[] = s($namefooting);

foreach ($items as $item) {
	if ($item->hidden) {
    	continue;
    }
    $table->footer[] = s($item->displaytext);
}
//var_dump($table); //debug

//Output table
$output = '';
$output .= '<table id="my" summary="'.get_string('reporttablesummary','checklist').'"';
$output .= ' cellpadding="5" cellspacing="1" class="generaltable boxaligncenter checklistreport">';

$showteachermark = !($checklist->teacheredit == CHECKLIST_MARKING_STUDENT);
$showstudentmark = !($checklist->teacheredit == CHECKLIST_MARKING_TEACHER);
$teachermarklocked = $checklist->lockteachermarks && !has_capability('mod/checklist:updatelocked', $context);

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
        if ($key < 2) { //for item numer and student name align must be left
        	$output .= '<td style=" text-align: left; width: '.$table->size[$key].';" class="cell c'.$key.'">'.$item.'</td>';
        } else {
        	$size = $table->size[$key];
            $img = '&nbsp;';
            $cellclass = 'cell c'.$key.' level'.$table->level[$key];
            //list($teachermark, $studentmark, $heading, $userid, $checkid) = $item;
			list($teachermark, $studentmark, $heading, $userid, $checkid, $teachertimestamp) = $item;//TDMU
            if ($heading) {
            	$output .= '<td style=" text-align: center; width: '.$size.';" class="cell c'.$key.' reportheading">&nbsp;</td>';
            } else {
            	if ($showteachermark) {
                	if ($teachermark == CHECKLIST_TEACHERMARK_YES) {
                    	$cellclass .= '-checked';
						$img = '<b>'.get_string('classbookpassed', 'checklist').'</b>';//Passed!
						$img .= '<div class="itemteacherdate">'.userdate($teachertimestamp, get_string('strftimedatetimeshort')).'</div>';//TDMU
                    } elseif ($teachermark == CHECKLIST_TEACHERMARK_NO) {
                    	$cellclass .= '-unchecked';
						$img = '<b>'.get_string('classbookfailed', 'checklist').'</b>';//Failed!
						$img .= '<div class="itemteacherdate">'.userdate($teachertimestamp, get_string('strftimedatetimeshort')).'</div>';//TDMU
                    } else {
                    	//$img = $teacherimg[CHECKLIST_TEACHERMARK_UNDECIDED];
						$img = ' ';
                    }
                }
                if ($showstudentmark) {
                	if ($studentmark) {
                    	if (!$showteachermark) {
                        	$cellclass .= '-checked';
                        }
						$img .= '<b>'.get_string('classbookdoned', 'checklist').'</b>';//Doned!
                    }
                }
                if ($key == $lastkey) {
                	$cellclass .= ' lastcol';
                }
				//output students marks in table row are there:
            	$output .= '<td style=" text-align: center; width: '.$size.';" class="'.$cellclass.'">'.$img.'</td>';
        	}
    	}
    }
	$output .= '</tr>';
}

//level mastering text
$output .= '<tr>';
$keys = array_keys($table->levfooter);
$lastkey = end($keys);
foreach ($table->levfooter as $key => $levfooting) {
	if ($table->skip[$key]) {
    	continue;
    }
    $size = $table->size[$key];
    $levelclass = ' head'.$table->level[$key];
    if ($key == $lastkey) {
    	$levelclass .= ' lastcol';
    }
	if ($key < 2) {
		$textalign = ' left';
	} else {
		$textalign = ' center';
	}
    $output .= '<td style=" text-align:'.$textalign.'; width:'.$size.'" class="header c'.$key.$levelclass.'">';
    $output .= $levfooting.'</td>';
}
$output .= '</tr>';

//footer text - list of the practice skills names
$output .= '<tr>';
$keys = array_keys($table->footer);
$lastkey = end($keys);
foreach ($table->footer as $key => $footing) {
	if ($table->skip[$key]) {
    	continue;
    }
 	$size = $table->size[$key];
    $levelclass = ' head'.$table->level[$key];
    if ($key == $lastkey) {
    	$levelclass .= ' lastcol';
    }
	if ($key < 2) {
		$textalign = ' left';
	} else {
		$textalign = ' center';
	}
    $output .= '<td style=" text-align:'.$textalign.'; width:'.$size.'" class="header c'.$key.$levelclass.'">';
    $output .= $footing.'</td>';
}
$output .= '</tr>';
$output .= '</table>';

echo $output;
echo '</body>';
echo '</html>';

exit;