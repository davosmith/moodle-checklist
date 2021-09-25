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
 * @package   mod_checklist
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_checklist;

use context_module;
use core\log\manager;
use logstore_standard\log\store;
use mod_checklist\local\checklist_comment_student;
use mod_checklist\local\checklist_item;
use mod_checklist_external;
use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Class student_comment_test
 * @package mod_checklist
 */
class student_comment_test extends \advanced_testcase
{
    /** @var stdClass The student object. */
    protected $student;

    /** @var stdClass The course module object. */
    protected $cm;

    /** @var store The log manager object. */
    protected $store;

    /** @var checklist_item[] checklist items */
    protected $items;
    /**
     * Set up steps
     */
    public function setUp(): void
    {
        global $CFG, $USER, $DB;
        $this->resetAfterTest();
        // Create test checklist with a couple items.
        require_once("$CFG->dirroot/mod/checklist/externallib.php");
        $gen = self::getDataGenerator();
        /** @var \mod_checklist_generator $cgen */
        $cgen = $gen->get_plugin_generator('mod_checklist');

        $c1 = $gen->create_course(['startdate' => strtotime('2019-04-10T12:00:00Z')]);
        $chk = $cgen->create_instance(['course' => $c1->id]);

        // Create a student who will add data to these checklists.
        $this->student = $gen->create_user();
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $gen->enrol_user($this->student->id, $c1->id, $studentrole->id);
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
            $item = new \mod_checklist\local\checklist_item();
            $item->checklist = $chk->id;
            $item->userid = 0;
            $item->displaytext = $iteminfo->displaytext;
            $item->position = $position++;
            $item->duetime = $iteminfo->duetime ?? null;
            $checklistitemid = $item->insert();
            $this->items[] = $item;
            // Create student comments alongside the other items.
            checklist_comment_student::update_or_create_student_comment($checklistitemid, 'testcomment' . $position, false);
        }

        $this->cm = get_coursemodule_from_instance('checklist', $chk->id, $c1->id);
        $context = context_module::instance($this->cm->id);
        $this->assertTrue(has_capability('mod/checklist:updateown', $context));

        // Want to test events were written to logstore.
        $this->preventResetByRollback();
        set_config('enabled_stores', 'logstore_standard', 'tool_log');
        set_config('buffersize', 0, 'logstore_standard');
        $manager = get_log_manager(true);
        $stores = $manager->get_readers();
        $this->store = $stores['logstore_standard'];

        $USER->ignoresesskey = true;
    }

    public function test_create_student_comment()
    {
        $data = (object)[
            'text' => 'this is my comment',
            'itemid' => 1,
        ];
        $comment = new checklist_comment_student(0, $data);
        $result = $comment->create();
        $this->assertIsNumeric($result->get('id'));
    }

    public function test_update_student_comment_new()
    {
        $result = checklist_comment_student::update_or_create_student_comment(1, 'some comment', null);
        $this->assertEquals(true, $result);
    }

    public function test_external_function_create()
    {
        // Delete one of the comments we made in set up so we can create now.
        $student_comment = checklist_comment_student::get_record(['itemid' => $this->items[1]->id, 'usermodified' => $this->student->id]);
        $student_comment->delete();

        // Create a student comment in this checklist on the second item.
        $params = [
            'cmid' => $this->cm->id,
            'commenttext' => 'test new comment',
            'checklistitemid' => $this->items[1]->id,
        ];
        $result = mod_checklist_external::update_student_comment($params);
        $this->assertEquals('1', $result);

        // Assert comment inserted properly with the right data.
        $student_comment = checklist_comment_student::get_record([
            'itemid' => $params['checklistitemid'],
            'usermodified' => $this->student->id
        ]);
        $this->assertEquals($this->student->id, $student_comment->get('usermodified'));
        $this->assertEquals('test new comment', $student_comment->get('text'));

        // Assert that 'create' event was created.
        $select = "userid = :userid AND contextlevel = :contextlevel AND contextinstanceid = :contextinstanceid";
        $params = array('userid' => $this->student->id, 'contextlevel' => CONTEXT_MODULE, 'contextinstanceid' => $this->cm->id);
        $events = $this->store->get_events_select($select, $params, 'timecreated ASC', 0, 1);
        $this->assertCount(1, $events);
        $event = array_shift($events);
        $eventdata = $event->get_data();
        $this->assertEquals('c', $eventdata['crud']);
        $this->assertEquals($student_comment->get('itemid'), $eventdata['objectid']);
        $this->assertEquals($this->cm->id, $eventdata['contextinstanceid']);
        $this->assertEquals(['commenttext' => 'test new comment'], $eventdata['other']);
        $this->assertEquals('The user with id ' . $this->student->id . ' has created a comment in the checklist with course module id ' . $this->cm->id . ' with text \'test new comment\'', $event->get_description());
    }

    public function test_external_function_update()
    {
        global $CFG;
        // Create test checklist with a couple items.
        require_once("$CFG->dirroot/mod/checklist/externallib.php");
        // Create a student comment in this checklist on the second item.
        $params = [
            'cmid' => $this->cm->id,
            'commenttext' => 'test update comment',
            'checklistitemid' => $this->items[1]->id,
        ];
        $result = mod_checklist_external::update_student_comment($params);
        $this->assertEquals('1', $result);

        // Assert comment inserted properly with the right data.
        $student_comment = checklist_comment_student::get_record(['itemid' => $params['checklistitemid'], 'usermodified' => $this->student->id]);
        $this->assertEquals($this->student->id, $student_comment->get('usermodified'));
        $this->assertEquals('test update comment', $student_comment->get('text'));

        // Assert that 'update' event was created.
        $select = "userid = :userid AND contextlevel = :contextlevel AND contextinstanceid = :contextinstanceid";
        $params = array('userid' => $this->student->id, 'contextlevel' => CONTEXT_MODULE, 'contextinstanceid' => $this->cm->id);
        $events = $this->store->get_events_select($select, $params, 'timecreated ASC', 0, 1);
        $this->assertCount(1, $events);
        $event = array_shift($events);
        $eventdata = $event->get_data();
        $this->assertEquals('u', $eventdata['crud']);
        $this->assertEquals($student_comment->get('itemid'), $eventdata['objectid']);
        $this->assertEquals($this->cm->id, $eventdata['contextinstanceid']);
        $this->assertEquals(['commenttext' => 'test update comment'], $eventdata['other']);
        $this->assertEquals('The user with id ' . $this->student->id .' has updated a comment in the checklist with course module id ' . $this->cm->id .' to have text \'test update comment\'', $event->get_description());
    }
}
