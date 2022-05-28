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
 * Test student comments
 *
 * @copyright  2021 Kristian Ringer <kristian.ringer@gmail.com>
 * @package   mod_checklist
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_checklist;

use checklist_class;
use context_module;
use logstore_standard\log\store;
use mod_checklist\external\update_student_comment;
use mod_checklist\local\checklist_comment_student;
use mod_checklist\local\checklist_item;
use stdClass;

/**
 * Class student_comment_test
 * @package mod_checklist
 * @covers \mod_checklist\local\checklist_comment_student
 */
class student_comment_test extends \advanced_testcase {
    /** @var stdClass The student object. */
    protected $student;

    /** @var stdClass The teacher object. */
    protected $teacher;

    /** @var stdClass The course module object. */
    protected $cm;

    /** @var store The log manager object. */
    protected $store;

    /** @var checklist_item[] checklist items */
    protected $items;
    /**
     * Set up steps
     */
    public function setUp(): void {
        global $USER, $DB;
        $this->resetAfterTest();
        // Create test checklist with a couple items.
        $gen = self::getDataGenerator();
        /** @var \mod_checklist_generator $cgen */
        $cgen = $gen->get_plugin_generator('mod_checklist');

        $c1 = $gen->create_course(['startdate' => strtotime('2019-04-10T12:00:00Z')]);
        $this->cm = $cgen->create_instance(['course' => $c1->id, 'studentcomments' => 1]);

        // Create a student who will add data to these checklists.
        $this->student = $gen->create_user();
        $this->teacher = $gen->create_user();
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $teacherrole = $DB->get_record('role', ['shortname' => 'teacher']);
        $gen->enrol_user($this->student->id, $c1->id, $studentrole->id);
        $gen->enrol_user($this->teacher->id, $c1->id, $teacherrole->id);
        $this->setUser($this->student);

        $iteminfos = [
            (object)[
                'displaytext' => 'Item 1',
                'duetime' => strtotime('2019-05-14T12:00:00Z'),
            ],
            (object)[
                'displaytext' => 'Item 2',
            ],
        ];
        $position = 1;
        foreach ($iteminfos as $iteminfo) {
            $item = new checklist_item();
            $item->checklist = $this->cm->id;
            $item->userid = 0;
            $item->displaytext = $iteminfo->displaytext;
            $item->position = $position++;
            $item->duetime = $iteminfo->duetime ?? null;
            $checklistitemid = $item->insert();
            $this->items[] = $item;
            // Create student comments alongside the other items.
            checklist_comment_student::update_or_create_student_comment($checklistitemid, 'testcomment' . $position);
        }
        // Test events were written to logstore.
        $this->preventResetByRollback();
        set_config('enabled_stores', 'logstore_standard', 'tool_log');
        set_config('buffersize', 0, 'logstore_standard');
        $manager = get_log_manager(true);
        $stores = $manager->get_readers();
        $this->store = $stores['logstore_standard'];

        $USER->ignoresesskey = true;
    }

    public function test_create_student_comment() {
        $data = (object)[
            'text' => 'this is my comment',
            'itemid' => 1,
        ];
        $comment = new checklist_comment_student(0, $data);
        $result = $comment->create();
        $this->assertIsNumeric($result->get('id'));
    }

    public function test_update_student_comment_new() {
        $result = checklist_comment_student::update_or_create_student_comment(1, 'some comment');
        $this->assertEquals(true, $result);
    }

