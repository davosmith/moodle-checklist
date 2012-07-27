<?php 

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {


$options = array();


// user can add description and documents
$options[0] = 0;
$options[1] = 1;
if (isset($CFG->checklist_description_display)){
    $settings->add(new admin_setting_configselect('checklist_description_display', get_string('checklist_description', 'checklist'),
                   get_string('config_description', 'checklist'), $CFG->checklist_description_display, $options));
}
else{
    $settings->add(new admin_setting_configselect('checklist_description_display', get_string('checklist_description', 'checklist'),
                   get_string('config_description', 'checklist'), 1, $options));
}

// user can import or export outcomes files as item list
unset($options);
$options[0] = 0;
$options[1] = 1;
if (isset($CFG->checklist_outcomes_input)){
    $settings->add(new admin_setting_configselect('checklist_outcomes_input', get_string('outcomes_input', 'checklist'),
                   get_string('config_outcomes_input', 'checklist'), $CFG->checklist_outcomes_input, $options));
}
else{
    $settings->add(new admin_setting_configselect('checklist_outcomes_input', get_string('outcomes_input', 'checklist'),
                   get_string('config_outcomes_input', 'checklist'), 1, $options));
}
}
?>