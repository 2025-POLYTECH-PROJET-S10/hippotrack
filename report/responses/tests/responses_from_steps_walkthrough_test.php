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

namespace hippotrack_responses;

use question_bank;
use hippotrack_attempt;

/**
 * HippoTrack attempt walk through using data from csv file.
 *
 * @package    hippotrack_responses
 * @category   test
 * @copyright  2013 The Open University
 * @author     Jamie Pratt <me@jamiep.org>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class responses_from_steps_walkthrough_test extends \mod_hippotrack\tests\attempt_walkthrough_testcase {
    #[\Override]
    public static function setUpBeforeClass(): void {
        global $CFG;

        parent::setUpBeforeClass();

        require_once($CFG->dirroot . '/mod/hippotrack/report/statistics/report.php');
        require_once($CFG->dirroot . '/mod/hippotrack/report/reportlib.php');
    }

    #[\Override]
    protected static function get_test_files(): array {
        return ['questions', 'steps', 'responses'];
    }

    /**
     * Create a hippotrack add questions to it, walk through hippotrack attempts and then check results.
     *
     * @param array $hippotracksettings settings to override default settings for hippotrack created by generator. Taken from hippotrackzes.csv.
     * @param array $csvdata of data read from csv file "questionsXX.csv", "stepsXX.csv" and "responsesXX.csv".
     * @dataProvider get_data_for_walkthrough
     */
    public function test_walkthrough_from_csv($hippotracksettings, $csvdata): void {
        $this->resetAfterTest(true);
        question_bank::get_qtype('random')->clear_caches_before_testing();

        $this->create_hippotrack($hippotracksettings, $csvdata['questions']);

        $hippotrackattemptids = $this->walkthrough_attempts($csvdata['steps']);

        foreach ($csvdata['responses'] as $responsesfromcsv) {
            $responses = $this->explode_dot_separated_keys_to_make_subindexs($responsesfromcsv);

            if (!isset($hippotrackattemptids[$responses['hippotrackattempt']])) {
                throw new \coding_exception("There is no hippotrackattempt {$responses['hippotrackattempt']}!");
            }
            $this->assert_response_test($hippotrackattemptids[$responses['hippotrackattempt']], $responses);
        }
    }

    /**
     * Helper to assert a response.
     *
     * @param mixed $hippotrackattemptid
     * @param mixed $responses
     * @throws \coding_exception
     */
    protected function assert_response_test($hippotrackattemptid, $responses): void {
        $hippotrackattempt = hippotrack_attempt::create($hippotrackattemptid);

        foreach ($responses['slot'] as $slot => $tests) {
            $slothastests = false;
            foreach ($tests as $test) {
                if ('' !== $test) {
                    $slothastests = true;
                }
            }
            if (!$slothastests) {
                continue;
            }
            $qa = $hippotrackattempt->get_question_attempt($slot);
            $stepswithsubmit = $qa->get_steps_with_submitted_response_iterator();
            $step = $stepswithsubmit[$responses['submittedstepno']];
            if (null === $step) {
                throw new \coding_exception("There is no step no {$responses['submittedstepno']} ".
                                           "for slot $slot in hippotrackattempt {$responses['hippotrackattempt']}!");
            }
            foreach (array('responsesummary', 'fraction', 'state') as $column) {
                if (isset($tests[$column]) && $tests[$column] != '') {
                    switch($column) {
                        case 'responsesummary' :
                            $actual = $qa->get_question()->summarise_response($step->get_qt_data());
                            break;
                        case 'fraction' :
                            if (count($stepswithsubmit) == $responses['submittedstepno']) {
                                // If this is the last step then we need to look at the fraction after the question has been
                                // finished.
                                $actual = $qa->get_fraction();
                            } else {
                                $actual = $step->get_fraction();
                            }
                           break;
                        case 'state' :
                            if (count($stepswithsubmit) == $responses['submittedstepno']) {
                                // If this is the last step then we need to look at the state after the question has been
                                // finished.
                                $state = $qa->get_state();
                            } else {
                                $state = $step->get_state();
                            }
                            $actual = substr(get_class($state), strlen('question_state_'));
                    }
                    $expected = $tests[$column];
                    $failuremessage = "Error in  hippotrackattempt {$responses['hippotrackattempt']} in $column, slot $slot, ".
                    "submittedstepno {$responses['submittedstepno']}";
                    $this->assertEquals($expected, $actual, $failuremessage);
                }
            }
        }
    }
}
