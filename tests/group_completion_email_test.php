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
 * Group email tests
 *
 * @package   mod_checklist
 * @copyright 2019 Andy McGill
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_checklist;

use mod_checklist\local\previous_completions;

/**
 * Class group_completion_email_test
 * @package mod_checklist
 * @covers \mod_checklist\local\previous_completions
 */
class group_completion_email_test extends \advanced_testcase {

    /**
     * @var phpunit_mailer_sink
     */
    protected $mailsink;

    /** @var \stdClass The student object. */
    protected $student;

    /** @var \stdClass The other student object. */
    protected $student2;

    /** @var \stdClass The teacher object. */
    protected $teacher;

    /** @var \stdClass The teacher2 object. */
    protected $teacher2;

    /** @var \stdClass The checklist objects. */
    protected $checklist;

    /** @var \stdClass The course object. */
    protected $course;

    /** @var \stdClass The group object. */
    protected $group;

    /**
     * Set up steps
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function setUp(): void {
        global $DB;

        $this->resetAfterTest();
        unset_config('noemailever');
        $this->mailsink = $this->redirectEmails();
        previous_completions::override_time(null);

        $courserecord = new \stdClass();
        $courserecord->groupmode = SEPARATEGROUPS;
        $courserecord->groupmodeforce = true;
        $courserecord->enablecompletion = 1;
        $this->course = $this->getDataGenerator()->create_course($courserecord);

        // Create a checklist.
        /** @var \mod_checklist_generator $plugingen */
        $plugingen = $this->getDataGenerator()->get_plugin_generator('mod_checklist');
        $params = [
            'course' => $this->course->id,
            'emailoncomplete' => 3, // 3 is teacher and student.
            'completionpercent' => 100,
            'completionpercenttype' => 'percent',
            'completion' => 2, // 2 is complete when completionpercent is reached.
        ];
        $this->checklist = $plugingen->create_instance($params);

        $itemtexts = ['First item', 'Second item', 'Third item'];

        $position = 1;
        foreach ($itemtexts as $text) {
            $item = new \mod_checklist\local\checklist_item([], false);
            $item->checklist = $this->checklist->id;
            $item->userid = 0;
            $item->displaytext = $text;
            $item->position = $position++;
            $item->insert();
        }

        // Create a student who will add data to these checklists.
        $this->student = $this->getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $this->getDataGenerator()->enrol_user($this->student->id, $this->course->id, $studentrole->id);

        // Create a second student who will also add data to these checklists.
        $this->student2 = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($this->student2->id, $this->course->id, $studentrole->id);

        // Create a teacher who should receive email for these checklists.
        $this->teacher = $this->getDataGenerator()->create_user(array('email' => 'ingroupteacher@testing.com'));
        $teacherrole = $DB->get_record('role', ['shortname' => 'teacher']);
        $this->getDataGenerator()->enrol_user($this->teacher->id, $this->course->id, $teacherrole->id);

        // Create a teacher who should not receive email for these checklists.
        $this->teacher2 = $this->getDataGenerator()->create_user(array('email' => 'notingroupteacher@testing.com'));
        $this->getDataGenerator()->enrol_user($this->teacher2->id, $this->course->id, $teacherrole->id);

        // Create a group.
        $this->group = $this->getDataGenerator()->create_group(array('courseid' => $this->course->id));

        // Add the student to the group.
        $this->getDataGenerator()->create_group_member(array('userid' => $this->student->id, 'groupid' => $this->group->id));

