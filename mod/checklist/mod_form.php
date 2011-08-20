<?php

/**
 * This file defines the main checklist configuration form
 * It uses the standard core Moodle (>1.8) formslib. For
 * more info about them, please visit:
 *
 * http://docs.moodle.org/en/Development:lib/formslib.php
 *
 * The form must provide support for, at least these fields:
 *   - name: text element of 64cc max
 *
 * Also, it's usual to use these fields:
 *   - intro: one htmlarea element to describe the activity
 *            (will be showed in the list of activities of
 *             newmodule type (index.php) and in the header
 *             of the checklist main page (view.php).
 *   - introformat: The format used to write the contents
 *             of the intro field. It automatically defaults
 *             to HTML when the htmleditor is used and can be
 *             manually selected if the htmleditor is not used
 *             (standard formats are: MOODLE, HTML, PLAIN, MARKDOWN)
 *             See lib/weblib.php Constants and the format_text()
 *             function for more info
 */

require_once($CFG->dirroot.'/course/moodleform_mod.php');

class mod_checklist_mod_form extends moodleform_mod {

    function definition() {

        global $COURSE, $CFG;
        $mform =& $this->_form;

//-------------------------------------------------------------------------------
    /// Adding the "general" fieldset, where all the common settings are showed
        $mform->addElement('header', 'general', get_string('general', 'form'));

    /// Adding the standard "name" field
        $mform->addElement('text', 'name', get_string('modulename', 'checklist'), array('size'=>'64'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

    /// Adding the required "intro" field to hold the description of the instance
        $mform->addElement('htmleditor', 'intro', get_string('checklistintro', 'checklist'));
        $mform->setType('intro', PARAM_RAW);
        $mform->addRule('intro', get_string('required'), 'required', null, 'client');
        $mform->setHelpButton('intro', array('writing', 'richtext'), false, 'editorhelpbutton');

    /// Adding "introformat" field
        $mform->addElement('format', 'introformat', get_string('format'));

//-------------------------------------------------------------------------------

        $mform->addElement('header', 'checklistsettings', get_string('checklistsettings', 'checklist'));

        $ynoptions = array( 0 => get_string('no'), 1 => get_string('yes'));
        $mform->addElement('select', 'useritemsallowed', get_string('useritemsallowed', 'checklist'), $ynoptions);

        $teditoptions = array();
        $teditoptions[CHECKLIST_MARKING_STUDENT] = get_string('teachernoteditcheck','checklist');
        $teditoptions[CHECKLIST_MARKING_TEACHER] = get_string('teacheroverwritecheck', 'checklist');
        $teditoptions[CHECKLIST_MARKING_BOTH] = get_string('teacheralongsidecheck', 'checklist');
        $mform->addElement('select', 'teacheredit', get_string('teacheredit', 'checklist'), $teditoptions);

        $mform->addElement('select', 'duedatesoncalendar', get_string('duedatesoncalendar', 'checklist'), $ynoptions);
        $mform->setDefault('duedatesoncalendar', 0);

        // These settings are all disabled, as they are not currently implemented

        /*
        $themes = array('default' => 'default');
        $mform->addElement('select', 'theme', get_string('theme', 'checklist'), $themes);
        */

        $mform->addElement('select', 'teachercomments', get_string('teachercomments', 'checklist'), $ynoptions);
        $mform->setDefault('teachercomments', 1);
        $mform->setAdvanced('teachercomments');

        $mform->addElement('text', 'maxgrade', get_string('maximumgrade'), array('size'=>'10'));
        $mform->setDefault('maxgrade', 100);
        $mform->setAdvanced('maxgrade');

        $mform->addElement('selectyesno', 'emailoncomplete', get_string('emailoncomplete', 'checklist'));
        $mform->setDefault('emailoncomplete', 0);
        $mform->setHelpButton('emailoncomplete', array('emailoncomplete', get_string('emailoncomplete','checklist'), 'checklist'));

        $autopopulateoptions = array (CHECKLIST_AUTOPOPULATE_NO => get_string('no'),
                                      CHECKLIST_AUTOPOPULATE_SECTION => get_string('importfromsection','checklist'),
                                      CHECKLIST_AUTOPOPULATE_COURSE => get_string('importfromcourse', 'checklist'));
        $mform->addElement('select', 'autopopulate', get_string('autopopulate', 'checklist'), $autopopulateoptions);
        $mform->setDefault('autopopulate', 0);
        $mform->setHelpButton('autopopulate', array('autopopulate', get_string('autopopulate','checklist'), 'checklist'));

        $autoupdate_options = array( CHECKLIST_AUTOUPDATE_NO => get_string('no'),
                                     CHECKLIST_AUTOUPDATE_YES => get_string('yesnooverride', 'checklist'),
                                     CHECKLIST_AUTOUPDATE_YES_OVERRIDE => get_string('yesoverride', 'checklist'));
        $mform->addElement('select', 'autoupdate', get_string('autoupdate', 'checklist'), $autoupdate_options);
        $mform->setDefault('autoupdate', 1);
        $mform->disabledIf('autoupdate', 'autopopulate', 'eq', 0);
        $mform->setHelpButton('autoupdate', array('autoupdate', get_string('autoupdate','checklist'), 'checklist'));

        $mform->addElement('selectyesno', 'lockteachermarks', get_string('lockteachermarks', 'checklist'));
        $mform->setDefault('lockteachermarks', 0);
        $mform->setAdvanced('lockteachermarks');
        $mform->setHelpButton('lockteachermarks', array('lockteachermarks', get_string('lockteachermarks', 'checklist'), 'checklist'));

//-------------------------------------------------------------------------------
        // add standard elements, common to all modules
        $features = new stdClass;
        $features->groups = true;
        $features->groupings = true;
        $features->groupmembersonly = true;
        $this->standard_coursemodule_elements($features);
//-------------------------------------------------------------------------------
        // add standard buttons, common to all modules
        $this->add_action_buttons();

    }
}

?>
