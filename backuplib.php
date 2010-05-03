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
       
        global $CFG;

        $status = true;

        $checklists = get_records ('checklist','course',$preferences->backup_course,'id');
        if ($checklist) {
            foreach ($checklist as $checklist) {
                if (backup_mod_selected($preferences,'checklist',$checklist->id)) {
                    $status = checklist_backup_one_mod($bf,$preferences,$checklist);
                }
            }
        }
        return $status;
    }


    function checklist_backup_one_mod($bf,$preferences,$checklist) {
    
        global $CFG;
        
        if (is_numeric($checklist)) {
            $checklist = get_record('checklist','id',$checklist);
        }
        $instanceid = $checklist->id;
        
        $status = true;
        
        //Start mod
        fwrite ($bf,start_tag("MOD",3,true));
        
        fwrite ($bf,full_tag("ID",4,false,$checklist->id));
        fwrite ($bf,full_tag("MODTYPE",4,false,"checklist"));
        fwrite ($bf,full_tag("NAME",4,false,$checklist->name));
        fwrite ($bf,full_tag("INTRO",4,false,$checklist->intro));
        fwrite ($bf,full_tag("INTROFORMAT",4,false,$checklist->introformat));
        fwrite ($bf,full_tag("TIMECREATED",4,false,$checklist->timecreated));
        fwrite ($bf,full_tag("TIMEMODIFIED",4,false,$checklist->timemodified));
        fwrite ($bf,full_tag("USERITEMSALLOWED",4,false,$checklist->useritemsallowed));
        fwrite ($bf,full_tag("TEACHEREDIT",4,false,$checklist->teacheredit));
        fwrite ($bf,full_tag("THEME",4,false,$checklist->theme));
        fwrite ($bf,full_tag("DUEDATESONCALENDAR",4,false,$checklist->duedatesoncalendar));
        fwrite ($bf,full_tag("TEACHERCOMMENTS",4,false,$checklist->teachercomments));

        $status = backup_checklist_items($bf,$preferences,$checklist->id);

        if ($status) $status = fwrite ($bf,end_tag("MOD",3,true));
        
        return $status;
    }


    function checklist_check_backup_mods_instances($instance,$backup_unique_code) {
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
		global $CFG;
		
		$status = true;
        $userbackup = backup_userdata_selected($preferences,'checklist',$checklist);
		
        if ($userbackup) {
            $checklist_items = get_records('checklist_item','checklist',$checklist,'id');
        } else {
            $checklist_items = get_records_select('checklist_item',"checklist = $checklist AND userid = 0",'id');
        }
		if ($checklist_items) {
			$status = fwrite($bf, start_tag("ITEMS",4,true));
			foreach ($checklist_items as $item) {
				if ($status) $status = fwrite($bf, start_tag("ITEM", 5, true));
				if ($status) $status = fwrite ($bf, full_tag("ID",6,false,$item->id));
				if ($status) $status = fwrite ($bf, full_tag("CHECKLIST",6,false,$item->checklist));
				if ($status) $status = fwrite ($bf, full_tag("USERID",6,false,$item->userid));
				if ($status) $status = fwrite ($bf, full_tag("DISPLAYTEXT",6,false,$item->displaytext));
				if ($status) $status = fwrite ($bf, full_tag("POSITION",6,false,$item->position));
				if ($status) $status = fwrite ($bf, full_tag("INDENT",6,false,$item->indent));
				if ($status) $status = fwrite ($bf, full_tag("ITEMOPTIONAL",6,false,$item->itemoptional));
                if ($status) $status = fwrite ($bf, full_tag("DUETIME",6,false,$item->duetime));
				
                if ($userbackup) {
                    if ($status) $status = backup_checklist_checks($bf, $preferences, $item->id);
                }
				if ($status) $status = fwrite($bf, end_tag("ITEM", 5, true));
			}
			if ($status) $status = fwrite($bf, end_tag("ITEMS",4,true));
		}
		
		return $status;
	}

	function backup_checklist_checks($bf, $preferences, $item) {
		global $CFG;
		
		$status = true;
		
		$checklist_checks = get_records('checklist_check','item',$item,'id');
		if ($checklist_checks) {
		    $status = fwrite($bf, start_tag('CHECKS',7,true));
		    foreach ($checklist_checks as $check) {
		        if ($status) $status = fwrite($bf, start_tag("CHECK", 8, true));
		        if ($status) $status = fwrite($bf, full_tag("ID",9,false,$check->id));
		        if ($status) $status = fwrite($bf, full_tag("ITEM",9,false,$check->item));
		        if ($status) $status = fwrite($bf, full_tag("USERID",9,false,$check->userid));
		        if ($status) $status = fwrite($bf, full_tag("USERTIMESTAMP",9,false,$check->usertimestamp));
                if ($status) $status = fwrite($bf, full_tag("TEACHERMARK",9,false,$check->teachermark));
                if ($status) $status = fwrite($bf, full_tag("TEACHERTIMESTAMP",9,false,$check->teachertimestamp));
		        if ($status) $status = fwrite($bf, end_tag("CHECK", 8, true));		        
		    }
		
		    if ($status) $status = fwrite($bf, end_tag("CHECKS",7,true));
		}

        $checklist_comments = get_records('checklist_comment','itemid',$item,'id');
        if ($checklist_comments) {
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
       
       if (!empty($instances) && is_array($instances) && count($instances)) {
           $info = array();
           foreach ($instances as $id => $instance) {
               $info += checklist_check_backup_mods_instances($instance,$backup_unique_code);
           }
           return $info;
       }
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

        global $CFG;

        return get_records_sql ("SELECT c.id, c.course
                                 FROM {$CFG->prefix}checklist c
                                 WHERE c.course = '$course'");
    }

function checklist_item_ids_by_course ($course, $userdata) {

        global $CFG;

        if ($userdata) {
            $userid = '';
        } else {
            $userid = ' AND i.userid = 0 ';
        }
        
        return get_records_sql ("SELECT i.id, i.checklist      
                                 FROM {$CFG->prefix}checklist_item i,    
                                      {$CFG->prefix}checklist c 
                                 WHERE c.course = '$course' AND
                                       i.checklist = c.id".$userid); 
}
    
function checklist_item_ids_by_instance ($instanceid, $userdata) {

        global $CFG;

        if ($userdata) {
            $userid = '';
        } else {
            $userid = ' AND i.userid = 0 ';
        }

        return get_records_sql ("SELECT i.id, i.checklist      
                                 FROM {$CFG->prefix}checklist_item i
                                 WHERE i.checklist = $instanceid".$userid); 
}
    
function checklist_check_ids_by_course ($course) {

        global $CFG;

        return get_records_sql ("SELECT k.id, k.item 
                                 FROM {$CFG->prefix}checklist_check k,
                                      {$CFG->prefix}checklist_item i,    
                                      {$CFG->prefix}checklist c 
                                 WHERE c.course = '$course' AND
                                       i.checklist = c.id AND
                                       k.item = i.id"); 
}
    
function checklist_check_ids_by_instance ($instanceid) {

        global $CFG;

        return get_records_sql ("SELECT k.id, k.item
                                 FROM {$CFG->prefix}checklist_check k,
                                      {$CFG->prefix}checklist_item i     
                                 WHERE i.checklist = $instanceid AND
                                       k.item = i.id"); 
}

function checklist_comment_ids_by_course ($course) {

        global $CFG;

        return get_records_sql ("SELECT t.id, t.itemid 
                                 FROM {$CFG->prefix}checklist_comment t,
                                      {$CFG->prefix}checklist_item i,    
                                      {$CFG->prefix}checklist c 
                                 WHERE c.course = '$course' AND
                                       i.checklist = c.id AND
                                       t.itemid = i.id"); 
}
    
function checklist_comment_ids_by_instance ($instanceid) {

        global $CFG;

        return get_records_sql ("SELECT t.id, t.itemid
                                 FROM {$CFG->prefix}checklist_comment t,
                                      {$CFG->prefix}checklist_item i     
                                 WHERE i.checklist = $instanceid AND
                                       t.itemid = i.id"); 
}
    
?>