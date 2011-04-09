<?php

// Note - to adjust the user columns included in the report, edit 'columns.php'

require_once(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');
require_once($CFG->dirroot.'/grade/export/lib.php');
require_once($CFG->dirroot.'/lib/excellib.class.php');

$courseid = required_param('id', PARAM_INT);                   // course id
$district = optional_param('choosedistrict', false, PARAM_TEXT);
$checklistid = required_param('choosechecklist', PARAM_INT);

if (!$course = get_record('course', 'id', $courseid)) {
    print_error('nocourseid');
}

require_login($course->id);
$context = get_context_instance(CONTEXT_COURSE, $course->id);

require_capability('gradeexport/checklist:view', $context);
$viewall = has_capability('gradeexport/checklist:viewall', $context);
$viewdistrict = has_capability('gradeexport/checklist:viewdistrict', $context);
if (!$viewall && (!$viewdistrict || !$district)) {
    error('You do not have permission to view this report');
}

if (!$viewall) {
    $sql = "SELECT ud.data AS district FROM {$CFG->prefix}user_info_data ud, {$CFG->prefix}user_info_field uf ";
    $sql .= "WHERE ud.fieldid = uf.id AND uf.shortname = 'district' AND ud.userid = {$USER->id}";
    $mydistrict = get_record_sql($sql);
    if ($district != $mydistrict->district) {
        print_error('wrongdistrict','gradeexport_checklist');
    }
}

if (!$checklist = get_record('checklist','id', $checklistid)) {
    print_error('checklistnotfound','gradeexport_checklist');
}

$strchecklistreport = get_string('checklistreport','gradeexport_checklist');

$users = get_users_by_capability($context, 'mod/checklist:updateown', 'u.*', '', '', '', '', false);


if ($district && $district != 'ALL' && $users) {
    $users = implode(',',array_keys($users));
    $sql = "SELECT u.* FROM ({$CFG->prefix}user u JOIN {$CFG->prefix}user_info_data ud ON u.id = ud.userid) JOIN {$CFG->prefix}user_info_field uf ON ud.fieldid = uf.id ";
    $sql .= "WHERE u.id IN ($users) AND uf.shortname = 'district' AND ud.data = '$district'";
    $users = get_records_sql($sql);
}
if (!$users) {
    print_error('nousers','gradeexport_checklist');
}

require_once(dirname(__FILE__).'/columns.php');

// Useful for debugging
/*class FakeMoodleExcelWorkbook {
    function FakeMoodleExcelWorkbook($ignore) {}
    function send($ignore) {}
    function write_string($row, $col, $data) { echo "($row, $col) = $data<br/>"; }
    function write_number($row, $col, $data) { echo "($row, $col) = $data<br/>"; }
    function add_worksheet($ignore) { return new FakeMoodleExcelWorkbook($ignore); }
    function close() {}
    }*/


// Only write the data if it exists
function safe_write_string($myxls, $row, $col, $user, $extra, $element) {
    if (isset($user[$element])) {
        $myxls->write_string($row, $col, $user[$element]);
    } elseif (isset($extra[$element])) {
        $myxls->write_string($row, $col, $extra[$element]->data);
    }
}

/// Calculate file name
$downloadfilename = clean_filename("{$course->shortname} {$checklist->name} $strchecklistreport.xls");
/// Creating a workbook
$workbook = new MoodleExcelWorkbook("-");
/// Sending HTTP headers
$workbook->send($downloadfilename);
/// Adding the worksheet
$wsname = str_replace(array('\\','/','?','*','[',']',' ',':','\''), '', $checklist->name);
$wsname = substr($wsname, 0, 31);
$myxls =& $workbook->add_worksheet($wsname);

/// Print names of all the fields
$col = 0;
foreach ($checklist_report_user_columns as $field => $headerstr) {
    $myxls->write_string(0,$col++,$headerstr);
}

$headings = get_records_select('checklist_item',"checklist = {$checklist->id} AND userid = 0 AND itemoptional < 2 AND hidden = 0",'position'); // 2 - optional / not optional (but not heading)
if ($headings) {
    foreach($headings as $heading) {
        $myxls->write_string(0, $col++, strip_tags($heading->displaytext));
    }
}

// Go through each of the users
$row = 1;
foreach ($users as $user) {
    $sql = "SELECT uf.shortname, ud.data ";
    $sql .= "FROM {$CFG->prefix}user_info_data ud JOIN {$CFG->prefix}user_info_field uf ON uf.id = ud.fieldid ";
    $sql .= "WHERE ud.userid = {$user->id}";
    $extra = get_records_sql($sql);
    $groups = groups_get_all_groups($course->id, $user->id, 0, 'g.id, g.name');
    if ($groups) {
        $groups = array_values($groups);
        $first = reset($groups);
        $groups_str = $first->name;
        while ($next = next($groups)) {
            $groups_str .= ', '.$next->name;
        }
    } else {
        $groups_str = '';
    }
    $col = 0;

    $userarray = (array)$user;
    foreach ($checklist_report_user_columns as $field => $header) {
        if ($field == '_groups') {
            $myxls->write_string($row, $col++, $groups_str);

        } elseif ($field == '_enroldate') {
            $enrolement = get_records_select('role_assignments', "contextid = $context->id AND userid = $user->id", 'timestart ASC', 'id, timestart', 0, 1);
            $datestr = '';
            if ($enrolement) {
                $enrolement = reset($enrolement);
                $datestr = userdate($enrolement->timestart, get_string('strftimedate'));
            }
            $myxls->write_string($row, $col++, $datestr);

        } elseif ($field == '_startdate') {
            $firstview = get_records_select('log', "userid = $user->id AND course = $course->id AND module = 'course' AND action = 'view'", 'time ASC', 'id, time', 0, 1);
            $datestr = '';
            if ($firstview) {
                $firstview = reset($firstview);
                $datestr = userdate($firstview->time, get_string('strftimedate'));
            }
            $myxls->write_string($row, $col++, $datestr);

        } else {
            safe_write_string($myxls, $row, $col++, $userarray, $extra, $field);
        }
    }

    $sql = "SELECT i.position, c.usertimestamp ";
    $sql .= "FROM {$CFG->prefix}checklist_item i LEFT JOIN ";
    $sql .= "(SELECT ch.item, ch.usertimestamp FROM {$CFG->prefix}checklist_check ch WHERE ch.userid = {$user->id}) c ";
    $sql .= "ON c.item = i.id ";
    $sql .= "WHERE i.checklist = {$checklist->id} AND userid = 0 AND i.itemoptional < 2 AND i.hidden = 0 ";
    $sql .= 'ORDER BY i.position';
    $checks = get_records_sql($sql);

    foreach ($checks as $check) {
        if ($check->usertimestamp > 0) {
            $myxls->write_number($row, $col, 1);
        }
        $col++;
    }
    $row++;
}

$workbook->close();
exit;

?>
