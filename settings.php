<?php

//require_once($CFG->dirroot.'/mod/checklist/lib.php');

/*$settings->add(new admin_setting_configcheckbox('checklist_module_links', get_string('allowmodulelinks', 'checklist'),
  get_string('configallowmodulelinks', 'checklist'), 1));*/
                   
$settings->add(new admin_setting_configcheckbox('checklist_auto_update', get_string('checklistautoupdate', 'checklist'),
                   get_string('configchecklistautoupdate', 'checklist'), 0));

?>
