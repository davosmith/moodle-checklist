<?php
    //Based on php script for backing up forum mods
	//This php script contains all the stuff to backup/restore
    //checklist mods
    //
    //This is the "graphical" structure of the checklist mod:
    //
    //                            checklist
    //                            (CL,pk->id)
    //                                 |
    //                                 |       
    //                                 |
    //                          checklist_item
    //                        (UL,pk->id, fk->checklist)
    //                                 |
    //                                 |
    //                                 |
    //                          checklist_check
    //                     (UL,pk->id,fk->item) 
    //
    // Meaning: pk->primary key field of the table
    //          fk->foreign key to link with parent
    //          CL->course level info
    //          UL->user level info
    //
    //-----------------------------------------------------------

    function checklist_backup_mods($bf,$preferences) {
        //UT
        global $CFG, $DB;

        $status = true;

        $checklists = $DB->get_records ('checklist', array('course' => $preferences->backup_course), 'id');
        foreach ($checklist as $checklist) {
            if (backup_mod_selected($preferences,'checklist',$checklist->id)) {
                $status = checklist_backup_one_mod($bf,$preferences,$checklist);
            }
        }
        return $status;
    }


    function checklist_backup_one_mod($bf,$preferences,$checklist) {
        //UT
        global $CFG, $DB;
        
        if (is_numeric($checklist)) {
            //UT
            $checklist = $DB->get_record('checklist', array('id' => $checklist) );
        }
        $instanceid = $checklist->id;
        
        $status = true;
        
        //Start mod
        $status = $status && fwrite ($bf,start_tag("MOD",3,true));
        
        $status = $status && fwrite ($bf,full_tag("ID",4,false,$checklist->id));
        $status = $status && fwrite ($bf,full_tag("MODTYPE",4,false,"checklist"));
        $status = $status && fwrite ($bf,full_tag("NAME",4,false,$checklist->name));
        $status = $status && fwrite ($bf,full_tag("INTRO",4,false,$checklist->intro));
        $status = $status && fwrite ($bf,full_tag("INTROFORMAT",4,false,$checklist->introformat));
        $status = $status && fwrite ($bf,full_tag("TIMECREATED",4,false,$checklist->timecreated));
        $status = $status && fwrite ($bf,full_tag("TIMEMODIFIED",4,false,$checklist->timemodified));
        $status = $status && fwrite ($bf,full_tag("USERITEMSALLOWED",4,false,$checklist->useritemsallowed));
        $status = $status && fwrite ($bf,full_tag("TEACHEREDIT",4,false,$checklist->teacheredit));
        $status = $status && fwrite ($bf,full_tag("THEME",4,false,$checklist->theme));
        $status = $status && fwrite ($bf,full_tag("DUEDATESONCALENDAR",4,false,$checklist->duedatesoncalendar));
        $status = $status && fwrite ($bf,full_tag("TEACHERCOMMENTS",4,false,$checklist->teachercomments));

        $status = backup_checklist_items($bf,$preferences,$checklist->id);

        $status = $status && fwrite ($bf,end_tag("MOD",3,true));
        
        return $status;
    }


    function checklist_check_backup_mods_instances($instance,$backup_unique_code) {
        //UT
        
        $info[$instance->id.'0'][0] = '<b>'.$instance->name.'</b>';
        $info[$instance->id.'0'][1] = '';
        $info[$instance->id.'1'][0] = get_string('items','checklist');
        $userdata = !empty($instance->userdata);
        if ($ids = checklist_item_ids_by_instance ($instance->id, $userdata)) {
            $info[$instance->id.'1'][1] = count($ids);        
        } else {
            $info[$instance->id.'1'][1] = 0;
        }
        if ($userdata) {
            $info[$instance->id.'2'][0] = get_string('checks','checklist');
            if ($ids = checklist_check_ids_by_instance ($instance->id)) {
                $info[$instance->id.'2'][1] = count($ids);        
            } else {
                $info[$instance->id.'2'][1] = 0;
            }
            $info[$instance->id.'3'][0] = get_string('comments','checklist');
            if ($ids = checklist_comment_ids_by_instance($instance->id)) {
                $info[$instance->id.'3'][1] = count($ids);
            } else {
                $info[$instance->id.'3'][1] = 0;
            }
        }
        
        return $info;
    }


	function backup_checklist_items($bf, $preferences,$checklist) {
        //UT
        
		global $CFG, $DB;
		
		$status = true;
        $userbackup = backup_userdata_selected($preferences,'checklist',$checklist);
		
        if ($userbackup) {
            //UT
            $checklist_items = $DB->get_records('checklist_item', array('checklist' => $checklist), 'id');
        } else {
            //UT
            $checklist_items = $DB->get_records('checklist_item',array('checklist' = > $checklist, 'userid' => 0),'id');
        }
		if (!empty($checklist_items)) {
            //UT
			$status = fwrite($bf, start_tag("ITEMS",4,true));
			foreach ($checklist_items as $item) {
				$status = $status && fwrite($bf, start_tag("ITEM", 5, true));
				$status = $status && fwrite ($bf, full_tag("ID",6,false,$item->id));
				$status = $status && fwrite ($bf, full_tag("CHECKLIST",6,false,$item->checklist));
				$status = $status && fwrite ($bf, full_tag("USERID",6,false,$item->userid));
				$status = $status && fwrite ($bf, full_tag("DISPLAYTEXT",6,false,$item->displaytext));
				$status = $status && fwrite ($bf, full_tag("POSITION",6,false,$item->position));
				$status = $status && fwrite ($bf, full_tag("INDENT",6,false,$item->indent));
				$status = $status && fwrite ($bf, full_tag("ITEMOPTIONAL",6,false,$item->itemoptional));
                $status = $status && fwrite ($bf, full_tag("DUETIME",6,false,$item->duetime));
				
                if ($userbackup) {
                    //UT
                    $status = $status && backup_checklist_checks($bf, $preferences, $item->id);
                }
				$status = $status && fwrite($bf, end_tag("ITEM", 5, true));
			}
			$status = $status && fwrite($bf, end_tag("ITEMS",4,true));
		}
		
		return $status;
	}

	function backup_checklist_checks($bf, $preferences, $item) {
        //UT
		global $CFG, $DB;
		
		$status = true;
		
		$checklist_checks = $DB->get_records('checklist_check', array('item' => $item), 'id');
		if (!empty($checklist_checks)) {
            //UT
		    $status = fwrite($bf, start_tag('CHECKS',7,true));
		    foreach ($checklist_checks as $check) {
		        $status = $status && fwrite($bf, start_tag("CHECK", 8, true));
		        $status = $status && fwrite($bf, full_tag("ID",9,false,$check->id));
		        $status = $status && fwrite($bf, full_tag("ITEM",9,false,$check->item));
		        $status = $status && fwrite($bf, full_tag("USERID",9,false,$check->userid));
		        $status = $status && fwrite($bf, full_tag("USERTIMESTAMP",9,false,$check->usertimestamp));
                $status = $status && fwrite($bf, full_tag("TEACHERMARK",9,false,$check->teachermark));
                $status = $status && fwrite($bf, full_tag("TEACHERTIMESTAMP",9,false,$check->teachertimestamp));
		        $status = $status && fwrite($bf, end_tag("CHECK", 8, true));		        
		    }
		
		    $status = $status && fwrite($bf, end_tag("CHECKS",7,true));
		}

        $checklist_comments = $DB->get_records('checklist_comment', array('itemid' => $item), 'id');
        if (!empty($checklist_comments)) {
            //UT
            $status = fwrite($bf, start_tag('COMMENTS',7,true));
            foreach ($checklist_comments as $comment) {
                $status = $status && fwrite($bf, start_tag('COMMENT', 8, true));
                $status = $status && fwrite($bf, full_tag('ID',9,false,$comment->id));
                $status = $status && fwrite($bf, full_tag('USERID',9,false,$comment->userid));
                $status = $status && fwrite($bf, full_tag('COMMENTBY',9,false,$comment->commentby));
                $status = $status && fwrite($bf, full_tag('TEXT',9,false,$comment->text));
                $status = $status && fwrite($bf, end_tag('COMMENT', 8, true));
            }

            $status = $status && fwrite($bf, end_tag('COMMENTS', 7, true));
        }
				
		return $status;
	}

