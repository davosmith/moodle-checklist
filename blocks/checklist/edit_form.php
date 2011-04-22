<?php

class block_checklist_edit_form extends block_edit_form {
    protected function specific_definition($mform) {
        global $DB, $COURSE;

        $mform->addElement('header', 'configheader', get_string('blocksettings', 'block'));

        $options = array();
        $checklists = $DB->get_records('checklist', array('course'=>$COURSE->id));
        foreach ($checklists as $checklist) {
            $options[$checklist->id] = s($checklist->name);
        }
        $mform->addElement('select', 'config_checklistid', get_string('choosechecklist', 'block_checklist'), $options);

        $options = array(0 => get_string('allparticipants'));
        $groups = $DB->get_records('groups', array('courseid'=>$COURSE->id));
        foreach ($groups as $group) {
            $options[$group->id] = s($group->name);
        }
        $mform->addElement('select', 'config_groupid', get_string('choosegroup', 'block_checklist'), $options);
    }

    function set_data($defaults) {
        parent::set_data($defaults);
    }
}
