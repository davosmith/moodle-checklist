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
 * Information about the student's progress, to pass on to the progress bar output
 *
 * @package   mod_checklist
 * @copyright 2016 Davo Smith, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_checklist\local;

/**
 * Class progress_info
 * @package mod_checklist
 */
class progress_info {
    /** @var int */
    public $totalitems;
    /** @var int */
    public $requireditems;
    /** @var int */
    public $allcompleteitems;
    /** @var int */
    public $requiredcompleteitems;

    /**
     * progress_info constructor.
     * @param int $totalitems
     * @param int $requireditems
     * @param int $allcompleteitems
     * @param int $requiredcompleteitems
     */
    public function __construct($totalitems, $requireditems, $allcompleteitems, $requiredcompleteitems) {
        $this->totalitems = $totalitems;
        $this->requireditems = $requireditems;
        $this->allcompleteitems = $allcompleteitems;
        $this->requiredcompleteitems = $requiredcompleteitems;
    }
}
