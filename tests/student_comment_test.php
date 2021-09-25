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

use mod_checklist\local\checklist_comment_student;
use mod_checklist_external;

defined('MOODLE_INTERNAL') || die();

/**
 * Class student_comment_test
 * @package mod_checklist
 */
class student_comment_test extends \advanced_testcase
{
    /**
     * Set up steps
     */
    public function setUp(): void
    {
        $this->resetAfterTest();
    }

    public function test_create_student_comment()
    {
        $this->setAdminUser();
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
        global $CFG, $USER;
        $this->setAdminUser();
        $USER->ignoresesskey = true;
        // Create test checklist with a couple items.
        require_once("$CFG->dirroot/mod/checklist/externallib.php");
        $gen = self::getDataGenerator();
        /** @var \mod_checklist_generator $cgen */
        $cgen = $gen->get_plugin_generator('mod_checklist');

        $c1 = $gen->create_course(['startdate' => strtotime('2019-04-10T12:00:00Z')]);
        $chk = $cgen->create_instance(['course' => $c1->id]);
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
        }

        $cm = get_coursemodule_from_instance('checklist', $chk->id, $c1->id);

        // Want to test events were written to logstore.
        $this->preventResetByRollback();
        set_config('enabled_stores', 'logstore_standard', 'tool_log');
        set_config('buffersize', 0, 'logstore_standard');
        $manager = get_log_manager(true);

        // Create a student comment in this checklist on the second item.
        $params = [
            'cmid' => $cm->id,
            'commenttext' => 'test new comment',
            'checklistitemid' => $checklistitemid,
        ];
        $result = mod_checklist_external::update_student_comment($params);
        $this->assertEquals('1', $result);

        // Assert comment inserted properly with the right data.
        $student_comment = checklist_comment_student::get_record([
            'itemid' => $params['checklistitemid'],
            'usermodified' => $USER->id
        ]);
        $this->assertEquals($USER->id, $student_comment->get('usermodified'));
        $this->assertEquals('test new comment', $student_comment->get('text'));

        // Assert that 'create' event was created.
        $stores = $manager->get_readers();
        /** @var \logstore_standard\log\store $store */
        $store = $stores['logstore_standard'];
        $select = "userid = :userid AND contextlevel = :contextlevel AND contextinstanceid = :contextinstanceid";
        $params = array('userid' => $USER->id, 'contextlevel' => CONTEXT_MODULE, 'contextinstanceid' => $cm->id);
        $events = $store->get_events_select($select, $params, 'timecreated ASC', 0, 1);
        $this->assertCount(1, $events);
        $event = array_shift($events);
        $eventdata = $event->get_data();
        $this->assertEquals('c', $eventdata['crud']);
        $this->assertEquals($student_comment->get('itemid'), $eventdata['objectid']);
        $this->assertEquals($cm->id, $eventdata['contextinstanceid']);
        $this->assertEquals(['commenttext' => 'test new comment'], $eventdata['other']);
        $this->assertEquals('The user with id 2 has created a comment in the checklist with course module id 192000 with text \'test new comment\'',
            $event->get_description());
    }

    public function test_external_function_update()
    {
        global $CFG, $USER;
        $this->setAdminUser();
        $USER->ignoresesskey = true;

        // Create test checklist with a couple items.
        require_once("$CFG->dirroot/mod/checklist/externallib.php");
        $gen = self::getDataGenerator();
        /** @var \mod_checklist_generator $cgen */
        $cgen = $gen->get_plugin_generator('mod_checklist');

        $c1 = $gen->create_course(['startdate' => strtotime('2019-04-10T12:00:00Z')]);
        $chk = $cgen->create_instance(['course' => $c1->id]);
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
            // Create student comments alongside the other items.
            checklist_comment_student::update_or_create_student_comment($checklistitemid, 'testcomment' . $position,
                false);
        }

        $cm = get_coursemodule_from_instance('checklist', $chk->id, $c1->id);

        // Want to test events were written to logstore.
        $this->preventResetByRollback();
        set_config('enabled_stores', 'logstore_standard', 'tool_log');
        set_config('buffersize', 0, 'logstore_standard');
        $manager = get_log_manager(true);

        // Create a student comment in this checklist on the second item.
        $params = [
            'cmid' => $cm->id,
            'commenttext' => 'test update comment',
            'checklistitemid' => $checklistitemid,
        ];
        $result = mod_checklist_external::update_student_comment($params);
        $this->assertEquals('1', $result);

        // Assert comment inserted properly with the right data.
        $student_comment = checklist_comment_student::get_record([
            'itemid' => $params['checklistitemid'],
            'usermodified' => $USER->id
        ]);
        $this->assertEquals($USER->id, $student_comment->get('usermodified'));
        $this->assertEquals('test update comment', $student_comment->get('text'));

        // Assert that 'update' event was created.
        $stores = $manager->get_readers();
        /** @var \logstore_standard\log\store $store */
        $store = $stores['logstore_standard'];
        $select = "userid = :userid AND contextlevel = :contextlevel AND contextinstanceid = :contextinstanceid";
        $params = array('userid' => $USER->id, 'contextlevel' => CONTEXT_MODULE, 'contextinstanceid' => $cm->id);
        $events = $store->get_events_select($select, $params, 'timecreated ASC', 0, 1);
        $this->assertCount(1, $events);
        $event = array_shift($events);
        $eventdata = $event->get_data();
        $this->assertEquals('u', $eventdata['crud']);
        $this->assertEquals($student_comment->get('itemid'), $eventdata['objectid']);
        $this->assertEquals($cm->id, $eventdata['contextinstanceid']);
        $this->assertEquals(['commenttext' => 'test update comment'], $eventdata['other']);
        $this->assertEquals('The user with id 2 has updated a comment in the checklist with course module id 192000 to have text \'test update comment\'',
            $event->get_description());
    }
}
