Checklist module
================

==Introduction==
This is a Moodle plugin for Moodle 1.9 & 2.0+ that allows a teacher to create a checklist for their students to work through.
The teacher can monitor all the student's progress, as they tick off each of the items in the list.
Note: This is the Moodle 1.9 version of the plugin.

Items can be indented and marked as optional or turned into headings; a range of different colours can be used for the items.
Students are presented with a simple chart showing how far they have progressed through the required/optional items and can add their own, private, items to the list.

==Installation==
(Note, due to changes in the way the the Moodle plugins database works, if you have downloaded this from the Moodle.org website, you will need to separately download the 'checklist block' and 'checklist grade export' plugins)

1. Unzip the contents of file you downloaded to a temporary folder.
2. Upload the files to the your moodle server, placing the 'mod/checklist' files in the '[moodlefolder]/mod/checklist' folder, (optionally) the 'blocks/checklist' files in the '[moodlefolder]/blocks/checklist' folder and (optionally) the 'grade/export/checklist' files into the '[moodlefolder]/grade/export/checklist' folder.
3. Log in as administrator and click on 'Notifications' in the admin area to update the Moodle database, ready to use this plugin.

IMPORTANT: The 'Check-off modules when complete' option now works via cron, by default. This means that there can be a delay of up to 60 seconds, between a student completing an activity and their checklist being updated.

If you are not happy with this delay, then make the changes found in the file core_modifications.txt

If you are upgrading from a previous version, please remove the file 'mod/checklist/settings.php' from your server, as it is no longer needed.


==Adding a checklist block==
1. Click 'Turn editing on', in a course view.
2. Under 'blocks', choose 'Checklist'
3. Click on the 'Edit' icon in the new block to set the checklist to  display and (optionally) which group of users to display.

==Exporting checklist progress (Excel)==
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
Moodle plugins database entry: http://moodle.org/mod/data/view.php?d=13&rid=3582
Report a bug, or suggest an improvement: http://tracker.moodle.org/browse/CONTRIB/component/10608

==Contact details==
Any questions, suggested improvements (or offers to pay for specific customisations) to:
davo@davodev.co.uk
http://www.davodev.co.uk

