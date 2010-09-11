<?php  //$Id: settings.php,v 1.1.2.3 2008/01/24 20:29:36 skodak Exp $

//require_once($CFG->dirroot.'/mod/checklist/lib.php');

$settings->add(new admin_setting_configcheckbox('checklist_module_links', get_string('allowmodulelinks', 'checklist'),
                   get_string('configallowmodulelinks', 'checklist'), 1));

?>
