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

namespace hippotrack_statistics;

use core\dml\sql_join;

/**
 * Clear the statistics cache when the hippotrack structure is modified.
 *
 * @package   hippotrack_statistics
 * @copyright 2023 onwards Catalyst IT EU {@link https://catalyst-eu.net}
 * @author    Mark Johnson <mark.johnson@catalyst-eu.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hippotrack_structure_modified {
    /**
     * Clear the statistics cache.
     *
     * @param int $hippotrackid The hippotrack to clear the cache for.
     * @return void
     */
    public static function callback(int $hippotrackid): void {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/hippotrack/report/statistics/statisticslib.php');
        require_once($CFG->dirroot . '/mod/hippotrack/report/statistics/report.php');
        $hippotrack = $DB->get_record('hippotrack', ['id' => $hippotrackid]);
        if (!$hippotrack) {
            throw new \coding_exception('Could not find hippotrack with ID ' . $hippotrackid . '.');
        }
        $qubaids = hippotrack_statistics_qubaids_condition(
            $hippotrack->id,
            new sql_join(),
            $hippotrack->grademethod
        );

        $report = new \hippotrack_statistics_report();
        $report->clear_cached_data($qubaids);
    }
}
