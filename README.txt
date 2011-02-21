Checklist module
================

**Introduction**
This is a Moodle plugin for Moodle 1.9 & 2.0+ that allows a teacher to create a checklist for their students to work through.
The teacher can monitor all the student's progress, as they tick off each of the items in the list.
Note: There are separate downloads for the Moodle 1.9 and 2.0+ versions of this plugin - make sure you download the correct version.
This is the Moodle 2.0+ version.

Items can be indented and marked as optional or turned into headings; a range of different colours can be used for the items.
Students are presented with a simple chart showing how far they have progressed through the required/optional items and can add their own, private, items to the list.

**Installation**
Unzip the contents of file you downloaded to a temporary folder.
Upload the files to the your moodle server, placing them in the 'moodle/mod/checklist' folder.
Log in as administrator and click on 'Notifications' in the admin area to update the Moodle database, ready to use this plugin.

IMPORTANT: The 'Check-off modules when complete' option now works via cron, by default. This means that there can be a delay of up to 60 seconds, between a student completing an activity and their checklist being updated.
** If you are happy with this delay, then ignore all of these suggested changes below **
If this is not acceptable, then you should make the following changes to the Moodle core code (for extra help with this, look in 'mod/checklist/core_modifications.txt'):
Open the file - moodle/lib/datalib.php

Find the function 'add_to_log', then add these lines to the end of it:

    require_once($CFG->dirroot.'/mod/checklist/autoupdate.php');
    checklist_autoupdate($courseid, $module, $action, $cm, $userid, $url);

Now, open the file - moodle/lib/completionlib.php

Find the function 'update_state', then add these lines, just after the
line '$this->internal_set_data($cm, $current);':

    global $CFG;
    require_once($CFG->dirroot.'/mod/checklist/autoupdate.php');
    checklist_completion_autoupdate($cm->id, $userid, $newstate);

WARNING: This will slow your Moodle site down very slightly.
However, the difference is unlikely to be noticable.

You should also disable the cron updates, by changing the following line in 'mod/checklist/autoupdate.php' (it is at the top of the file):
$CFG->checklist_autoupdate_use_cron = true;
should be changed to:
$CFG->checklist_autoupdate_use_cron = false;

**Usage**
Click on 'Add an activity' and choose 'Checklist'.
Enter all the usual information.
You can optionally allow students to add their own, private items to the list (this will not affect the overall progress, but may help students to keep note of anything extra they need to do).

You can then add items to the list.
Click on the 'tick' to toggle an item between required, optional and heading
Click on the 'edit' icon to change the text.
Click on the 'indent' icons to change the level of indent.
Click on the 'move' icons to move the item up/down one place.
Click on the 'delete' icon to delete the item.
Click on the '+' icon to insert a new item immediately below the current item.

Click on 'Preview', to get some idea of how this will look to students.
Click on 'Results', to see a chart of how the students are currently progressing through the checklist.

Students can now log in, click on the checklist, tick any items they have completed and then click 'Save' to update the database.
If you have allowed them to do so, they can click on 'Start Adding Items', then click on the green '+' icons to insert their own, private items to the list.

If you allow a checklist to be updated by teachers (either exclusively, or in addition to students), it can be updated by doing the following:
1. Click 'Results'
2. Click on the little 'Magnifying glass' icon, beside the student's name
3. Choose Yes / No for each item
4. Click 'Save'
5. (Optional) Click 'Add comments', enter/update/delete a comment against each item, Click 'Save'
5. Click 'View all Progress' to go back to the view with all the students shown.

**Further information**
Moodle plugins database entry: http://moodle.org/mod/data/view.php?d=13&rid=3582
Browse the code: http://cvs.moodle.org/contrib/plugins/mod/checklist
Download the latest version: http://download.moodle.org/download.php/plugins/mod/checklist.zip
Report a bug, or suggest an improvement: http://tracker.moodle.org/browse/CONTRIB/component/10608

**Contact details**
Any questions, suggested improvements (or offers to pay for specific customisations) to:
Davo Smith - davo@davodev.co.uk