////Return an array of info (name,value)
   function checklist_check_backup_mods($course,$user_data=false,$backup_unique_code,$instances=null) {
       //UT
       
       if (!empty($instances) && is_array($instances) && count($instances)) {
           //UT
           $info = array();
           foreach ($instances as $id => $instance) {
               $info += checklist_check_backup_mods_instances($instance,$backup_unique_code);
           }
           return $info;
       }

       //UT
        //First the course data
       $info[0][0] = get_string('modulenameplural','checklist');
       if ($ids = checklist_ids ($course)) {
           $info[0][1] = count($ids);
       } else {
           $info[0][1] = 0;
       }
        
       $info[1][0] = get_string('items','checklist');
       if ($ids = checklist_item_ids_by_course($course, $user_data)) {
           $info[1][1] = count($ids);
       } else {
           $info[1][1] = 0;
       }

       if ($user_data) {
           //UT
           $info[2][0] = get_string('checks','checklist');
           if ($ids = checklist_check_ids_by_course($course)) {
               $info[2][1] = count($ids);
           } else {
               $info[2][1] = 0;
           }
           $info[3][0] = get_string('comments','checklist');
           if ($ids = checklist_comment_ids_by_course($course)) {
               $info[3][1] = count($ids);
           } else {
               $info[3][1] = 0;
           }
       }

       return $info;
   }

