<?php

require_once(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');
require_once($CFG->dirroot.'/grade/export/lib.php');
require_once($CFG->dirroot.'/lib/excellib.class.php');

$courseid = required_param('id', PARAM_INT);                   // course id
$district = required_param('choosedistrict', PARAM_TEXT);
$checklistid = required_param('choosechecklist', PARAM_INT);

if (!$course = get_record('course', 'id', $courseid)) {
    print_error('nocourseid');
}

require_login($course->id);
$context = get_context_instance(CONTEXT_COURSE, $course->id);

require_capability('gradereport/checklist:view', $context);
$viewall = has_capability('gradereport/checklist:viewall', $context);
$viewdistrict = has_capability('gradereport/checklist:viewdistrict', $context);
if (!$viewall && !$viewdistrict) {
    error('You do not have permission to view this report');
}

if (!$viewall) {
    $sql = "SELECT ud.data AS district FROM {$CFG->prefix}user_info_data ud, {$CFG->prefix}user_info_field uf ";
    $sql .= "WHERE ud.fieldid = uf.id AND uf.shortname = 'district' AND ud.userid = {$USER->id}";
    $mydistrict = get_record_sql($sql);
    if ($district != $mydistrict->district) {
        print_error('wrongdistrict','gradereport_checklist');
    }
}

if (!$checklist = get_record('checklist','id', $checklistid)) {
    print_error('checklistnotfound','gradereport_checklist');
}

$strchecklistreport = get_string('checklistreport','gradereport_checklist');

$users = get_users_by_capability($context, 'mod/checklist:updateown', 'u.id, u.firstname, u.lastname, u.username', '', '', '', '', false);


if ($district != 'ALL' && $users) {
    $users = implode(',',array_keys($users));
    $sql = "SELECT u.id, u.firstname, u.lastname, u.username FROM ({$CFG->prefix}user u JOIN {$CFG->prefix}user_info_data ud ON u.id = ud.userid) JOIN {$CFG->prefix}user_info_field uf ON ud.fieldid = uf.id ";
    $sql .= "WHERE u.id IN ($users) AND uf.shortname = 'district' AND ud.data = '$district'";
    $users = get_records_sql($sql);
}
if (!$users) {
    print_error('nousers','gradereport_checklist');
}


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
function safe_write_string($myxls, $row, $col, $data, $element) {
    if (isset($data[$element])) {
        $myxls->write_string($row, $col, $data[$element]->data);
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
$myxls->write_string(0,$col++,'Region');
$myxls->write_string(0,$col++,'Disrict');
$myxls->write_string(0,$col++,'Last Name');
$myxls->write_string(0,$col++,'First Name');
$myxls->write_string(0,$col++,'Username');
$myxls->write_string(0,$col++,'Group(s)');
$myxls->write_string(0,$col++,'Position');
//$myxls->write_string(0,$col++,'Position_2');
$myxls->write_string(0,$col++,'Dealer Name');
$myxls->write_string(0,$col++,'Dealer #');

$headings = get_records_select('checklist_item',"checklist = {$checklist->id} AND itemoptional < 2",'position'); // 2 - optional / not optional (but not heading / disabled)
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
    safe_write_string($myxls, $row, $col++, $extra, 'region');
    safe_write_string($myxls, $row, $col++, $extra, 'district');
    $myxls->write_string($row, $col++, $user->lastname);
    $myxls->write_string($row, $col++, $user->firstname);
    $myxls->write_string($row, $col++, $user->username);
    $myxls->write_string($row, $col++, $groups_str);
    safe_write_string($myxls, $row, $col++, $extra, 'role'); // 'position'
    //safe_write_string($myxls, $row, $col++, $extra, '????'); //'position_2'
    safe_write_string($myxls, $row, $col++, $extra, 'dealername');
    safe_write_string($myxls, $row, $col++, $extra, 'dealernumber');

    $sql = "SELECT i.position, c.usertimestamp ";
    $sql .= "FROM {$CFG->prefix}checklist_item i LEFT JOIN ";
    $sql .= "(SELECT ch.item, ch.usertimestamp FROM {$CFG->prefix}checklist_check ch WHERE ch.userid = {$user->id}) c ";
    $sql .= "ON c.item = i.id ";
    $sql .= "WHERE i.checklist = {$checklist->id} AND i.itemoptional < 2 ";
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