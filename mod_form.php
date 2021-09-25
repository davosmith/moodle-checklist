<?php
// This file is part of the Checklist plugin for Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Activity instance editing form.
 * @copyright Davo Smith <moodle@davosmith.co.uk>
 * @package mod_checklist
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot.'/course/moodleform_mod.php');

/**
 * Class mod_checklist_mod_form
 */
class mod_checklist_mod_form extends moodleform_mod {

    /**
     * Define form elements
     * @throws coding_exception
     * @throws dml_exception
     */
    public function definition() {

        global $CFG;
        $mform = $this->_form;

        // Adding the "general" fieldset, where all the common settings are showed.
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Adding the standard "name" field.
        $mform->addElement('text', 'name', get_string('modulename', 'checklist'), array('size' => '64'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        if ($CFG->branch < 29) {
            $this->add_intro_editor(true, get_string('checklistintro', 'checklist'));
        } else {
            $this->standard_intro_elements(get_string('checklistintro', 'checklist'));
        }

        $mform->addElement('header', 'checklistsettings', get_string('checklistsettings', 'checklist'));
        $mform->setExpanded('checklistsettings', true);

        $ynoptions = array(0 => get_string('no'), 1 => get_string('yes'));
        $mform->addElement('select', 'useritemsallowed', get_string('useritemsallowed', 'checklist'), $ynoptions);
        $mform->addElement('select', 'studentcomments', get_string('studentcomments', 'checklist'), $ynoptions);

        $teditoptions = array(
            CHECKLIST_MARKING_STUDENT => get_string('teachernoteditcheck', 'checklist'),
            CHECKLIST_MARKING_TEACHER => get_string('teacheroverwritecheck', 'checklist'),
            CHECKLIST_MARKING_BOTH => get_string('teacheralongsidecheck', 'checklist')
        );
        $mform->addElement('select', 'teacheredit', get_string('teacheredit', 'checklist'), $teditoptions);

        $mform->addElement('select', 'duedatesoncalendar', get_string('duedatesoncalendar', 'checklist'), $ynoptions);
        $mform->setDefault('duedatesoncalendar', 0);

        $mform->addElement('select', 'teachercomments', get_string('teachercomments', 'checklist'), $ynoptions);
        $mform->setDefault('teachercomments', 1);

        $mform->addElement('text', 'maxgrade', get_string('maximumgrade'), array('size' => '10'));
        $mform->setDefault('maxgrade', 100);
        $mform->setType('maxgrade', PARAM_INT);

        $emailrecipients = array(
            CHECKLIST_EMAIL_NO => get_string('no'),
            CHECKLIST_EMAIL_STUDENT => get_string('teachernoteditcheck', 'checklist'),
            CHECKLIST_EMAIL_TEACHER => get_string('teacheroverwritecheck', 'checklist'),
            CHECKLIST_EMAIL_BOTH => get_string('teacheralongsidecheck', 'checklist')
        );
        $mform->addElement('select', 'emailoncomplete', get_string('emailoncomplete', 'checklist'), $emailrecipients);
        $mform->setDefault('emailoncomplete', 0);
        $mform->addHelpButton('emailoncomplete', 'emailoncomplete', 'checklist');

        $autopopulateoptions = array(
            CHECKLIST_AUTOPOPULATE_NO => get_string('no'),
            CHECKLIST_AUTOPOPULATE_SECTION => get_string('importfromsection', 'checklist'),
            CHECKLIST_AUTOPOPULATE_COURSE => get_string('importfromcourse', 'checklist')
        );
        $mform->addElement('select', 'autopopulate', get_string('autopopulate', 'checklist'), $autopopulateoptions);
        $mform->setDefault('autopopulate', 0);
        $mform->addHelpButton('autopopulate', 'autopopulate', 'checklist');

        $checkdisable = true;
        $str = 'autoupdate';
        if (get_config('mod_checklist', 'linkcourses')) {
            $str = 'autoupdate2';
            $checkdisable = false;
        }

        $autoupdateoptions = array(
            CHECKLIST_AUTOUPDATE_NO => get_string('no'),
            CHECKLIST_AUTOUPDATE_YES => get_string('yesnooverride', 'checklist'),
            CHECKLIST_AUTOUPDATE_YES_OVERRIDE => get_string('yesoverride', 'checklist')
        );
        $mform->addElement('select', 'autoupdate', get_string($str, 'checklist'), $autoupdateoptions);
        $mform->setDefault('autoupdate', 1);
        $mform->addHelpButton('autoupdate', $str, 'checklist');
        $mform->addElement('static', 'autoupdatenote', '', get_string('autoupdatenote', 'checklist'));
        if ($checkdisable) {
            $mform->disabledIf('autoupdate', 'autopopulate', 'eq', 0);
        }

        $mform->addElement('selectyesno', 'lockteachermarks', get_string('lockteachermarks', 'checklist'));
        $mform->setDefault('lockteachermarks', 0);
        $mform->addHelpButton('lockteachermarks', 'lockteachermarks', 'checklist');

        // Add standard elements, common to all modules.
        $this->standard_coursemodule_elements();

        // Add standard buttons, common to all modules.
        $this->add_action_buttons();
    }

    /**
     * Pre-process form data
     * @param array $defaultvalues
     */
    public function data_preprocessing(&$defaultvalues) {
        parent::data_preprocessing($defaultvalues);

        // Set up the completion checkboxes which aren't part of standard data.
        // We also make the default value (if you turn on the checkbox) for those
        // numbers to be 1, this will not apply unless checkbox is ticked.
        $defaultvalues['completionpercentenabled'] = !empty($defaultvalues['completionpercent']) ? 1 : 0;
        if (empty($defaultvalues['completionpercent'])) {
            $defaultvalues['completionpercent'] = 100;
        }
        if (empty($defaultvalues['completionpercenttype'])) {
            $defaultvalues['completionpercenttype'] = 'percent';
        }
    }

    /**
     * Add completion rules
     * @return string[]
     * @throws coding_exception
     */
    public function add_completion_rules() {
        $mform = $this->_form;

        $group = array();
        $group[] = $mform->createElement('checkbox', 'completionpercentenabled', '',
                                         get_string('completionpercent', 'checklist'), array('class' => 'checkbox-inline'));
        $group[] = $mform->createElement('text', 'completionpercent', '', array('size' => 3));
        $mform->setType('completionpercent', PARAM_INT);
        $opts = [
            'percent' => get_string('percent', 'mod_checklist'),
            'items' => get_string('itemstype', 'mod_checklist'),
        ];
        $group[] = $mform->createElement('select', 'completionpercenttype', '', $opts);

        $mform->addGroup($group, 'completionpercentgroup', get_string('completionpercentgroup', 'checklist'), array(' '), false);
        $mform->disabledIf('completionpercent', 'completionpercentenabled', 'notchecked');
        $mform->disabledIf('completionpercenttype', 'completionpercentenabled', 'notchecked');
        $mform->addHelpButton('completionpercentgroup', 'completionpercentgroup', 'mod_checklist');

        return array('completionpercentgroup');
    }

    /**
     * Are completion rules enabled?
     * @param array $data
     * @return bool
     */
    public function completion_rule_enabled($data) {
        return (!empty($data['completionpercentenabled']) && $data['completionpercent'] != 0);
    }

    /**
     * Get the form data
     * @return false|object
     */
    public function get_data() {
        $data = parent::get_data();
        if (!$data) {
            return false;
        }
        // Turn off completion settings if the checkboxes aren't ticked.
        if (isset($data->completionpercent)) {
            $autocompletion = !empty($data->completion) && $data->completion == COMPLETION_TRACKING_AUTOMATIC;
            if (empty($data->completionpercentenabled) || !$autocompletion) {
                $data->completionpercent = 0;
            }
        }
        return $data;
    }

}
