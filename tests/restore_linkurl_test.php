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
 * Test the link URL of restored checklist items
 *
 * @package   mod_checklist
 * @copyright 2026 ISB Bayern
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_checklist;

/**
 * Class restore_linkurl_test
 * @package mod_checklist
 * @covers \restore_checklist_activity_task::after_restore
 */
final class restore_linkurl_test extends \advanced_testcase {
    /**
     * Create a checklist with one item per link URL, duplicate it and return the restored link URLs.
     *
     * @param string[] $linkurls
     * @return string[] Restored link URLs, keyed by display text
     */
    private function duplicate_with_links(array $linkurls): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = self::getDataGenerator()->create_course();
        $checklist = self::getDataGenerator()->create_module('checklist', ['course' => $course->id]);
        foreach ($linkurls as $text => $linkurl) {
            $DB->insert_record('checklist_item', [
                'checklist' => $checklist->id,
                'displaytext' => $text,
                'position' => 1,
                'linkurl' => $linkurl,
            ]);
        }

        $newcm = duplicate_module($course, get_coursemodule_from_id('checklist', $checklist->cmid));
        return $DB->get_records_menu('checklist_item', ['checklist' => $newcm->instance], '', 'displaytext, linkurl');
    }

    #[\PHPUnit\Framework\Attributes\Group('baseline')]
    /**
     * A script URL in the backup must not survive the restore.
     */
    public function test_restore_drops_script_linkurl(): void {
        $restored = $this->duplicate_with_links(['script' => 'javascript:%0aalert(document.domain)']);

        $this->assertNull($restored['script']);
    }

    #[\PHPUnit\Framework\Attributes\Group('baseline')]
    /**
     * External and same-site links (encoded in the backup) are restored unchanged.
     */
    public function test_restore_keeps_valid_linkurls(): void {
        global $CFG;
        $external = 'https://example.org/page.php?a=1&b=2';
        $samesite = $CFG->wwwroot . '/course/view.php?id=' . SITEID;

        $restored = $this->duplicate_with_links(['external' => $external, 'samesite' => $samesite]);

        $this->assertSame($external, $restored['external']);
        $this->assertSame($samesite, $restored['samesite']);
    }
}