/*
    //Return a content encoded to support interactivities linking. Every module
    //should have its own. They are called automatically from the backup procedure.
    function forum_encode_content_links ($content,$preferences) {

        global $CFG;

        $base = preg_quote($CFG->wwwroot,"/");

        //Link to the list of forums
        $buscar="/(".$base."\/mod\/forum\/index.php\?id\=)([0-9]+)/";
        $result= preg_replace($buscar,'$@FORUMINDEX*$2@$',$content);

        //Link to forum view by moduleid
        $buscar="/(".$base."\/mod\/forum\/view.php\?id\=)([0-9]+)/";
        $result= preg_replace($buscar,'$@FORUMVIEWBYID*$2@$',$result);

        //Link to forum view by forumid
        $buscar="/(".$base."\/mod\/forum\/view.php\?f\=)([0-9]+)/";
        $result= preg_replace($buscar,'$@FORUMVIEWBYF*$2@$',$result);

        //Link to forum discussion with parent syntax
        $buscar="/(".$base."\/mod\/forum\/discuss.php\?d\=)([0-9]+)\&parent\=([0-9]+)/";
        $result= preg_replace($buscar,'$@FORUMDISCUSSIONVIEWPARENT*$2*$3@$',$result);

        //Link to forum discussion with relative syntax
        $buscar="/(".$base."\/mod\/forum\/discuss.php\?d\=)([0-9]+)\#([0-9]+)/";
        $result= preg_replace($buscar,'$@FORUMDISCUSSIONVIEWINSIDE*$2*$3@$',$result);

        //Link to forum discussion by discussionid
        $buscar="/(".$base."\/mod\/forum\/discuss.php\?d\=)([0-9]+)/";
        $result= preg_replace($buscar,'$@FORUMDISCUSSIONVIEW*$2@$',$result);

        return $result;
    }
*/
    // INTERNAL FUNCTIONS. BASED IN THE MOD STRUCTURE

    //Returns an array of checklist id
    function checklist_ids ($course) {

        //UT
        global $CFG, $DB;

        return $DB->get_records_sql ('SELECT c.id, c.course
                                 FROM {checklist} c
                                 WHERE c.course = ?', array($course));
    }

function checklist_item_ids_by_course ($course, $userdata) {
    //UT
    global $CFG, $DB;

        if ($userdata) {
            $userid = '';
        } else {
            $userid = ' AND i.userid = 0 ';
        }
        
        return $DB->get_records_sql ('SELECT i.id, i.checklist      
                                 FROM {checklist_item} i,    
                                      {checklist} c 
                                 WHERE c.course = ? AND
                                       i.checklist = c.id'.$userid, array($course)); 
}
    
function checklist_item_ids_by_instance ($instanceid, $userdata) {
    //UT
    global $CFG, $DB;

        if ($userdata) {
            $userid = '';
        } else {
            $userid = ' AND i.userid = 0 ';
        }

        return $DB->get_records_sql ("SELECT i.id, i.checklist      
                                 FROM {checklist_item} i
                                 WHERE i.checklist = ?".$userid, array($instanceid) ); 
}
    
function checklist_check_ids_by_course ($course) {
    //UT

    global $CFG, $DB;

        return $DB->get_records_sql ('SELECT k.id, k.item 
                                 FROM {checklist_check} k,
                                      {checklist_item} i,    
                                      {checklist} c 
                                 WHERE c.course = ? AND
                                       i.checklist = c.id AND
                                       k.item = i.id', array($course)); 
}
    
function checklist_check_ids_by_instance ($instanceid) {
    //UT
    global $CFG, $DB;

        return $DB->get_records_sql ('SELECT k.id, k.item
                                 FROM {checklist_check} k,
                                      {checklist_item} i     
                                 WHERE i.checklist = ? AND
                                       k.item = i.id', array($instanceid)); 
}

function checklist_comment_ids_by_course ($course) {
    //UT
    global $CFG, $DB;

        return $DB->get_records_sql ('SELECT t.id, t.itemid 
                                 FROM {checklist_comment} t,
                                      {checklist_item} i,    
                                      {checklist} c 
                                 WHERE c.course = ? AND
                                       i.checklist = c.id AND
                                       t.itemid = i.id', array($course)); 
}
    
function checklist_comment_ids_by_instance ($instanceid) {
    //UT
        global $CFG;

        return $DB->get_records_sql ("SELECT t.id, t.itemid
                                 FROM {checklist_comment} t,
                                      {checklist_item} i     
                                 WHERE i.checklist = ? AND
                                       t.itemid = i.id", array($instanceid)); 
}
    
?>