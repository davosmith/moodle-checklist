<?php

class block_checklist extends block_list {
    function init() {
        $this->title = get_string('checklist','block_checklist');
    }

    function instance_allow_multiple() {
        return true;
    }

    function has_config() {
        return false;
    }

    function instance_allow_config() {
        return true;
    }

    function specialization() {
        global $DB;

        if (!empty($this->config->checklistid)) {
            $checklist = $DB->get_record('checklist', array('id'=>$this->config->checklistid));
            if ($checklist) {
                $this->title = s($checklist->name);
            }
        }
    }

    function get_content() {
        global $CFG, $USER, $DB, $COURSE;

        if ($this->content !== NULL) {
            return $this->content;
        }

        $this->content = new stdClass;
        $this->content->footer = '';
        $this->content->icons = array();

        if (!$this->import_checklist_plugin()) {
            $this->content->items = array(get_string('nochecklistplugin','block_checklist'));
            return $this->content;
        }

        if (empty($this->config->checklistid)) {
            $this->content->items = array(get_string('nochecklist','block_checklist'));
            return $this->content;
        }

        if (!$checklist = $DB->get_record('checklist',array('id'=>$this->config->checklistid))) {
            $this->content->items = array(get_string('nochecklist', 'block_checklist'));
            return $this->content;
        }

        if (!$cm = get_coursemodule_from_instance('checklist', $checklist->id, $checklist->course)) {
            $this->content->items = array('Error - course module not found');
            return $this->content;
        }
        if ($CFG->version < 2011120100) {
            $context = get_context_instance(CONTEXT_MODULE, $cm->id);
        } else {
            $context = context_module::instance($cm->id);
        }

        $viewallreports = has_capability('mod/checklist:viewreports', $context);
        $viewmenteereports = has_capability('mod/checklist:viewmenteereports', $context);

        if ($viewallreports || $viewmenteereports) {
            $orderby = 'ORDER BY firstname ASC';
            $ausers = false;
            $showgroup = false;
            if (!empty($this->config->groupid)) {
                $showgroup = $this->config->groupid;
            }
            $separate = $COURSE->groupmode == SEPARATEGROUPS;
            if ($separate && !has_capability('moodle/site:accessallgroups', $context)) {
                // Teacher can only see own groups
                $groups = groups_get_all_groups($COURSE->id, $USER->id, 0, 'g.id, g.name');
                if (!$groups) {
                    $groups = array();
                }
                if (!$showgroup || !array_key_exists($showgroup, $groups)) {
                    // Showgroup not set OR teacher not member of showgroup
                    $showgroup = array_keys($groups); // Show all students for group(s) teacher is member of
                }
            }

            if ($users = get_users_by_capability($context, 'mod/checklist:updateown', 'u.id', '', '', '', $showgroup, '', false)) {
                $users = array_keys($users);
                if (!$viewallreports) { // can only see reports for their mentees
                    $users = checklist_class::filter_mentee_users($users);
                }
                if (!empty($users)) {
                    $ausers = $DB->get_records_sql('SELECT u.id, u.firstname, u.lastname FROM {user} u WHERE u.id IN ('.implode(',',$users).') '.$orderby);
                }
            }

            if ($ausers) {
                $this->content->items = array();
                $reporturl = new moodle_url('/mod/checklist/report.php', array('id'=>$cm->id));
                foreach ($ausers as $auser) {
                    $link = '<a href="'.$reporturl->out(true, array('studentid'=>$auser->id)).'" >&nbsp;';
                    $this->content->items[] = $link.fullname($auser).checklist_class::print_user_progressbar($checklist->id, $auser->id, '50px', false, true).'</a>';
                }
            } else {
                $this->content->items = array(get_string('nousers','block_checklist'));
            }

        } else {
            $viewurl = new moodle_url('/mod/checklist/view.php', array('id'=>$cm->id));
            $link = '<a href="'.$viewurl.'" >&nbsp;';
            $this->content->items = array($link.checklist_class::print_user_progressbar($checklist->id, $USER->id, '150px', false, true).'</a>');
        }

        return $this->content;
    }

    function import_checklist_plugin() {
        global $CFG, $DB;

        $chk = $DB->get_record('modules', array('name'=>'checklist'));
        if (!$chk) {
            return false;
        }

        if ($chk->version < 2010041800) {
            return false;
        }

        if (!file_exists($CFG->dirroot.'/mod/checklist/locallib.php')) {
            return false;
        }

        require_once($CFG->dirroot.'/mod/checklist/locallib.php');
        return true;
    }
}

