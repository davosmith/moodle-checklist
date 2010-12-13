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

$users = get_users_by_capability($context, 'mod/checklist:updateown', 'u.id, u.firstname, u.lastname', '', '', '', '', false);


if ($district != 'ALL' && $users) {
    $users = implode(',',array_keys($users));
    $sql = "SELECT u.id, u.firstname, u.lastname FROM ({$CFG->prefix}user u JOIN {$CFG->prefix}user_info_data ud ON u.id = ud.userid) JOIN {$CFG->prefix}user_info_field uf ON ud.fieldid = uf.id ";
    $sql .= "WHERE u.id IN ($users) AND uf.shortname = 'district' AND ud.data = '$district'";
    $users = get_records_sql($sql);
}
if (!$users) {
    print_error('nousers','gradereport_checklist');
}

/*
// Useful for debugging
class FakeMoodleExcelWorkbook {
    function FakeMoodleExcelWorkbook($ignore) {}
    function send($ignore) {}
    function write_string($row, $col, $data) { echo "($row, $col) = $data<br/>"; }
    function write_number($row, $col, $data) { echo "($row, $col) = $data<br/>"; }
    function add_worksheet($ignore) { return new FakeMoodleExcelWorkbook($ignore); }
    function close() {}
    }
*/

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
$myxls =& $workbook->add_worksheet($checklist->name);

/// Print names of all the fields
$myxls->write_string(0,0,'Region');
$myxls->write_string(0,1,'Disrict');
$myxls->write_string(0,2,'Last Name');
$myxls->write_string(0,3,'First Name');
$myxls->write_string(0,4,'Position');
$myxls->write_string(0,5,'Position_2');
$myxls->write_string(0,6,'Dealer Name');
$myxls->write_string(0,7,'Dealer #');
$pos=8;

$columns = get_records_select('checklist_item',"checklist = {$checklist->id} AND itemoptional < 2",'position'); // 2 - optional / not optional (but not heading / disabled)
if ($columns) {
    foreach($columns as $col) {
        $myxls->write_string(0, $pos++, strip_tags($col->displaytext));
    }
}

// Go through each of the users
$row = 1;
foreach ($users as $user) {
    $sql = "SELECT uf.shortname, ud.data ";
    $sql .= "FROM {$CFG->prefix}user_info_data ud JOIN {$CFG->prefix}user_info_field uf ON uf.id = ud.fieldid ";
    $sql .= "WHERE ud.userid = {$user->id}";
    $extra = get_records_sql($sql);
    safe_write_string($myxls, $row, 0, $extra, 'region');
    safe_write_string($myxls, $row, 1, $extra, 'district');
    $myxls->write_string($row,2,$user->lastname);
    $myxls->write_string($row,3,$user->firstname);
    //safe_write_string($myxls, $row, 4, $extra, '????'); //'position'
    safe_write_string($myxls, $row, 5, $extra, 'role'); // 'position_2'
    safe_write_string($myxls, $row, 6, $extra, 'dealername');
    safe_write_string($myxls, $row, 7, $extra, 'dealernumber');

    $sql = "SELECT i.position, c.usertimestamp ";
    $sql .= "FROM {$CFG->prefix}checklist_item i LEFT JOIN ";
    $sql .= "(SELECT ch.item, ch.usertimestamp FROM {$CFG->prefix}checklist_check ch WHERE ch.userid = {$user->id}) c ";
    $sql .= "ON c.item = i.id ";
    $sql .= "WHERE i.checklist = {$checklist->id} AND i.itemoptional < 2 ";
    $sql .= 'ORDER BY i.position';
    $checks = get_records_sql($sql);

    $col = 8;
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