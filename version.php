<?php
// This file is part of the Checklist plugin for Moodle - http://moodle.org/
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
 * Code fragment to define the version of checklist
 * This fragment is called by moodle_needs_upgrading() and /admin/index.php
 *
 * @author  Davo Smith <moodle@davosmith.co.uk>
 * @package mod/checklist
 */

defined('MOODLE_INTERNAL') || die();
global $CFG;

if (!isset($plugin)) {
    // Avoid warning message in M2.5 and below.
    $plugin = new stdClass();
}
// Used by M2.6 and above.
$plugin->version = 2015110800;  // The current module version (Date: YYYYMMDDXX)
$plugin->cron = 60;          // Period for cron to check this module (secs).
$plugin->maturity = MATURITY_STABLE;
$plugin->release = '2.x (Build: 2015110800)';
$plugin->requires = 2010112400;
$plugin->component = 'mod_checklist';

if ($CFG->branch < 26) {
    // Used by M2.5 and below.
    $module->version = $plugin->version;
    $module->cron = $plugin->cron;
    $module->maturity = $plugin->maturity;
    $module->release = $plugin->release;
    $module->requires = $plugin->requires;
    $module->component = $plugin->component;
}