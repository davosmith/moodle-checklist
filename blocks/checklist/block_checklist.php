<?php

class block_checklist extends block_list {
    function init() {
        $this->title = get_string('checklist','block_checklist');
        $this->version = 2010050300;
    }

    function instance_allow_multiple() {
        return true;
    }

    function specialization() {
        if (!empty($this->config->checklistid)) {
            $checklist = get_record('checklist','id',$this->config->checklistid);
            if ($checklist) {
                //$this->title = get_string('checklist', 'block_checklist').' - '.s($checklist->name);
                $this->title = s($checklist->name);
            }
        }
    }

    function get_content() {
        global $CFG, $USER;
        
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

        if (!$checklist = get_record('checklist','id',$this->config->checklistid)) {
            $this->content->items = array(get_string('nochecklist', 'block_checklist'));
            return $this->content;
        }

        if (!$cm = get_coursemodule_from_instance('checklist', $checklist->id, $checklist->course)) {
            $this->content->items = array('Error - course module not found');
            return $this->content;
        }
        $context = get_context_instance(CONTEXT_MODULE, $cm->id);

        if (has_capability('mod/checklist:viewreports', $context)) {
            $orderby = 'ORDER BY firstname ASC';
            $ausers = false;
            $showgroup = false;
            if (!empty($this->config->groupid)) {
                $showgroup = $this->config->groupid;
            }
            if ($users = get_users_by_capability($context, 'mod/checklist:updateown', 'u.id', '', '', '', $showgroup, '', false)) {
                $users = array_keys($users);
                $ausers = get_records_sql('SELECT u.id, u.firstname, u.lastname FROM '.$CFG->prefix.'user u WHERE u.id IN ('.implode(',',$users).') '.$orderby);
            }

            if ($ausers) {
                $this->content->items = array();
                foreach ($ausers as $auser) {
                    $link = '<a href="'.$CFG->wwwroot.'/mod/checklist/report.php?id='.$cm->id.'&amp;studentid='.$auser->id.'" >&nbsp;';
                    $this->content->items[] = $link.fullname($auser).checklist_class::print_user_progressbar($checklist->id, $auser->id, '50px', false, true).'</a>';
                }
            } else {
                $this->content->items = array(get_string('nousers','block_checklist'));
            }
            
        } else {
            $link = '<a href="'.$CFG->wwwroot.'/mod/checklist/view.php?id='.$cm->id.'" >&nbsp;';
            $this->content->items = array($link.checklist_class::print_user_progressbar($checklist->id, $USER->id, '150px', false, true).'</a>');
        }

        return $this->content;
    }

    function import_checklist_plugin() {
        global $CFG;
        
        $chk = get_record('modules', 'name', 'checklist');
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

?>