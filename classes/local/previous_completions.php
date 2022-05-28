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
 * Keep track of which users have previously completed the checklist
 *
 * @package   mod_checklist
 * @copyright 2022 Davo Smith
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_checklist\local;

/**
 * previous_completions class.
 */
class previous_completions {

    /** @var int minimum time that must elapse before sending out another completion email */
    private const MAX_EMAIL_FREQUENCY = HOURSECS;

    /** @var object[] */
    private $comps;

    /** @var int|null override current time for unit tests */
    private static $timeoverride;

    /** @var bool flag that we are doing a bulk update of all grades, so do not want completion emails/log entries */
    private static $isbulkupdate = false;

    /**
     * For unit tests, override the current timestamp
     * @param int|null $timeoverride
     * @return void
     */
    public static function override_time(?int $timeoverride): void {
        if (!PHPUNIT_TEST) {
            throw new \coding_exception("Only unit tests can call override_time()");
        }
        self::$timeoverride = $timeoverride;
    }

    /**
     * Returns the current timestamp, unless overridden by a unit test.
     * @return int
     */
    private static function time(): int {
        if (PHPUNIT_TEST && self::$timeoverride) {
            return self::$timeoverride;
        }
        return time();
    }

    /**
     * Mark a bulk update in progress (of all checklists), so we do not want to send out emails/log entries.
     * @param bool $state
     * @return void
     */
    public static function set_bulk_update(bool $state): void {
        self::$isbulkupdate = $state;
    }

    /**
     * Is there a bulk update in progress?
     * @return bool
     */
    public static function is_bulk_update(): bool {
        return self::$isbulkupdate;
    }

    /**
     * Class constructor.
     * @param int $checklistid
     * @param int[] $userids
     */
    public function __construct(int $checklistid, array $userids) {
        global $DB;
        if (!$userids) {
            return;
        }
        // Load details for all existing users.
        [$usql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $sql = "
            SELECT userid, iscomplete, timenotified, id
              FROM {checklist_comp_notification}
             WHERE checklistid = :checklistid
               AND userid $usql
        ";
        $params['checklistid'] = $checklistid;
        $this->comps = $DB->get_records_sql($sql, $params);
        foreach ($userids as $userid) {
            // Fill in blank details for any missing records.
            if (!isset($this->comps[$userid])) {
                $this->comps[$userid] = (object)[
                    'id' => 0,
                    'checklistid' => $checklistid,
                    'userid' => $userid,
                    'iscomplete' => 0,
                    'timenotified' => 0,
                ];
            }
        }
    }

    /**
     * Was the given user previously complete?
     * @param int $userid
     * @return bool
     */
    public function was_complete(int $userid): bool {
        if (!isset($this->comps[$userid])) {
            throw new \coding_exception("Cannot check completion state for user $userid, not previously loaded");
        }
        return (bool)$this->comps[$userid]->iscomplete;
    }

    /**
     * Was a notification email recently (last hour) sent out about this user completing their checklist?
     * @param int $userid
     * @return bool
     */
    public function notified_recently(int $userid): bool {
        if (!isset($this->comps[$userid])) {
            throw new \coding_exception("Cannot check recent notification for user $userid, not previously loaded");
        }
        $nexttimeallowed = $this->comps[$userid]->timenotified + self::MAX_EMAIL_FREQUENCY;
        return ($nexttimeallowed > self::time());
    }

    /**
     * Set the time when a notification was sent for this user (note this does not save the details
     * back to the database - it is expected that save_completion() will be called soon after by the
     * calling code).
     * @param int $userid
     * @return void
     */
    public function mark_notified(int $userid): void {
        if (!isset($this->comps[$userid])) {
            throw new \coding_exception("Cannot set time notified for user $userid, not previously loaded");
        }
        $this->comps[$userid]->timenotified = self::time();
        $this->comps[$userid]->timenotifiedchanged = true;
    }

    /**
     * Keep track of whether the user has completed the checklist.
     * @param int $userid
     * @param bool $iscomplete
     * @return void
     */
    public function save_completion(int $userid, bool $iscomplete): void {
        global $DB;
        $rec = $this->comps[$userid];
        if ((bool)$rec->iscomplete === $iscomplete && $rec->id && empty($rec->timenotifiedchanged)) {
            return; // Nothing has changed - don't update the DB.
        }
        $rec->iscomplete = $iscomplete ? 1 : 0;
        if (!$rec->id) {
            $rec->id = $DB->insert_record('checklist_comp_notification', $rec);
        } else {
            $DB->update_record('checklist_comp_notification', $rec);
        }
    }
}
