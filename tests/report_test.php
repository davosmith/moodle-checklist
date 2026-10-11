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
     * Create a course with a teacher-marked checklist containing one item.
     *
     * @param int $groupmode
     * @return array [course, checklist record, item id]
     */
    private function create_checklist(int $groupmode = NOGROUPS): array {
        $course = $this->getDataGenerator()->create_course();
        $checklist = $this->getDataGenerator()->create_module('checklist', [
            'course' => $course->id,
            'teacheredit' => CHECKLIST_MARKING_TEACHER,
            'groupmode' => $groupmode,
        ]);
        $item = new checklist_item(['checklist' => $checklist->id, 'userid' => 0, 'displaytext' => 'Item', 'position' => 1], false);
        $item->insert();
        return [$course, $checklist, $item->id];
    }

    /**
     * Submit teacher marks for the given users as the current user, via the report page.
     *
     * @param \stdClass $checklist
     * @param int $itemid
     * @param int[] $userids
     */
    private function submit_teacher_marks(\stdClass $checklist, int $itemid, array $userids): void {
        global $OUTPUT, $PAGE;
        $PAGE->set_url('/mod/checklist/report.php', ['id' => $checklist->cmid]);
        // The CLI renderer used by unit tests does not return a page footer.
        $OUTPUT = $PAGE->get_renderer('core', null, RENDERER_TARGET_GENERAL);
        $_POST['action'] = 'updateallchecks';
        $_POST['sesskey'] = sesskey();
        $_POST['userids'] = $userids;
        foreach ($userids as $userid) {
            $_POST['items_' . $userid] = [$itemid => CHECKLIST_TEACHERMARK_YES];
        }
        $chk = new \checklist_class($checklist->cmid, 0, $checklist);
        $chk->report();
    }

    /**
     * Check whether a teacher mark has been saved for the user.
     *
     * @param int $itemid
     * @param int $userid
     * @return bool
     */
    private function is_marked(int $itemid, int $userid): bool {
        global $DB;
        return $DB->record_exists('checklist_check', [
            'item' => $itemid,
            'userid' => $userid,
            'teachermark' => CHECKLIST_TEACHERMARK_YES,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Group('baseline')]
    /**
     * Teacher marks are saved for enrolled students, but not for users who do not participate in the course.
     *
     * @covers \checklist_class::updateallteachermarks
     */
    public function test_updateallteachermarks_ignores_non_participants(): void {
        $this->resetAfterTest();
        [$course, $checklist, $itemid] = $this->create_checklist();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $outsider = $this->getDataGenerator()->create_user();
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'editingteacher'));

        $this->submit_teacher_marks($checklist, $itemid, [$student->id, $outsider->id]);

        $this->assertTrue($this->is_marked($itemid, $student->id));
        $this->assertFalse($this->is_marked($itemid, $outsider->id));
    }

    #[\PHPUnit\Framework\Attributes\Group('baseline')]
    /**
     * In separate groups mode, teacher marks are only saved for students in the teacher's group.
     *
     * @covers \checklist_class::updateallteachermarks
     */
    public function test_updateallteachermarks_ignores_other_groups(): void {
        $this->resetAfterTest();
        [$course, $checklist, $itemid] = $this->create_checklist(SEPARATEGROUPS);
        $owngroup = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $othergroup = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $ownstudent = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $otherstudent = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->getDataGenerator()->create_group_member(['groupid' => $owngroup->id, 'userid' => $teacher->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $owngroup->id, 'userid' => $ownstudent->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $othergroup->id, 'userid' => $otherstudent->id]);
        $this->setUser($teacher);

        $this->submit_teacher_marks($checklist, $itemid, [$ownstudent->id, $otherstudent->id]);

        $this->assertTrue($this->is_marked($itemid, $ownstudent->id));
        $this->assertFalse($this->is_marked($itemid, $otherstudent->id));
    }

    #[\PHPUnit\Framework\Attributes\Group('baseline')]
    /**
     * A user who can only view mentee reports can only save teacher marks for their mentees.
     *
     * @covers \checklist_class::updateallteachermarks
     */
    public function test_updateallteachermarks_ignores_non_mentees(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $checklist, $itemid] = $this->create_checklist();
        $mentor = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $mentee = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $teacherroleid = $DB->get_field('role', 'id', ['shortname' => 'teacher'], MUST_EXIST);
        $coursecontext = \context_course::instance($course->id);
        assign_capability('mod/checklist:viewreports', CAP_PROHIBIT, $teacherroleid, $coursecontext->id);
        assign_capability('mod/checklist:viewmenteereports', CAP_ALLOW, $teacherroleid, $coursecontext->id);
        role_assign($teacherroleid, $mentor->id, \context_user::instance($mentee->id)->id);
        $this->setUser($mentor);

        $this->submit_teacher_marks($checklist, $itemid, [$mentee->id, $student->id]);

        $this->assertTrue($this->is_marked($itemid, $mentee->id));
        $this->assertFalse($this->is_marked($itemid, $student->id));
    }
}
