<?php

/**
 * Define all the backup steps that will be used by the backup_forum_activity_task
 */

/**
 * Define the complete checklist structure for backup, with file and id annotations
 */
class backup_checklist_activity_structure_step extends backup_activity_structure_step {

    protected function define_structure() {

        // To know if we are including userinfo
        $userinfo = $this->get_setting_value('userinfo');

        // Define each element separated

        $checklist = new backup_nested_element('checklist', array('id'), array(
            'name', 'intro', 'introformat', 'timecreated', 'timemodified', 'useritemsallowed',
            'teacheredit', 'theme', 'duedatesoncalendar', 'teachercomments'));

        $items = new backup_nested_element('items');

        $item = new backup_nested_element('item', array('id'), array(
            'userid', 'displaytext', 'position', 'indent', 'itemoptional', 'duetime'));

        $checks = new backup_nested_element('checks');
        
        $check = new backup_nested_element('check', array('id'), array(
            'userid', 'usertimestamp', 'teachermark', 'teachertimestamp'));

        $comments = new backup_nested_element('comments');

        $comment = new backup_nested_element('comment', array('id'), array(
            'userid', 'commentby', 'text'));
                                           
        // Build the tree
        $checklist->add_child($items);
        $items->add_child($item);

        $item->add_child($checks);
        $checks->add_child($check);

        $item->add_child($comments);
        $comments->add_child($comment);

        // Define sources
        $checklist->set_source_table('checklist', array('id' => backup::VAR_ACTIVITYID));

        if ($userinfo) {
            $item->set_source_table('checklist_item', array('checklist' => backup::VAR_PARENTID));
            $check->set_source_table('checklist_check', array('item' => backup::VAR_PARENTID));
            $comment->set_source_table('checklist_comment', array('itemid' => backup::VAR_PARENTID));
        } else {
            $item->set_source_sql('SELECT * FROM {checklist_item} WHERE userid = 0 AND checklist = ?', array(backup::VAR_PARENTID));
        }

        // Define id annotations
        $item->annotate_ids('userid', 'userid');
        $check->annotate_ids('userid', 'userid');
        $comment->annotate_ids('userid', 'userid');
        $comment->annotate_ids('userid', 'commentby');
        

        // Define file annotations

        $checklist->annotate_files(array('forum_intro'), null); // This file area hasn't itemid

        // Return the root element (forum), wrapped into standard activity structure
        return $this->prepare_activity_structure($checklist);
    }

}