    public function test_external_function_create() {
        // Delete one of the comments we made in set up so we can create now.
        $studentcomment = checklist_comment_student::get_record([
            'itemid' => $this->items[1]->id,
            'usermodified' => $this->student->id
        ]);
        $studentcomment->delete();

        // Create a student comment in this checklist on the second item.
        $params = [
            'cmid' => $this->cm->cmid,
            'commenttext' => 'test new comment',
            'checklistitemid' => $this->items[1]->id,
        ];
        $result = update_student_comment::execute($params);
        $this->assertEquals('1', $result);

        // Assert comment inserted properly with the right data.
        $studentcomment = checklist_comment_student::get_record([
            'itemid' => $params['checklistitemid'],
            'usermodified' => $this->student->id
        ]);
        $this->assertEquals($this->student->id, $studentcomment->get('usermodified'));
        $this->assertEquals('test new comment', $studentcomment->get('text'));

        // Assert that 'create' event was created.
        $select = "userid = :userid AND contextlevel = :contextlevel AND contextinstanceid = :contextinstanceid";
        $params = array('userid' => $this->student->id, 'contextlevel' => CONTEXT_MODULE, 'contextinstanceid' => $this->cm->cmid);
        $events = $this->store->get_events_select($select, $params, 'timecreated ASC', 0, 1);
        $this->assertCount(1, $events);
        $event = array_shift($events);
        $eventdata = $event->get_data();
        $this->assertEquals('c', $eventdata['crud']);
        $this->assertEquals($studentcomment->get('itemid'), $eventdata['objectid']);
        $this->assertEquals($this->cm->cmid, $eventdata['contextinstanceid']);
        $this->assertEquals(['commenttext' => 'test new comment'], $eventdata['other']);
        $eventtext = 'The user with id ' . $this->student->id . ' has created a comment in ';
        $eventtext .= 'the checklist with course module id ' . $this->cm->cmid . ' with text \'test new comment\'';
        $this->assertEquals($eventtext, $event->get_description());
    }

    public function test_external_function_update() {
        // Create a student comment in this checklist on the second item.
        $params = [
            'cmid' => $this->cm->cmid,
            'commenttext' => 'test update comment',
            'checklistitemid' => $this->items[1]->id,
        ];
        $result = update_student_comment::execute($params);
        $this->assertEquals('1', $result);

        // Assert comment inserted properly with the right data.
        $studentcomment = checklist_comment_student::get_record([
            'itemid' => $params['checklistitemid'],
            'usermodified' => $this->student->id
        ]);
        $this->assertEquals($this->student->id, $studentcomment->get('usermodified'));
        $this->assertEquals('test update comment', $studentcomment->get('text'));

        // Assert that 'update' event was created.
        $select = "userid = :userid AND contextlevel = :contextlevel AND contextinstanceid = :contextinstanceid";
        $params = array('userid' => $this->student->id, 'contextlevel' => CONTEXT_MODULE, 'contextinstanceid' => $this->cm->cmid);
        $events = $this->store->get_events_select($select, $params, 'timecreated ASC', 0, 1);
        $this->assertCount(1, $events);
        $event = array_shift($events);
        $eventdata = $event->get_data();
        $this->assertEquals('u', $eventdata['crud']);
        $this->assertEquals($studentcomment->get('itemid'), $eventdata['objectid']);
        $this->assertEquals($this->cm->cmid, $eventdata['contextinstanceid']);
        $this->assertEquals(['commenttext' => 'test update comment'], $eventdata['other']);

        $eventtext = 'The user with id ' . $this->student->id . ' has updated a comment in ';
        $eventtext .= 'the checklist with course module id ' . $this->cm->cmid . ' to have text \'test update comment\'';
        $this->assertEquals($eventtext, $event->get_description());
    }

    public function test_can_add_student_comments() {
        global $DB;
        $checklistclass = new checklist_class($this->cm->cmid, $this->student->id);
        $this->assertTrue($checklistclass->canaddstudentcomments());

        // Remove the capability 'updateown'.
        $role = $DB->get_record('role', ['shortname' => 'student']);
        unassign_capability('mod/checklist:updateown', $role->id);
        $this->assertFalse($checklistclass->canaddstudentcomments());

        // Should not be able to add student comments if user is a teacher viewing someone else's checklist.
        $this->setUser($this->teacher);
        $context = context_module::instance($this->cm->cmid);
        $role = $DB->get_record('role', ['shortname' => 'teacher']);
        assign_capability('mod/checklist:updateown', CAP_ALLOW, $role->id, $context->id);
        $this->assertFalse($checklistclass->canaddstudentcomments());

        // When previewing, we have a user id of 0, and we should be able to preview the student comment fields.
        $checklistclass = new checklist_class($this->cm->cmid, 0);
        $this->assertTrue($checklistclass->canaddstudentcomments());

        // Teacher must still have the updateown capability when previewing to see student comments.
        $role = $DB->get_record('role', ['shortname' => 'teacher']);
        unassign_capability('mod/checklist:updateown', $role->id);
        $this->assertFalse($checklistclass->canaddstudentcomments());
    }
}
