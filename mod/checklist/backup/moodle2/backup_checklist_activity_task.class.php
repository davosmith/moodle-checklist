<?php

require_once($CFG->dirroot . '/mod/checklist/backup/moodle2/backup_checklist_stepslib.php'); // Because it exists (must)
require_once($CFG->dirroot . '/mod/checklist/backup/moodle2/backup_checklist_settingslib.php'); // Because it exists (optional)

/**
 * forum backup task that provides all the settings and steps to perform one
 * complete backup of the activity
 */
class backup_checklist_activity_task extends backup_activity_task {

    /**
     * Define (add) particular settings this activity can have
     */
    protected function define_my_settings() {
        // No particular settings for this activity
    }

    /**
     * Define (add) particular steps this activity can have
     */
    protected function define_my_steps() {
        // Forum only has one structure step
        $this->add_step(new backup_checklist_activity_structure_step('checklist structure', 'checklist.xml'));
    }

    /**
     * Code the transformations to perform in the activity in
     * order to get transportable (encoded) links
     */
    static public function encode_content_links($content) {
        // I don't think there is anything needed here (but I could be wrong)


        global $CFG;

        $base = preg_quote($CFG->wwwroot,"/");

        // Link to the list of checklists
        $search="/(".$base."\/mod\/checklist\/index.php\?id\=)([0-9]+)/";
        $content= preg_replace($search, '$@CHECKLISTINDEX*$2@$', $content);

        // Link to checklist view by moduleid
        $search="/(".$base."\/mod\/checklist\/view.php\?id\=)([0-9]+)/";
        $content= preg_replace($search, '$@CHECKLISTVIEWBYID*$2@$', $content);

        // Link to checklist view by id
        $search="/(".$base."\/mod\/checklist\/view.php\?checklist\=)([0-9]+)/";
        $content= preg_replace($search, '$@CHECKLISTVIEWBYCHECKLIST*$2@$', $content);

        // Link to checklist report by moduleid
        $search="/(".$base."\/mod\/checklist\/report.php\?id\=)([0-9]+)/";
        $content= preg_replace($search, '$@CHECKLISTREPORTBYID*$2@$', $content);

        // Link to checklist report by id
        $search="/(".$base."\/mod\/checklist\/report.php\?checklist\=)([0-9]+)/";
        $content= preg_replace($search, '$@CHECKLISTREPORTBYCHECKLIST*$2@$', $content);

        // Link to checklist edit by moduleid
        $search="/(".$base."\/mod\/checklist\/edit.php\?id\=)([0-9]+)/";
        $content= preg_replace($search, '$@CHECKLISTEDITBYID*$2@$', $content);

        // Link to checklist edit by id
        $search="/(".$base."\/mod\/checklist\/edit.php\?checklist\=)([0-9]+)/";
        $content= preg_replace($search, '$@CHECKLISTEDITBYCHECKLIST*$2@$', $content);

        return $content;
    }
}
