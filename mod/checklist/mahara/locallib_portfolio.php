<?php

// This file is part of Moodle - http://moodle.org/
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
 * Library of functions for forum outside of the core api
 */

require_once($CFG->dirroot . '/mod/checklist/lib.php');
require_once($CFG->dirroot . '/mod/checklist/locallib.php');
require_once($CFG->libdir . '/portfolio/caller.php');
require_once($CFG->libdir . '/filelib.php');


/**
 * @package   mod-referentiel
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @copyright 2011 Jean Fruitet  {@link http://univ-nantes.fr}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class checklist_portfolio_caller extends portfolio_module_caller_base {

    protected $instanceid;
    protected $export_format;

    protected $cm;
    protected $course;
    protected $checklist;
    protected $userid;
    
    private $adresse_retour;
    
    /**
     * @return array
     */
    public static function expected_callbackargs() {
        return array(
            'instanceid' => false,
            'userid'   => false,
            'export_format'  => false,
        );
    }
    /**
     * @param array $callbackargs
     */
    function __construct($callbackargs) {
        parent::__construct($callbackargs);
        if (!$this->instanceid) {
            throw new portfolio_caller_exception('mustprovideinstanceid', 'checklist');
        }
        if (!$this->userid) {
            throw new portfolio_caller_exception('mustprovideuser', 'checklist');
        }
        if (!isset($this->export_format)) {
            throw new portfolio_caller_exception('mustprovideexportformat', 'checklist');
        }
        else{
            // echo "<br>:: 86 ::$this->export_format\n";
            if ($this->export_format!=PORTFOLIO_FORMAT_FILE) {
                // depending on whether there are files or not, we might have to change richhtml/plainhtml
                $this->supportedformats = array_merge(array($this->supportedformats), array(PORTFOLIO_FORMAT_RICH, PORTFOLIO_FORMAT_LEAP2A));
            }
        }
    }
    /**
     * @global object
     */
    public function load_data() {
        global $DB;
        global $CFG;
        if ($this->instanceid) {
            if (!$this->checklist = $DB->get_record('checklist', array('id' => $this->instanceid))) {
                throw new portfolio_caller_exception('invalidinstanceid', 'checklist');
            }
        }
        
        if (!$this->cm = get_coursemodule_from_instance('checklist', $this->checklist->id)) {
            throw new portfolio_caller_exception('invalidcoursemodule');
        }
        $this->adresse_retour= '/mod/checklist/view.php?id='.$this->cm->id;
    }

    /**
     * @global object
     * @return string
     */
    function get_return_url() {
        global $CFG;
        return $CFG->wwwroot . $this->adresse_retour;
    }
    
    /**
     * @global object
     * @return array
     */
    function get_navigation() {
        global $CFG;

        $navlinks = array();
        $navlinks[] = array(
            'name' => format_string($this->checklist->name),
            'link' => $CFG->wwwroot . $this->adresse_retour,
            'type' => 'title'
        );
        return array($navlinks, $this->cm);
    }
    
    /**
     * either a whole discussion
     * a single post, with or without attachment
     * or just an attachment with no post
     *
     * @global object
     * @global object
     * @uses PORTFOLIO_FORMAT_HTML
     * @return mixed
     */
    function prepare_package() {
        global $CFG;
        global $OUTPUT;
        global $USER;
                // exporting a single HTML certificat
                $content_to_export = $this->prepare_checklist();
                $name = 'checklist'.'_'.$this->checklist->name.'_'.$this->checklist->id.'_'.$this->userid.'.html';
                // $manifest = ($this->exporter->get('format') instanceof PORTFOLIO_FORMAT_PLAINHTML);
                $manifest = ($this->exporter->get('format') instanceof PORTFOLIO_FORMAT_RICH);

                // DEBUG
                /*
                echo "<br>DEBUG :: 179 :: CONTENT<br>\n";
                echo($content_to_export);
                echo "<br>MANIFEST : $manifest<br>\n";
                echo "<br>FORMAT ".$this->exporter->get('formatclass')."\n";
                */

                $content=$content_to_export;

                if ($this->exporter->get('formatclass') == PORTFOLIO_FORMAT_LEAP2A) {
                    $leapwriter = $this->exporter->get('format')->leap2a_writer($USER);
                    // DEBUG
                    //echo "<br>DEBUG :: 169 :: LEAPWRITER<br>\n";
                    //print_object($leapwriter);
                    // exit;
                    if ($leapwriter){
                        if ($this->prepare_certificat_leap2a($leapwriter, $content_to_export)){
                            // echo "<br>DEBUG :: 175\n";
                            $content = $leapwriter->to_xml();
                            // DEBUG
                            // echo "<br><br>DEBUG :: mod/checklist/mahara/locallib_portfolio.php :: 167<br>\n";
                            // echo htmlspecialchars($content);
                            $name = $this->exporter->get('format')->manifest_name();
                        }
                    }
                }
                /*
                // DEBUG
                echo "<br>DEBUG :: 176<br>\n";
                print_object($content);
                */
                $this->get('exporter')->write_new_file($content, $name, $manifest);

    }

    

    /**
     * @return string
     */
    function get_sha1() {
        $filesha = '';
        try {
            $filesha = $this->get_sha1_file();
        } catch (portfolio_caller_exception $e) { } // no files

        if ($this->checklist && $this->userid){
            return sha1($filesha . ',' . $this->checklist->id. ',' . $this->checklist->name. ',' . $this->userid);
        }
        return 0;
    }

    function expected_time() {
        // a file based export
        if ($this->singlefile) {
            return portfolio_expected_time_file($this->singlefile);
        }
        else{
            return PORTFOLIO_TIME_LOW;
        }
    }

    /**
     * @uses CONTEXT_MODULE
     * @return bool
     */
    function check_permissions() {
        $context = get_context_instance(CONTEXT_MODULE, $this->cm->id);
        return true;
    }
    
    /**
     * @return string
     */
    public static function display_name() {
        return get_string('modulename', 'checklist');
    }

    public static function base_supported_formats() {
        //return array(PORTFOLIO_FORMAT_FILE, PORTFOLIO_FORMAT_PLAINHTML, PORTFOLIO_FORMAT_LEAP2A);
        return array(PORTFOLIO_FORMAT_FILE);
    }
    
    /**
     * helper function to add a leap2a entry element
     * that corresponds to a single certificate,
     *
     * the entry/ies are added directly to the leapwriter, which is passed by ref
     *
     * @global object $checklist $userid the stdclass object representing the database record
     * @param portfolio_format_leap2a_writer $leapwriter writer object to add entries to
     * @param string $content  the content of the certificate (prepared by {@link prepare_checklist}
     *
     * @return int id of new entry
     */
    private function prepare_certificat_leap2a(portfolio_format_leap2a_writer $leapwriter, $content) {
    global $USER;
        $order   = array( "&nbsp;",  "\r\n", "\n", "\r");
        $replace = ' ';
        $content=str_replace($order, $replace, $content);

        $title=get_string('modulename', 'checklist').' '.$this->checklist->name. ' '. $this->userid;
        $entry = new portfolio_format_leap2a_entry('checklist_id' . $this->checklist->id .'_user'. $this->userid, $title, 'leap2', $content); // proposer ability ?
        $entry->published = time();
        $entry->updated = time();
        $entry->author->id = $this->userid;
        $entry->summary = $this->checklist->name.' '.strip_tags($this->checklist->intro);
        $entry->add_category('web', 'any_type', 'CheckList');
        // DEBUG
        /*
        echo "<br>246 :: ENTRY<br>\n";
        print_object($entry);
        */
        $leapwriter->add_entry($entry);
        /*
        echo "<br>286 :: LEAPWRITER<br>\n";
        print_object($leapwriter);
        */
        return $entry->id;
    }

    /**
     * this is a very cut down version of what is in checklist_certificat print_lib
     *
     * @global object
     * @return string
     */
    private function prepare_checklist() {
        global $DB;
        $output='';
        $info_checklist='';
        $info_items='';
        $fullname ='';
        $login='';

        if(!empty($this->userid)){
            $user= $DB->get_record('user', array('id' => $this->userid));
            if ($user){
                $fullname = fullname($user, true);
                $login=$user->username;
            }
        }

        if (!empty($this->checklist) && !empty($this->userid) ) {
            $info_checklist = "<h2>".$this->checklist->name."</h2>\n<p>".strip_tags($this->checklist->intro)."\n";
            if (!empty($this->checklist->timecreated)){
                $info_checklist .= "<br />".get_string('timecreated', 'checklist')." ".userdate($this->checklist->timecreated)." ";
            }
            if (!empty($this->checklist->timemodified)){
                $info_checklist .= "<br />".get_string('timemodified', 'checklist')." ".userdate($this->checklist->timemodified)." ";
            }
            $info_checklist .= "</p>\n";
            
            $info_items='';
            $items=$DB->get_records('checklist_item', array('checklist' => $this->checklist->id), 'position ASC', '*');
            if ($items){
                $info_items.= "<h3>".get_string('items', 'checklist')."</h3>\n";
                $info_items.= "<ul>\n";
                foreach ($items as $item){
                    if ($item){
                        $info_items.="<li><i>".get_string('id', 'checklist').$item->id."</i> <b>".stripslashes($item->displaytext)."</b>\n";

                        // checks
                        $checks=$DB->get_records('checklist_check', array('item' => $item->id, 'userid' => $this->userid));
                        if ($checks){
                            foreach ($checks as $check){
                                switch ($check->teachermark) {
                                    case CHECKLIST_TEACHERMARK_YES:
                                        $info_items.="<br /> ".get_string('teachermarkyes','checklist');
                                        break;
                                    case CHECKLIST_TEACHERMARK_NO:
                                        $info_items.="<br />  ".get_string('teachermarkno','checklist');
                                        break;
                                    default:
                                        $info_items.="<br />  ".get_string('teachermarkundecided','checklist');
                                        break;
                                }

                                if (!empty($check->usertimestamp)){
                                    $info_items.= " (".get_string('usertimestamp', 'checklist')." ".userdate($check->usertimestamp).") ";
                                }
                                if (!empty($check->teachertimestamp)){
                                    $info_items.= " (".get_string('teachertimestamp', 'checklist')." ".userdate($check->teachertimestamp).") ";
                                }
                                $info_items.= "<br />\n";
                            }
                        }

                        // comments
                        $comments=$DB->get_records('checklist_comment', array('itemid' => $item->id, 'userid' => $this->userid));
                        if ($comments){
                            $info_items.= "<h4>".get_string('comments', 'checklist')."</h4>\n";
                            foreach ($comments as $comment){
                                if (!empty($comment->text)){
                                    $info_items.= "<br />&nbsp;  &nbsp;  &nbsp; ". stripslashes($comment->text)." ";
                                }
                                if ($comment->commentby){
                                    $teacher= $DB->get_record('user', array('id' => $comment->commentby));
                                    if ($teacher){
                                        $fullnameteacher =fullname($teacher, true);
                                    }
                                }
                                if (!empty($fullnameteacher)){
                                    $info_items.="<br />(".get_string('commentby', 'checklist')." ".$fullnameteacher.") ";
                                }
                                $info_items.= "\n";
                            }
                        }
                        // description
                        $descriptions=$DB->get_records('checklist_description', array('itemid' => $item->id, 'userid' => $this->userid));
                        if ($descriptions){
                            $info_items.= "<h4>".get_string('argumentation', 'checklist')."</h4>\n";

                            foreach ($descriptions as $description){
                                if (!empty($description->description)){
                                    $info_items.= "<p>".stripslashes($description->description)." ";
                                }
                                if (!empty($description->timestamp)){
                                    $info_items.= " (".userdate($description->timestamp).") \n";
                                }
                                // documents
                                $documents=$DB->get_records('checklist_document', array('descriptionid' => $description->id));
                                if ($documents){
                                    $info_items.= "<ol>\n";
                                    foreach ($documents as $document){
                                        if (!empty($document)){
			                                if ($document->target==1){
                                                $cible_document='_blank'; // fenêtre cible
			                                }
                                			else{
                                                $cible_document='';
                                			}
                                  			if ($document->title){
                                                $etiquette_document=$document->title; // fenêtre cible
                                            }
                                			else{
                                                $etiquette_document='';
			                                }
			                                $info_items.="<li>&nbsp;  &nbsp;  &nbsp;  &nbsp; ".get_string('doc_num','checklist',$document->id).' &nbsp; '.stripslashes($document->description_document)." ";
                                            $info_items.=checklist_affiche_url($document->url_document, $etiquette_document, $cible_document);
                                            $info_items.=' [<i><span class="small">'.userdate($document->timestamp).'</span></i>] '."</li>\n";
                                        }
                                    }
                                    $info_items.= "</ol>\n";
                                }
                                $info_items.= "</p>\n";
                            }

                        }
                        $info_items.= "</li>\n";
                    }
                }
                $info_items.= "</ul>\n";
            }
            // format the body
            $s='<h3>'.get_string('modulename','checklist').'</h3>'."\n";
            $s.='<p><b>'.$fullname.'</b> (<i>'.$login.'</i>)</p>';
            $s.=$info_checklist;
            $s.=$info_items;

            // DEBUG
            // echo $s;
            // exit;
            $options = portfolio_format_text_options();
            $format = $this->get('exporter')->get('format');
            $formattedtext = format_text($s, FORMAT_HTML, $options);

            // $formattedtext = portfolio_rewrite_pluginfile_urls($formattedtext, $this->context->id, 'mod_checklist', 'document', $item->id, $format);

            $output = '<table border="0" cellpadding="3" cellspacing="1" bgcolor="#333300">';
            $output .= '<tr valign="top" bgcolor="#ffffff"><td>';
            $output .= '<div><b>'.get_string('modulename', 'checklist').' '. format_string($this->checklist->name).'</b></div>';
            $output .= '</td></tr>';
            $output .= '<tr valign="top" bgcolor="#ffffff"><td align="left">';
            $output .= $formattedtext;
            $output .= '</td></tr></table>'."\n\n";

        }
        return $output;
    }



}

