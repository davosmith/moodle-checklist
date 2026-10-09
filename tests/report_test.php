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

namespace mod_checklist;

use mod_checklist\local\checklist_item;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/checklist/locallib.php');

/**
 * Tests for saving teacher marks from the report page.
 *
 * @package   mod_checklist
 * @copyright 2026 ISB Bayern
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class report_test extends \advanced_testcase {
    /**
     * Submit teacher marks for the given user through the report save handler.
     *
     * @param int $userid
     * @return int id of the checklist item
     */
    private function submit_teacher_mark(int $userid): int {
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $checklist = $this->getDataGenerator()->create_module('checklist', [
            'course' => $course->id,
            'teacheredit' => CHECKLIST_MARKING_TEACHER,
        ]);
        $item = new checklist_item(['checklist' => $checklist->id, 'userid' => 0, 'displaytext' => 'Item', 'position' => 1], false);
        $item->insert();
        if ($userid === 0) {
            $userid = $this->getDataGenerator()->create_and_enrol($course, 'student')->id;
        }

        $this->setUser($teacher);
        $_POST['userids'] = [$userid];
        $_POST['items_' . $userid] = [$item->id => CHECKLIST_TEACHERMARK_YES];
        $chk = new \checklist_class($checklist->cmid, 0, $checklist);
        $method = new \ReflectionMethod($chk, 'updateallteachermarks');
        $method->setAccessible(true);
        $method->invoke($chk);
        return $item->id;
    }

    #[\PHPUnit\Framework\Attributes\Group('baseline')]
    /**
     * Teacher marks for a user who does not participate in the course are ignored.
     *
     * @covers \checklist_class::updateallteachermarks
     */
    public function test_updateallteachermarks_ignores_non_participants(): void {
        global $DB;
        $this->resetAfterTest();
        $outsider = $this->getDataGenerator()->create_user();

        $itemid = $this->submit_teacher_mark($outsider->id);

        $this->assertFalse($DB->record_exists('checklist_check', ['item' => $itemid, 'userid' => $outsider->id]));
    }

    #[\PHPUnit\Framework\Attributes\Group('baseline')]
    /**
     * Teacher marks for an enrolled student are saved.
     *
     * @covers \checklist_class::updateallteachermarks
     */
    public function test_updateallteachermarks_saves_participants(): void {
        global $DB;
        $this->resetAfterTest();

        $itemid = $this->submit_teacher_mark(0);

        $userid = (int) $_POST['userids'][0];
        $this->assertTrue($DB->record_exists('checklist_check', [
            'item' => $itemid,
            'userid' => $userid,
            'teachermark' => CHECKLIST_TEACHERMARK_YES,
        ]));
    }
}
