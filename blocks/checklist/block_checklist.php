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

            // Add the groups selector to the footer.
            $this->content->footer = $this->get_groups_menu($cm);
            $showgroup = $this->get_selected_group($cm);

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

    function get_groups_menu($cm) {
        global $COURSE, $OUTPUT;

        if (!$groupmode = groups_get_activity_groupmode($cm)) {
            return '';
        }

        $context = get_context_instance(CONTEXT_MODULE, $cm->id);
        $aag = has_capability('moodle/site:accessallgroups', $context);

        if ($groupmode == VISIBLEGROUPS or $aag) {
            $seeall = true;
            $allowedgroups = groups_get_all_groups($cm->course, 0, $cm->groupingid); // any group in grouping
        } else {
            $seeall = false;
            $allowedgroups = groups_get_all_groups($cm->course, $USER->id, $cm->groupingid); // only assigned groups
        }

        $selected = $this->get_selected_group($cm, $allowedgroups, $seeall);

        $groupsmenu = array();
        if (empty($allowedgroups) || $seeall) {
            $groupsmenu[0] = get_string('allparticipants');
        }
        if ($allowedgroups) {
            foreach ($allowedgroups as $group) {
                $groupsmenu[$group->id] = format_string($group->name);
            }
        }

        $baseurl = new moodle_url('/course/view.php', array('id' => $COURSE->id));
        if (count($groupsmenu) <= 1) {
            return '';
        }

        $select = new single_select($baseurl, 'group', $groupsmenu, $selected, null, 'selectgroup');
        $out = $OUTPUT->render($select);
        return html_writer::tag('div', $out, array('class' => 'groupselector'));
    }

    function get_selected_group($cm, $allowedgroups = null, $seeall = false) {
        global $SESSION;

        if (!is_null($allowedgroups)) {
            if (!isset($SESSION->checklistgroup)) {
                $SESSION->checklistgroup = array();
            }
            $change = optional_param('group', -1, PARAM_INT);
            if ($change != -1) {
                $SESSION->checklistgroup[$cm->id] = $change;
            } else if (!isset($SESSION->checklistgroup[$cm->id])) {
                if (isset($this->config->groupid)) {
                    $SESSION->checklistgroup[$cm->id] = $this->config->groupid;
                }
            }
            $groupok = (($SESSION->checklistgroup[$cm->id] == 0) && $seeall);
            $groupok = $groupok || array_key_exists($SESSION->checklistgroup[$cm->id], $allowedgroups);
            if (!$groupok) {
                $SESSION->checklistgroup[$cm->id] = reset($allowedgroups);
            }
        }

        return $SESSION->checklistgroup[$cm->id];
    }
}
