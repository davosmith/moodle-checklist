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

defined('MOODLE_INTERNAL') || die();

/**
 * Class student_comment_test
 * @package mod_checklist
 */
class student_comment_test extends \advanced_testcase {
    /**
     * Set up steps
     */
    public function setUp(): void {
        $this->resetAfterTest();
    }

    public function test_create_student_comment()
    {
        global $USER;
        $this->setAdminUser();
        $data = (object)[
            'text' => 'this is my comment',
            'userid' => $USER->id,
            'itemid' => 1,
        ];
        $comment = new checklist_comment_student(0, $data);
        $result = $comment->create();
        $this->assertIsNumeric($result->get('id'));
    }

    public function test_update_student_comment_new()
    {
        $result = checklist_comment_student::update_student_comment(1, 'some comment', 2);
        $this->assertEquals(true, $result);
    }
}
