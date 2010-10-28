<?php

class restore_checklist_activity_structure_step extends restore_activity_structure_step {

    protected function define_structure() {

        $paths = array();
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('checklist', '/activity/checklist');
        $paths[] = new restore_path_element('checklist_item', '/activity/checklist/items/item');
        if ($userinfo) {
            $paths[] = new restore_path_element('checklist_check', '/activity/checklist/items/item/checks/check');
            $paths[] = new restore_path_element('checklist_comment', '/activity/checklist/items/item/comments/comment');
        }

        // Return the paths wrapped into standard activity structure
        return $this->prepare_activity_structure($paths);
    }

    protected function process_checklist($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $newitemid = $DB->insert_record('checklist', $data);
        $this->apply_activity_instance($newitemid);
    }

    protected function process_checklist_item($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->checklist = $this->get_new_parentid('checklist');
        $data->duetime = $this->apply_date_offset($data->duetime);
        if ($data->userid > 0) {
            $data->userid = $this->get_mappingid('user', $data->userid);
        }

        $newitemid = $DB->insert_record('checklist_item', $data);
        $this->set_mapping('checklist_item', $oldid, $newitemid);
    }

    protected function process_checklist_check($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->item = $this->get_new_parentid('checklist_item');
        if ($data->usertimestamp > 0) {
            $data->usertimestamp = $this->apply_date_offset($data->usertimestamp);
        }
        if ($data->teachertimestamp > 0) {
            $data->teachertimestamp = $this->apply_date_offset($data->teachertimestamp);
        }
        $data->userid = $this->get_mappingid('user', $data->userid);

        $newitemid = $DB->insert_record('checklist_check', $data);
        $this->set_mapping('checklist_check', $oldid, $newitemid);
    }

    protected function process_checklist_comment($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->itemid = $this->get_new_parentid('checklist_item');
        $data->userid = $this->get_mappingid('user', $data->userid);
        if ($data->commentby > 0) {
            $data->commentby = $this->get_mappingid('user', $data->commentby);
        }

        $newitemid = $DB->insert_record('checklist_comment', $data);
        $this->set_mapping('checklist_comment', $oldid, $newitemid);
    }

    protected function after_execute() {
        global $DB;

        // Add checklist related files, no need to match by itemname (just internally handled context)
        $this->add_related_files('mod_checklist', 'intro', null);
    }
}
