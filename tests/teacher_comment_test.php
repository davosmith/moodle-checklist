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

/**
 * Tests for saving teacher comments.
 *
 * @package   mod_checklist
 * @copyright 2026 ISB Bayern
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \checklist_class
 */
final class teacher_comment_test extends \advanced_testcase {
    /**
     * Create a checklist with teacher comments and one item.
     *
     * @param \stdClass $course
     * @return array [checklist record, item]
     */
    private function create_checklist_with_item(\stdClass $course): array {
        $checklist = $this->getDataGenerator()->get_plugin_generator('mod_checklist')->create_instance(
            ['course' => $course->id, 'teachercomments' => 1]
        );
        $item = new checklist_item();
        $item->checklist = $checklist->id;
        $item->userid = 0;
        $item->displaytext = 'Item';
        $item->position = 1;
        $item->insert();
        return [$checklist, $item];
    }

    /**
     * Save teacher comments for a student as submitted on the report page.
     *
     * @param \stdClass $checklist
     * @param int $studentid
     * @param array $comments item id => comment text
     */
    private function save_teacher_comments(\stdClass $checklist, int $studentid, array $comments): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/checklist/locallib.php');
        $_POST['teachercomment'] = $comments;
        $checklistclass = new \checklist_class($checklist->cmid, $studentid);
        (new \ReflectionMethod($checklistclass, 'updateteachermarks'))->invoke($checklistclass);
    }

    #[\PHPUnit\Framework\Attributes\Group('baseline')]
    /**
     * Comments for items of another checklist are not stored.
     */
    public function test_comment_on_foreign_item_is_ignored(): void {
        global $DB;
        $this->resetAfterTest();
        $own = $this->getDataGenerator()->create_course();
        $foreign = $this->getDataGenerator()->create_course();
        [$checklist] = $this->create_checklist_with_item($own);
        [, $foreignitem] = $this->create_checklist_with_item($foreign);
        $student = $this->getDataGenerator()->create_and_enrol($own, 'student');
        $this->setUser($this->getDataGenerator()->create_and_enrol($own, 'editingteacher'));

        $this->save_teacher_comments($checklist, $student->id, [$foreignitem->id => 'Injected']);

        $this->assertFalse($DB->record_exists('checklist_comment', ['itemid' => $foreignitem->id]));
    }

    #[\PHPUnit\Framework\Attributes\Group('baseline')]
    /**
     * Comments for items of the own checklist are stored.
     */
    public function test_comment_on_own_item_is_saved(): void {
        global $DB;
        $this->resetAfterTest();
        $own = $this->getDataGenerator()->create_course();
        [$checklist, $item] = $this->create_checklist_with_item($own);
        $student = $this->getDataGenerator()->create_and_enrol($own, 'student');
        $this->setUser($this->getDataGenerator()->create_and_enrol($own, 'editingteacher'));

        $this->save_teacher_comments($checklist, $student->id, [$item->id => 'Well done']);

        $this->assertSame(
            'Well done',
            $DB->get_field('checklist_comment', 'text', ['itemid' => $item->id, 'userid' => $student->id])
        );
    }
}
