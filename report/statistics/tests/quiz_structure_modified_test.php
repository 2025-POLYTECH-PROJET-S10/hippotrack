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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/hippotrack/tests/hippotrack_question_helper_test_trait.php');

use core\progress\none;
use mod_hippotrack\grade_calculator;
use mod_hippotrack\hippotrack_settings;

/**
 * Unit tests for hippotrack_statistics\event\observer\slots_updated
 *
 * @package   hippotrack_statistics
 * @copyright 2023 onwards Catalyst IT EU {@link https://catalyst-eu.net}
 * @author    Mark Johnson <mark.johnson@catalyst-eu.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \hippotrack_statistics\hippotrack_structure_modified
 */
class hippotrack_structure_modified_test extends \advanced_testcase {
    use \hippotrack_question_helper_test_trait;

    /**
     * Clear the statistics cache for a hippotrack when it structure is modified.
     *
     * When recompute_hippotrack_sumgrades() is called, it should trigger this plugin's hippotrack_structure_modified callback
     * which clears the statistics cache for the hippotrack.
     *
     * @return void
     */
    public function test_clear_cache_on_structure_modified(): void {
        global $DB;
        $this->resetAfterTest(true);

        // Create and attempt a hippotrack.
        $generator = $this->getDataGenerator();
        $user = $generator->create_user();
        $course = $generator->create_course();
        $hippotrack = $this->create_test_hippotrack($course);
        $questiongenerator = $generator->get_plugin_generator('core_question');
        $category = $questiongenerator->create_question_category();
        $question = $questiongenerator->create_question('match', null, ['category' => $category->id]);
        $questiongenerator->update_question($question);
        hippotrack_add_hippotrack_question($question->id, $hippotrack);
        $this->attempt_hippotrack($hippotrack, $user);

        // Run the statistics calculation to prime the cache.
        $report = new \hippotrack_statistics_report();
        $questions = $report->load_and_initialise_questions_for_calculations($hippotrack);
        $report->get_all_stats_and_analysis(
            $hippotrack,
            $hippotrack->grademethod,
            \question_attempt::ALL_TRIES,
            new \core\dml\sql_join(),
            $questions,
            new none(),
        );

        $hashcode = hippotrack_statistics_qubaids_condition($hippotrack->id, new \core\dml\sql_join(), $hippotrack->grademethod)->get_hash_code();

        $this->assertTrue($DB->record_exists('hippotrack_statistics', ['hashcode' => $hashcode]));
        $this->assertTrue($DB->record_exists('question_statistics', ['hashcode' => $hashcode]));
        $this->assertTrue($DB->record_exists('question_response_analysis', ['hashcode' => $hashcode]));

        // Recompute sumgrades, which triggers the hippotrack_structure_modified callback.
        hippotrack_update_sumgrades($hippotrack);

        $this->assertFalse($DB->record_exists('hippotrack_statistics', ['hashcode' => $hashcode]));
        $this->assertFalse($DB->record_exists('question_statistics', ['hashcode' => $hashcode]));
        $this->assertFalse($DB->record_exists('question_response_analysis', ['hashcode' => $hashcode]));
    }
}