        // Add one teacher to the group.
        $this->getDataGenerator()->create_group_member(array('userid' => $this->teacher->id, 'groupid' => $this->group->id));

    }

    /**
     * Tear down steps.
     */
    public function tearDown(): void {
        $this->mailsink->clear();
        $this->mailsink->close();
        unset($this->mailsink);
        previous_completions::override_time(null);
    }

    /**
     * Ensure that for a checklist when course is in SEPARATEGROUPS mode
     * teachers not in the same group as the student will not receive the
     * email but teachers in the same group as the student will receive the email
     */
    public function test_group_completion_emails(): void {
        global $CFG;
        require_once($CFG->dirroot.'/mod/checklist/lib.php');

        // The checklist includes checkmarks from the student for all the items.
        /** @var \mod_checklist\local\checklist_item[] $items */
        $items = \mod_checklist\local\checklist_item::fetch_all(['checklist' => $this->checklist->id], true);
        $items = array_values($items);
        $items[0]->set_checked_student($this->student->id, true);
        $items[1]->set_checked_student($this->student->id, true);
        $items[2]->set_checked_student($this->student->id, true);

        // Test the function.
        checklist_update_grades($this->checklist, $this->student->id);

        $recipients = array_map(function($o) {
            return $o->to;
        }, $this->mailsink->get_messages());

        $this->assertContains('ingroupteacher@testing.com', $recipients,
                              'The teacher in the same group as the student did not receive the completion email');
        $this->assertNotContains('notingroupteacher@testing.com', $recipients,
                                 'The teacher NOT in the same group as the student received the completion email');

        // Test updating the grades again does not send any more emails.
        $this->mailsink->clear();
        checklist_update_grades($this->checklist);
        $this->assertEquals(0, $this->mailsink->count());

        // Test updating the grades for a second student causes them to be emailed.
        $items = \mod_checklist\local\checklist_item::fetch_all(['checklist' => $this->checklist->id], true);
        $items = array_values($items);
        $items[0]->set_checked_student($this->student2->id, true);
        $items[1]->set_checked_student($this->student2->id, true);
        $items[2]->set_checked_student($this->student2->id, true);
        checklist_update_grades($this->checklist);

        $this->assertEquals(1, $this->mailsink->count()); // Email sent to student.
    }

    public function test_repeat_emails(): void {
        global $CFG;
        require_once($CFG->dirroot.'/mod/checklist/lib.php');

        // The checklist includes checkmarks from the student for all the items.
        /** @var \mod_checklist\local\checklist_item[] $items */
        $items = \mod_checklist\local\checklist_item::fetch_all(['checklist' => $this->checklist->id], true);
        $items = array_values($items);
        $items[0]->set_checked_student($this->student->id, true);
        $items[1]->set_checked_student($this->student->id, true);
        $items[2]->set_checked_student($this->student->id, true);

        // Check emails sent out.
        checklist_update_grades($this->checklist, $this->student->id);
        $this->assertEquals(2, $this->mailsink->count());
        $this->mailsink->clear();

        // Update the checklist to incomplete.
        $items[2]->set_checked_student($this->student->id, false);
        checklist_update_grades($this->checklist, $this->student->id);
        $this->assertEquals(0, $this->mailsink->count(), "No emails expected as checklist not completed");

        // Move forward 10 minutes, then mark the checklist as complete again.
        previous_completions::override_time(time() + 10 * MINSECS);
        $items[2]->set_checked_student($this->student->id, true);
        checklist_update_grades($this->checklist, $this->student->id);
        $this->assertEquals(0, $this->mailsink->count(), "No emails expected as checklist last emailed out within an hour");

        // Update the checklist to incomplete.
        $items[2]->set_checked_student($this->student->id, false);
        checklist_update_grades($this->checklist, $this->student->id);
        $this->assertEquals(0, $this->mailsink->count(), "No emails expected as checklist not completed");

        // Move forward 2 hours, then mark the checklist as complete again.
        previous_completions::override_time(time() + 2 * HOURSECS);
        $items[2]->set_checked_student($this->student->id, true);
        checklist_update_grades($this->checklist, $this->student->id);
        $this->assertEquals(2, $this->mailsink->count(), "Emails expected as an hour has passed since the last email notification");
        $this->mailsink->clear();

        // Move forward another 2 hours - should not see any more emails, as checklist state has not changed.
        previous_completions::override_time(time() + 4 * HOURSECS);
        checklist_update_grades($this->checklist, $this->student->id);
        $this->assertEquals(0, $this->mailsink->count(), "No emails expected as not newly completed.");
    }
}
