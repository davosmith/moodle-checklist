Checklist module
================

==Introduction==
This is a Moodle plugin for Moodle 1.9 & 2.x that allows a teacher to create a checklist for their students to work through.
The teacher can monitor all the student's progress, as they tick off each of the items in the list.
Note: This is the Moodle 2.x version.

Items can be indented and marked as optional or turned into headings; a range of different colours can be used for the items.
Students are presented with a simple chart showing how far they have progressed through the required/optional items and can add their own, private, items to the list.

==Changes==

* 2015-05-09 - Moodle 2.9 compatibility fixes
* 2015-03-02 - Fix item output so that multilang filters will work
* 2015-02-21 - Add automated testing. Fix code to prevent multiple emails when same checklist completed multiple times by the same student within an hour.
* 2015-02-19 - Setting 'max grade' to 0 in the checklist removes it from the gradebook
* 2014-10-26 - Option to hide checklists you cannot update from 'My home' (thanks to Syxton); fix PostgreSQL compatibility with autoupdate.
* 2014-07-06 - Fix toggle row/column buttons. Update version.php.
* 2014-05-31 - Add toggle row / toggle column buttons to report view - developed by Simon Bertoli
* 2014-05-31 - Add Moodle 2.7 event logging; fix Postgres compatibility (Tony Butler); prevent teachers seeing student reports when they cannot access any groups; fix centering of report headings (Syxton).
* 2014-01-15 - Fix compatibility of MS SQL with 2.6 version of plugin.
* 2013-19-11 - Moodle 2.6 compatibility fixes (+ correction to this fix)
* 2013-11-11 - Cope with empty section names
* 2013-07-30 - Fix editing of item indents
* 2013-05-22 - 'Display description on course page' + (old)IE compatibility fix
* 2013-05-06 - Fix Moodle 2.4 compatibility regression
* 2013-04-24 - Minor fixes for Moodle 2.5 compatibility
* 2013-04-09 - Allow checklists to import current section when they are located in an 'orphaned' section, add 'questionnaire' + 'assign' to autoupdate list
* 2013-03-01 - Fixed the backup & restore of items linked to course modules.
* 2013-01-04 - Option to email students when their checklists are complete - added by Andriy Semenets
* 2013-01-03 - Fixed the 'show course modules in checklist' feature in Moodle 2.4
* 2012-12-07 - Moodle 2.4 compatibility fixes
* 2012-10-09 - Fixed email sending when checklists are complete (thanks to Longfei Yu for the bug report + fix)
* 2012-09-20 - CONTRIB-3921: broken images in intro text; CONTRIB-3904: error when resetting courses; CONTRIB-3916: checklists can be hidden from 'My Moodle' (either all checklists, or just completed checklists); issue with checklists updating from 'completion' fixed; CONTRIB-3897: teachers able to see who last updated the teacher mark
* 2012-09-19 - Split the 3 plugins (mod / block / grade report) into separate repos for better maintenance; added 'spinner' when updating server
* 2012-08-25 - Minor fix to grade update function
* 2012-08-06 - Minor fix to reduce chance of hitting max_input_vars limits when updated all student's checkmarks
* 2012-07-07 - Improved progress bar styling; Improved debugging of automatic updates (see below); Fixed minor debug warnings
* 2012-04-07 - mod/checklist:addinstance capability added (for M2.3); Russian / Ukranian translations from Andriy Semenets
* 2012-03-05 - Bug fix: grades not updating when new items added to a course (with 'import course activities' on)
* 2012-01-27 - French translation from Luiggi Sansonetti
* 2012-01-02 - Minor tweaks to improve Moodle 2.2+ compatibility (optional_param_array / context_module::instance )
* 2012-01-02 - CONTRIB-2979: remembers report settings (sort order, etc.) until you log out; CONTRIB-3308 - 'viewmenteereport' capability, allowing users to view reports of users they are mentors for

==Installation==
The checklist block and grade report are separate, optional plugins that can be downloaded from:
http://moodle.org/plugins/view.php?plugin=block_checklist
http://moodle.org/plugins/view.php?plugin=gradeexport_checklist

1. Unzip the contents of file you downloaded to a temporary folder.
2. Upload the files to the your moodle server, placing the 'mod/checklist' files in the '[moodlefolder]/mod/checklist', (optionally) the 'blocks/checklist' files in the '[moodlefolder]/blocks/checklist' folder and (optionally) the 'grade/export/checklist' files in the '[moodlefolder]/grade/export/checklist' folder.
3. Log in as administrator and click on 'Notifications' in the admin area to update the Moodle database, ready to use this plugin.

IMPORTANT: The 'Check-off modules when complete' option now works via cron, by default. This means that there can be a delay of up to 60 seconds (or more - depending on how often your site runs 'cron' updates), between a student completing an activity and their checklist being updated.
If you are not happy with this delay, then make the changes found in the file core_modifications.txt

Note: if you are upgrading from a previous version, please delete the file 'mod/checklist/settings.php' from the server, as it is no longer needed.

==Problems with automatic update?==

Whilst automatic updates are working fine in all situations I have tested, there have been some reports of these not updating check-marks correctly on some sites.
If this is the case on your site, there are a couple of things to try, before contacting me:
1. Make sure the checklist is set to 'Student only' - it is the student mark that is automatically updated, if this is not displayed, you won't see any changes.
2. Make sure cron updates are running on your Moodle server.
3. Edit [moodledir]/mod/checklist/autoupdate.php and remove the '//' from the start of the line 'define("DEBUG_CHECKLIST_AUTOUPDATE", 1)'. Run a manual cron update ( http://[siteurl]/admin/cron.php ) and check the detailed feedback for the checklist module.

==Adding a checklist block==
(Optional plugin)
1. Click 'Turn editing on', in a course view.
2. Under 'blocks', choose 'Checklist'
3. Click on the 'Edit' icon in the new block to set which  checklist to display and (optionally) which group of users to display.

==Exporting checklist progress (Excel)==
(Optional plugin)
1. In a course, click 'Grades'
2. From the dropdown menu, choose 'Export => Checklist Export'
3. Choose the checklist you want to export and click 'Export Excel'
If you want to change the user information that is included in the export ('First name', 'Surname', etc.), then edit the file 'grade/export/checklist/columns.php' - instructions can be found inside the file itself.

==Usage==
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

==Further information==
Moodle plugins database entry: http://moodle.org/plugins/view.php?plugin=mod_checklist
Report a bug, or suggest an improvement: http://tracker.moodle.org/browse/CONTRIB/component/10608

==Contact details==
Any questions, suggested improvements to:
Davo Smith - moodle@davosmith.co.uk
Any enquiries about custom development to Synergy Learning: http://www.synergy-learning.com

