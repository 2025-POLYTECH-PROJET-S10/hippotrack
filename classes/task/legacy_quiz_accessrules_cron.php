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
 * Legacy Cron Quiz Access Rules Task
 *
 * @package    mod_hippotrack
 * @copyright  2017 Michael Hughes
 * @author Michael Hughes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_hippotrack\task;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/hippotrack/locallib.php');

/**
 * Legacy Cron Quiz Access Rules Task
 *
 * @package    mod_hippotrack
 * @copyright  2017 Michael Hughes
 * @author Michael Hughes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */
class legacy_hippotrack_accessrules_cron extends \core\task\scheduled_task {

    public function get_name() {
        return get_string('legacyquizaccessrulescron', 'mod_hippotrack');
    }

    /**
     * Execute all quizaccess subplugins legacy cron tasks.
     */
    public function execute() {
        cron_execute_plugin_type('quizaccess', 'quiz access rules');
    }
}
