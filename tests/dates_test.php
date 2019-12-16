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
 * Test date handling
 *
 * @package   mod_checklist
 * @copyright 2019 Davo Smith, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_checklist;

defined('MOODLE_INTERNAL') || die();

class dates_test extends \advanced_testcase {
    public function setUp() {
        $this->resetAfterTest();
    }

    public function test_import() {
        global $DB, $CFG;
        require_once($CFG->dirroot.'/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot.'/backup/util/includes/restore_includes.php');

        $gen = self::getDataGenerator();
        /** @var \mod_checklist_generator $cgen */
        $cgen = $gen->get_plugin_generator('mod_checklist');

        // Create 2 courses, with differening start dates.
        $c1 = $gen->create_course(['startdate' => strtotime('2019-04-10T12:00:00Z')]);
        $c2 = $gen->create_course(['startdate' => strtotime('2019-04-11T12:00:00Z')]);

        // Create a checklist in course 1, with some dates associated with the items.
        $chk = $cgen->create_instance(['course' => $c1->id]);
        $iteminfos = [
            (object)[
                'displaytext' => 'Item 1',
                'duetime' => strtotime('2019-05-14T12:00:00Z'),
            ],
            (object)[
                'displaytext' => 'Item 2',
                'duetime' => strtotime('2019-05-15T12:30:00Z'),
            ],
            (object)[
                'displaytext' => 'Item 3',
            ],
        ];
        $position = 1;
        foreach ($iteminfos as $iteminfo) {
            $item = new \mod_checklist\local\checklist_item();
            $item->checklist = $chk->id;
            $item->userid = 0;
            $item->displaytext = $iteminfo->displaytext;
            $item->position = $position++;
            $item->duetime = isset($iteminfo->duetime) ? $iteminfo->duetime : null;
            $item->insert();
        }

        // Import the checklist from c1 to c2.
        $bc = new \backup_controller(\backup::TYPE_1COURSE, $c1->id, \backup::FORMAT_MOODLE, \backup::INTERACTIVE_NO,
                                     \backup::MODE_IMPORT, get_admin()->id);
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();
        make_backup_temp_directory($backupid, false);
        $rc = new \restore_controller($backupid, $c2->id, \backup::INTERACTIVE_NO, \backup::MODE_IMPORT,
                                      get_admin()->id, \backup::TARGET_EXISTING_ADDING);
        $setting = $rc->get_plan()->get_setting('course_startdate');
        $setting->set_value($c2->startdate);
        $rc->execute_precheck();
        $rc->execute_plan();
        $rc->destroy();

        // Check the checklist in c2.
        $chk2 = $DB->get_record('checklist', ['course' => $c2->id], '*', MUST_EXIST);
        $items = $DB->get_records('checklist_item', ['checklist' => $chk2->id], 'position');

        $this->assertCount(3, $items);
        list($item1, $item2, $item3) = array_values($items);

        $this->assertEquals(strtotime('2019-05-15T12:00:00Z'), $item1->duetime, 'Actual date: '.date('c', $item1->duetime));
        $this->assertEquals(strtotime('2019-05-16T12:30:00Z'), $item2->duetime, 'Actual date: '.date('c', $item2->duetime));
        $this->assertEquals(0, $item3->duetime);
    }
}
