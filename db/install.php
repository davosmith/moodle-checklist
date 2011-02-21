<?php

// This file replaces:
//   * STATEMENTS section in db/install.xml
//   * lib.php/modulename_install() post installation hook
//   * partially defaults.php

function xmldb_checklist_install() {
    global $DB;

/// Install logging support
/*    update_log_display_entry('checklist', 'view', 'checklist', 'name');
    update_log_display_entry('checklist', 'edit', 'checklist', 'name');
    update_log_display_entry('checklist', 'update checks', 'checklist', 'name');
    update_log_display_entry('checklist', 'complete', 'checklist', 'name');
    update_log_display_entry('checklist', 'report', 'checklist', 'name');*/

}
