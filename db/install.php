<?php

// This file replaces:
//   * STATEMENTS section in db/install.xml
//   * lib.php/modulename_install() post installation hook
//   * partially defaults.php

function xmldb_checklist_install() {
    global $DB;

/// Install logging support
    update_log_display_entry('checklist', 'view', 'checklist', 'name');
    update_log_display_entry('checklist', 'add', 'checklist', 'name');
    update_log_display_entry('checklist', 'update', 'checklist', 'name');

}
