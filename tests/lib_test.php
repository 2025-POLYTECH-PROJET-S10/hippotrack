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
 * Unit tests for (some of) mod/hippotrack/locallib.php.
 *
 * @package    mod_hippotrack
 * @category   test
 * @copyright  2008 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU Public License
 */
namespace mod_hippotrack;

use hippotrack;
use hippotrack_attempt;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/hippotrack/lib.php');

/**
 * @copyright  2008 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU Public License
 */
class lib_test extends \advanced_testcase {
    public function test_hippotrack_has_grades() {
        $hippotrack = new \stdClass();
        $hippotrack->grade = '100.0000';
        $hippotrack->sumgrades = '100.0000';
        $this->assertTrue(hippotrack_has_grades($hippotrack));
        $hippotrack->sumgrades = '0.0000';
        $this->assertFalse(hippotrack_has_grades($hippotrack));
        $hippotrack->grade = '0.0000';
        $this->assertFalse(hippotrack_has_grades($hippotrack));
        $hippotrack->sumgrades = '100.0000';
        $this->assertFalse(hippotrack_has_grades($hippotrack));
    }

    public function test_hippotrack_format_grade() {
        $hippotrack = new \stdClass();
        $hippotrack->decimalpoints = 2;
        $this->assertEquals(hippotrack_format_grade($hippotrack, 0.12345678), format_float(0.12, 2));
        $this->assertEquals(hippotrack_format_grade($hippotrack, 0), format_float(0, 2));
        $this->assertEquals(hippotrack_format_grade($hippotrack, 1.000000000000), format_float(1, 2));
        $hippotrack->decimalpoints = 0;
        $this->assertEquals(hippotrack_format_grade($hippotrack, 0.12345678), '0');
    }

    public function test_hippotrack_get_grade_format() {
        $hippotrack = new \stdClass();
        $hippotrack->decimalpoints = 2;
        $this->assertEquals(hippotrack_get_grade_format($hippotrack), 2);
        $this->assertEquals($hippotrack->questiondecimalpoints, -1);
        $hippotrack->questiondecimalpoints = 2;
        $this->assertEquals(hippotrack_get_grade_format($hippotrack), 2);
        $hippotrack->decimalpoints = 3;
        $hippotrack->questiondecimalpoints = -1;
        $this->assertEquals(hippotrack_get_grade_format($hippotrack), 3);
        $hippotrack->questiondecimalpoints = 4;
        $this->assertEquals(hippotrack_get_grade_format($hippotrack), 4);
    }

    public function test_hippotrack_format_question_grade() {
        $hippotrack = new \stdClass();
        $hippotrack->decimalpoints = 2;
        $hippotrack->questiondecimalpoints = 2;
        $this->assertEquals(hippotrack_format_question_grade($hippotrack, 0.12345678), format_float(0.12, 2));
        $this->assertEquals(hippotrack_format_question_grade($hippotrack, 0), format_float(0, 2));
        $this->assertEquals(hippotrack_format_question_grade($hippotrack, 1.000000000000), format_float(1, 2));
        $hippotrack->decimalpoints = 3;
        $hippotrack->questiondecimalpoints = -1;
        $this->assertEquals(hippotrack_format_question_grade($hippotrack, 0.12345678), format_float(0.123, 3));
        $this->assertEquals(hippotrack_format_question_grade($hippotrack, 0), format_float(0, 3));
        $this->assertEquals(hippotrack_format_question_grade($hippotrack, 1.000000000000), format_float(1, 3));
        $hippotrack->questiondecimalpoints = 4;
        $this->assertEquals(hippotrack_format_question_grade($hippotrack, 0.12345678), format_float(0.1235, 4));
        $this->assertEquals(hippotrack_format_question_grade($hippotrack, 0), format_float(0, 4));
        $this->assertEquals(hippotrack_format_question_grade($hippotrack, 1.000000000000), format_float(1, 4));
    }

    /**
     * Test deleting a hippotrack instance.
     */
    public function test_hippotrack_delete_instance() {
        global $SITE, $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Setup a hippotrack with 1 standard and 1 random question.
        $hippotrackgenerator = $this->getDataGenerator()->get_plugin_generator('mod_hippotrack');
        $hippotrack = $hippotrackgenerator->create_instance(array('course' => $SITE->id, 'questionsperpage' => 3, 'grade' => 100.0));

        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $standardq = $questiongenerator->create_question('shortanswer', null, array('category' => $cat->id));

        hippotrack_add_hippotrack_question($standardq->id, $hippotrack);
        hippotrack_add_random_questions($hippotrack, 0, $cat->id, 1, false);

        // Get the random question.
        $randomq = $DB->get_record('question', array('qtype' => 'random'));

        hippotrack_delete_instance($hippotrack->id);

        // Check that the random question was deleted.
        if ($randomq) {
            $count = $DB->count_records('question', array('id' => $randomq->id));
            $this->assertEquals(0, $count);
        }
        // Check that the standard question was not deleted.
        $count = $DB->count_records('question', array('id' => $standardq->id));
        $this->assertEquals(1, $count);

        // Check that all the slots were removed.
        $count = $DB->count_records('hippotrack_slots', array('hippotrackid' => $hippotrack->id));
        $this->assertEquals(0, $count);

        // Check that the hippotrack was removed.
        $count = $DB->count_records('hippotrack', array('id' => $hippotrack->id));
        $this->assertEquals(0, $count);
    }

    /**
     * Setup function for all test_hippotrack_get_completion_state_* tests.
     *
     * @param array $completionoptions ['nbstudents'] => int, ['qtype'] => string, ['hippotrackoptions'] => array
     * @throws dml_exception
     * @return array [$course, $students, $hippotrack, $cm]
     */
    private function setup_hippotrack_for_testing_completion(array $completionoptions) {
        global $CFG, $DB;

        $this->resetAfterTest(true);

        // Enable completion before creating modules, otherwise the completion data is not written in DB.
        $CFG->enablecompletion = true;

        // Create a course and students.
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => true]);
        $students = [];
        for ($i = 0; $i < $completionoptions['nbstudents']; $i++) {
            $students[$i] = $this->getDataGenerator()->create_user();
            $this->assertTrue($this->getDataGenerator()->enrol_user($students[$i]->id, $course->id, $studentrole->id));
        }

        // Make a hippotrack.
        $hippotrackgenerator = $this->getDataGenerator()->get_plugin_generator('mod_hippotrack');
        $data = array_merge([
            'course' => $course->id,
            'grade' => 100.0,
            'questionsperpage' => 0,
            'sumgrades' => 1,
            'completion' => COMPLETION_TRACKING_AUTOMATIC
        ], $completionoptions['hippotrackoptions']);
        $hippotrack = $hippotrackgenerator->create_instance($data);
        $cm = get_coursemodule_from_id('hippotrack', $hippotrack->cmid);

        // Create a question.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');

        $cat = $questiongenerator->create_question_category();
        $question = $questiongenerator->create_question($completionoptions['qtype'], null, ['category' => $cat->id]);
        hippotrack_add_hippotrack_question($question->id, $hippotrack);

        // Set grade to pass.
        $item = \grade_item::fetch(['courseid' => $course->id, 'itemtype' => 'mod', 'itemmodule' => 'hippotrack',
            'iteminstance' => $hippotrack->id, 'outcomeid' => null]);
        $item->gradepass = 80;
        $item->update();

        return [
            $course,
            $students,
            $hippotrack,
            $cm
        ];
    }

    /**
     * Helper function for all test_hippotrack_get_completion_state_* tests.
     * Starts an attempt, processes responses and finishes the attempt.
     *
     * @param $attemptoptions ['hippotrack'] => object, ['student'] => object, ['tosubmit'] => array, ['attemptnumber'] => int
     */
    private function do_attempt_hippotrack($attemptoptions) {
        $hippotrackobj = hippotrack::create($attemptoptions['hippotrack']->id);

        // Start the passing attempt.
        $quba = \question_engine::make_questions_usage_by_activity('mod_hippotrack', $hippotrackobj->get_context());
        $quba->set_preferred_behaviour($hippotrackobj->get_hippotrack()->preferredbehaviour);

        $timenow = time();
        $attempt = hippotrack_create_attempt($hippotrackobj, $attemptoptions['attemptnumber'], false, $timenow, false,
            $attemptoptions['student']->id);
        hippotrack_start_new_attempt($hippotrackobj, $quba, $attempt, $attemptoptions['attemptnumber'], $timenow);
        hippotrack_attempt_save_started($hippotrackobj, $quba, $attempt);

        // Process responses from the student.
        $attemptobj = hippotrack_attempt::create($attempt->id);
        $attemptobj->process_submitted_actions($timenow, false, $attemptoptions['tosubmit']);

        // Finish the attempt.
        $attemptobj = hippotrack_attempt::create($attempt->id);
        $this->assertTrue($attemptobj->has_response_to_at_least_one_graded_question());
        $attemptobj->process_finish($timenow, false);
    }

    /**
     * Test checking the completion state of a hippotrack.
     * The hippotrack requires a passing grade to be completed.
     */
    public function test_hippotrack_get_completion_state_completionpass() {

        list($course, $students, $hippotrack, $cm) = $this->setup_hippotrack_for_testing_completion([
            'nbstudents' => 2,
            'qtype' => 'numerical',
            'hippotrackoptions' => [
                'completionusegrade' => 1,
                'completionpassgrade' => 1
            ]
        ]);

        list($passstudent, $failstudent) = $students;

        // Do a passing attempt.
        $this->do_attempt_hippotrack([
           'hippotrack' => $hippotrack,
           'student' => $passstudent,
           'attemptnumber' => 1,
           'tosubmit' => [1 => ['answer' => '3.14']]
        ]);

        // Check the results.
        $this->assertTrue(hippotrack_get_completion_state($course, $cm, $passstudent->id, 'return'));

        // Do a failing attempt.
        $this->do_attempt_hippotrack([
            'hippotrack' => $hippotrack,
            'student' => $failstudent,
            'attemptnumber' => 1,
            'tosubmit' => [1 => ['answer' => '0']]
        ]);

        // Check the results.
        $this->assertFalse(hippotrack_get_completion_state($course, $cm, $failstudent->id, 'return'));

        $this->assertDebuggingCalledCount(3, [
            'hippotrack_completion_check_passing_grade_or_all_attempts has been deprecated.',
            'hippotrack_completion_check_min_attempts has been deprecated.',
            'hippotrack_completion_check_passing_grade_or_all_attempts has been deprecated.',
        ]);
    }

    /**
     * Test checking the completion state of a hippotrack.
     * To be completed, this hippotrack requires either a passing grade or for all attempts to be used up.
     */
    public function test_hippotrack_get_completion_state_completionexhausted() {

        list($course, $students, $hippotrack, $cm) = $this->setup_hippotrack_for_testing_completion([
            'nbstudents' => 2,
            'qtype' => 'numerical',
            'hippotrackoptions' => [
                'attempts' => 2,
                'completionusegrade' => 1,
                'completionpassgrade' => 1,
                'completionattemptsexhausted' => 1
            ]
        ]);

        list($passstudent, $exhauststudent) = $students;

        // Start a passing attempt.
        $this->do_attempt_hippotrack([
            'hippotrack' => $hippotrack,
            'student' => $passstudent,
            'attemptnumber' => 1,
            'tosubmit' => [1 => ['answer' => '3.14']]
        ]);

        // Check the results. HippoTrack is completed by $passstudent because of passing grade.
        $this->assertTrue(hippotrack_get_completion_state($course, $cm, $passstudent->id, 'return'));

        // Do a failing attempt.
        $this->do_attempt_hippotrack([
            'hippotrack' => $hippotrack,
            'student' => $exhauststudent,
            'attemptnumber' => 1,
            'tosubmit' => [1 => ['answer' => '0']]
        ]);

        // Check the results. HippoTrack is not completed by $exhauststudent yet because of failing grade and of remaining attempts.
        $this->assertFalse(hippotrack_get_completion_state($course, $cm, $exhauststudent->id, 'return'));

        // Do a second failing attempt.
        $this->do_attempt_hippotrack([
            'hippotrack' => $hippotrack,
            'student' => $exhauststudent,
            'attemptnumber' => 2,
            'tosubmit' => [1 => ['answer' => '0']]
        ]);

        // Check the results. HippoTrack is completed by $exhauststudent because there are no remaining attempts.
        $this->assertTrue(hippotrack_get_completion_state($course, $cm, $exhauststudent->id, 'return'));

        $this->assertDebuggingCalledCount(5, [
            'hippotrack_completion_check_passing_grade_or_all_attempts has been deprecated.',
            'hippotrack_completion_check_min_attempts has been deprecated.',
            'hippotrack_completion_check_passing_grade_or_all_attempts has been deprecated.',
            'hippotrack_completion_check_passing_grade_or_all_attempts has been deprecated.',
            'hippotrack_completion_check_min_attempts has been deprecated.',
        ]);
    }

    /**
     * Test checking the completion state of a hippotrack.
     * To be completed, this hippotrack requires a minimum number of attempts.
     */
    public function test_hippotrack_get_completion_state_completionminattempts() {

        list($course, $students, $hippotrack, $cm) = $this->setup_hippotrack_for_testing_completion([
            'nbstudents' => 1,
            'qtype' => 'essay',
            'hippotrackoptions' => [
                'completionminattemptsenabled' => 1,
                'completionminattempts' => 2
            ]
        ]);

        list($student) = $students;

        // Do a first attempt.
        $this->do_attempt_hippotrack([
            'hippotrack' => $hippotrack,
            'student' => $student,
            'attemptnumber' => 1,
            'tosubmit' => [1 => ['answer' => 'Lorem ipsum.', 'answerformat' => '1']]
        ]);

        // Check the results. HippoTrack is not completed yet because only one attempt was done.
        $this->assertFalse(hippotrack_get_completion_state($course, $cm, $student->id, 'return'));

        // Do a second attempt.
        $this->do_attempt_hippotrack([
            'hippotrack' => $hippotrack,
            'student' => $student,
            'attemptnumber' => 2,
            'tosubmit' => [1 => ['answer' => 'Lorem ipsum.', 'answerformat' => '1']]
        ]);

        // Check the results. HippoTrack is completed by $student because two attempts were done.
        $this->assertTrue(hippotrack_get_completion_state($course, $cm, $student->id, 'return'));

        $this->assertDebuggingCalledCount(4, [
            'hippotrack_completion_check_passing_grade_or_all_attempts has been deprecated.',
            'hippotrack_completion_check_min_attempts has been deprecated.',
            'hippotrack_completion_check_passing_grade_or_all_attempts has been deprecated.',
            'hippotrack_completion_check_min_attempts has been deprecated.',
        ]);
    }

    /**
     * Test checking the completion state of a hippotrack.
     * To be completed, this hippotrack requires a minimum number of attempts AND a passing grade.
     * This is somewhat of an edge case as it is hard to imagine a scenario in which these precise settings are useful.
     * Nevertheless, this test makes sure these settings interact as intended.
     */
    public function  test_hippotrack_get_completion_state_completionminattempts_pass() {

        list($course, $students, $hippotrack, $cm) = $this->setup_hippotrack_for_testing_completion([
            'nbstudents' => 1,
            'qtype' => 'numerical',
            'hippotrackoptions' => [
                'attempts' => 2,
                'completionusegrade' => 1,
                'completionpassgrade' => 1,
                'completionminattemptsenabled' => 1,
                'completionminattempts' => 2
            ]
        ]);

        list($student) = $students;

        // Start a first attempt.
        $this->do_attempt_hippotrack([
            'hippotrack' => $hippotrack,
            'student' => $student,
            'attemptnumber' => 1,
            'tosubmit' => [1 => ['answer' => '3.14']]
        ]);

        // Check the results. Even though one requirement is met (passing grade) hippotrack is not completed yet because only
        // one attempt was done.
        $this->assertFalse(hippotrack_get_completion_state($course, $cm, $student->id, 'return'));

        // Start a second attempt.
        $this->do_attempt_hippotrack([
            'hippotrack' => $hippotrack,
            'student' => $student,
            'attemptnumber' => 2,
            'tosubmit' => [1 => ['answer' => '42']]
        ]);

        // Check the results. HippoTrack is completed by $student because two attempts were done AND a passing grade was obtained.
        $this->assertTrue(hippotrack_get_completion_state($course, $cm, $student->id, 'return'));

        $this->assertDebuggingCalledCount(4, [
            'hippotrack_completion_check_passing_grade_or_all_attempts has been deprecated.',
            'hippotrack_completion_check_min_attempts has been deprecated.',
            'hippotrack_completion_check_passing_grade_or_all_attempts has been deprecated.',
            'hippotrack_completion_check_min_attempts has been deprecated.',
        ]);
    }

    public function test_hippotrack_get_user_attempts() {
        global $DB;
        $this->resetAfterTest();

        $dg = $this->getDataGenerator();
        $hippotrackgen = $dg->get_plugin_generator('mod_hippotrack');
        $course = $dg->create_course();
        $u1 = $dg->create_user();
        $u2 = $dg->create_user();
        $u3 = $dg->create_user();
        $u4 = $dg->create_user();
        $role = $DB->get_record('role', ['shortname' => 'student']);

        $dg->enrol_user($u1->id, $course->id, $role->id);
        $dg->enrol_user($u2->id, $course->id, $role->id);
        $dg->enrol_user($u3->id, $course->id, $role->id);
        $dg->enrol_user($u4->id, $course->id, $role->id);

        $hippotrack1 = $hippotrackgen->create_instance(['course' => $course->id, 'sumgrades' => 2]);
        $hippotrack2 = $hippotrackgen->create_instance(['course' => $course->id, 'sumgrades' => 2]);

        // Questions.
        $questgen = $dg->get_plugin_generator('core_question');
        $hippotrackcat = $questgen->create_question_category();
        $question = $questgen->create_question('numerical', null, ['category' => $hippotrackcat->id]);
        hippotrack_add_hippotrack_question($question->id, $hippotrack1);
        hippotrack_add_hippotrack_question($question->id, $hippotrack2);

        $hippotrackobj1a = hippotrack::create($hippotrack1->id, $u1->id);
        $hippotrackobj1b = hippotrack::create($hippotrack1->id, $u2->id);
        $hippotrackobj1c = hippotrack::create($hippotrack1->id, $u3->id);
        $hippotrackobj1d = hippotrack::create($hippotrack1->id, $u4->id);
        $hippotrackobj2a = hippotrack::create($hippotrack2->id, $u1->id);

        // Set attempts.
        $quba1a = \question_engine::make_questions_usage_by_activity('mod_hippotrack', $hippotrackobj1a->get_context());
        $quba1a->set_preferred_behaviour($hippotrackobj1a->get_hippotrack()->preferredbehaviour);
        $quba1b = \question_engine::make_questions_usage_by_activity('mod_hippotrack', $hippotrackobj1b->get_context());
        $quba1b->set_preferred_behaviour($hippotrackobj1b->get_hippotrack()->preferredbehaviour);
        $quba1c = \question_engine::make_questions_usage_by_activity('mod_hippotrack', $hippotrackobj1c->get_context());
        $quba1c->set_preferred_behaviour($hippotrackobj1c->get_hippotrack()->preferredbehaviour);
        $quba1d = \question_engine::make_questions_usage_by_activity('mod_hippotrack', $hippotrackobj1d->get_context());
        $quba1d->set_preferred_behaviour($hippotrackobj1d->get_hippotrack()->preferredbehaviour);
        $quba2a = \question_engine::make_questions_usage_by_activity('mod_hippotrack', $hippotrackobj2a->get_context());
        $quba2a->set_preferred_behaviour($hippotrackobj2a->get_hippotrack()->preferredbehaviour);

        $timenow = time();

        // User 1 passes hippotrack 1.
        $attempt = hippotrack_create_attempt($hippotrackobj1a, 1, false, $timenow, false, $u1->id);
        hippotrack_start_new_attempt($hippotrackobj1a, $quba1a, $attempt, 1, $timenow);
        hippotrack_attempt_save_started($hippotrackobj1a, $quba1a, $attempt);
        $attemptobj = hippotrack_attempt::create($attempt->id);
        $attemptobj->process_submitted_actions($timenow, false, [1 => ['answer' => '3.14']]);
        $attemptobj->process_finish($timenow, false);

        // User 2 goes overdue in hippotrack 1.
        $attempt = hippotrack_create_attempt($hippotrackobj1b, 1, false, $timenow, false, $u2->id);
        hippotrack_start_new_attempt($hippotrackobj1b, $quba1b, $attempt, 1, $timenow);
        hippotrack_attempt_save_started($hippotrackobj1b, $quba1b, $attempt);
        $attemptobj = hippotrack_attempt::create($attempt->id);
        $attemptobj->process_going_overdue($timenow, true);

        // User 3 does not finish hippotrack 1.
        $attempt = hippotrack_create_attempt($hippotrackobj1c, 1, false, $timenow, false, $u3->id);
        hippotrack_start_new_attempt($hippotrackobj1c, $quba1c, $attempt, 1, $timenow);
        hippotrack_attempt_save_started($hippotrackobj1c, $quba1c, $attempt);

        // User 4 abandons the hippotrack 1.
        $attempt = hippotrack_create_attempt($hippotrackobj1d, 1, false, $timenow, false, $u4->id);
        hippotrack_start_new_attempt($hippotrackobj1d, $quba1d, $attempt, 1, $timenow);
        hippotrack_attempt_save_started($hippotrackobj1d, $quba1d, $attempt);
        $attemptobj = hippotrack_attempt::create($attempt->id);
        $attemptobj->process_abandon($timenow, true);

        // User 1 attempts the hippotrack three times (abandon, finish, in progress).
        $quba2a = \question_engine::make_questions_usage_by_activity('mod_hippotrack', $hippotrackobj2a->get_context());
        $quba2a->set_preferred_behaviour($hippotrackobj2a->get_hippotrack()->preferredbehaviour);

        $attempt = hippotrack_create_attempt($hippotrackobj2a, 1, false, $timenow, false, $u1->id);
        hippotrack_start_new_attempt($hippotrackobj2a, $quba2a, $attempt, 1, $timenow);
        hippotrack_attempt_save_started($hippotrackobj2a, $quba2a, $attempt);
        $attemptobj = hippotrack_attempt::create($attempt->id);
        $attemptobj->process_abandon($timenow, true);

        $quba2a = \question_engine::make_questions_usage_by_activity('mod_hippotrack', $hippotrackobj2a->get_context());
        $quba2a->set_preferred_behaviour($hippotrackobj2a->get_hippotrack()->preferredbehaviour);

        $attempt = hippotrack_create_attempt($hippotrackobj2a, 2, false, $timenow, false, $u1->id);
        hippotrack_start_new_attempt($hippotrackobj2a, $quba2a, $attempt, 2, $timenow);
        hippotrack_attempt_save_started($hippotrackobj2a, $quba2a, $attempt);
        $attemptobj = hippotrack_attempt::create($attempt->id);
        $attemptobj->process_finish($timenow, false);

        $quba2a = \question_engine::make_questions_usage_by_activity('mod_hippotrack', $hippotrackobj2a->get_context());
        $quba2a->set_preferred_behaviour($hippotrackobj2a->get_hippotrack()->preferredbehaviour);

        $attempt = hippotrack_create_attempt($hippotrackobj2a, 3, false, $timenow, false, $u1->id);
        hippotrack_start_new_attempt($hippotrackobj2a, $quba2a, $attempt, 3, $timenow);
        hippotrack_attempt_save_started($hippotrackobj2a, $quba2a, $attempt);

        // Check for user 1.
        $attempts = hippotrack_get_user_attempts($hippotrack1->id, $u1->id, 'all');
        $this->assertCount(1, $attempts);
        $attempt = array_shift($attempts);
        $this->assertEquals(hippotrack_attempt::FINISHED, $attempt->state);
        $this->assertEquals($u1->id, $attempt->userid);
        $this->assertEquals($hippotrack1->id, $attempt->hippotrack);

        $attempts = hippotrack_get_user_attempts($hippotrack1->id, $u1->id, 'finished');
        $this->assertCount(1, $attempts);
        $attempt = array_shift($attempts);
        $this->assertEquals(hippotrack_attempt::FINISHED, $attempt->state);
        $this->assertEquals($u1->id, $attempt->userid);
        $this->assertEquals($hippotrack1->id, $attempt->hippotrack);

        $attempts = hippotrack_get_user_attempts($hippotrack1->id, $u1->id, 'unfinished');
        $this->assertCount(0, $attempts);

        // Check for user 2.
        $attempts = hippotrack_get_user_attempts($hippotrack1->id, $u2->id, 'all');
        $this->assertCount(1, $attempts);
        $attempt = array_shift($attempts);
        $this->assertEquals(hippotrack_attempt::OVERDUE, $attempt->state);
        $this->assertEquals($u2->id, $attempt->userid);
        $this->assertEquals($hippotrack1->id, $attempt->hippotrack);

        $attempts = hippotrack_get_user_attempts($hippotrack1->id, $u2->id, 'finished');
        $this->assertCount(0, $attempts);

        $attempts = hippotrack_get_user_attempts($hippotrack1->id, $u2->id, 'unfinished');
        $this->assertCount(1, $attempts);
        $attempt = array_shift($attempts);
        $this->assertEquals(hippotrack_attempt::OVERDUE, $attempt->state);
        $this->assertEquals($u2->id, $attempt->userid);
        $this->assertEquals($hippotrack1->id, $attempt->hippotrack);

        // Check for user 3.
        $attempts = hippotrack_get_user_attempts($hippotrack1->id, $u3->id, 'all');
        $this->assertCount(1, $attempts);
        $attempt = array_shift($attempts);
        $this->assertEquals(hippotrack_attempt::IN_PROGRESS, $attempt->state);
        $this->assertEquals($u3->id, $attempt->userid);
        $this->assertEquals($hippotrack1->id, $attempt->hippotrack);

        $attempts = hippotrack_get_user_attempts($hippotrack1->id, $u3->id, 'finished');
        $this->assertCount(0, $attempts);

        $attempts = hippotrack_get_user_attempts($hippotrack1->id, $u3->id, 'unfinished');
        $this->assertCount(1, $attempts);
        $attempt = array_shift($attempts);
        $this->assertEquals(hippotrack_attempt::IN_PROGRESS, $attempt->state);
        $this->assertEquals($u3->id, $attempt->userid);
        $this->assertEquals($hippotrack1->id, $attempt->hippotrack);

        // Check for user 4.
        $attempts = hippotrack_get_user_attempts($hippotrack1->id, $u4->id, 'all');
        $this->assertCount(1, $attempts);
        $attempt = array_shift($attempts);
        $this->assertEquals(hippotrack_attempt::ABANDONED, $attempt->state);
        $this->assertEquals($u4->id, $attempt->userid);
        $this->assertEquals($hippotrack1->id, $attempt->hippotrack);

        $attempts = hippotrack_get_user_attempts($hippotrack1->id, $u4->id, 'finished');
        $this->assertCount(1, $attempts);
        $attempt = array_shift($attempts);
        $this->assertEquals(hippotrack_attempt::ABANDONED, $attempt->state);
        $this->assertEquals($u4->id, $attempt->userid);
        $this->assertEquals($hippotrack1->id, $attempt->hippotrack);

        $attempts = hippotrack_get_user_attempts($hippotrack1->id, $u4->id, 'unfinished');
        $this->assertCount(0, $attempts);

        // Multiple attempts for user 1 in hippotrack 2.
        $attempts = hippotrack_get_user_attempts($hippotrack2->id, $u1->id, 'all');
        $this->assertCount(3, $attempts);
        $attempt = array_shift($attempts);
        $this->assertEquals(hippotrack_attempt::ABANDONED, $attempt->state);
        $this->assertEquals($u1->id, $attempt->userid);
        $this->assertEquals($hippotrack2->id, $attempt->hippotrack);
        $attempt = array_shift($attempts);
        $this->assertEquals(hippotrack_attempt::FINISHED, $attempt->state);
        $this->assertEquals($u1->id, $attempt->userid);
        $this->assertEquals($hippotrack2->id, $attempt->hippotrack);
        $attempt = array_shift($attempts);
        $this->assertEquals(hippotrack_attempt::IN_PROGRESS, $attempt->state);
        $this->assertEquals($u1->id, $attempt->userid);
        $this->assertEquals($hippotrack2->id, $attempt->hippotrack);

        $attempts = hippotrack_get_user_attempts($hippotrack2->id, $u1->id, 'finished');
        $this->assertCount(2, $attempts);
        $attempt = array_shift($attempts);
        $this->assertEquals(hippotrack_attempt::ABANDONED, $attempt->state);
        $attempt = array_shift($attempts);
        $this->assertEquals(hippotrack_attempt::FINISHED, $attempt->state);

        $attempts = hippotrack_get_user_attempts($hippotrack2->id, $u1->id, 'unfinished');
        $this->assertCount(1, $attempts);
        $attempt = array_shift($attempts);

        // Multiple hippotrack attempts fetched at once.
        $attempts = hippotrack_get_user_attempts([$hippotrack1->id, $hippotrack2->id], $u1->id, 'all');
        $this->assertCount(4, $attempts);
        $attempt = array_shift($attempts);
        $this->assertEquals(hippotrack_attempt::FINISHED, $attempt->state);
        $this->assertEquals($u1->id, $attempt->userid);
        $this->assertEquals($hippotrack1->id, $attempt->hippotrack);
        $attempt = array_shift($attempts);
        $this->assertEquals(hippotrack_attempt::ABANDONED, $attempt->state);
        $this->assertEquals($u1->id, $attempt->userid);
        $this->assertEquals($hippotrack2->id, $attempt->hippotrack);
        $attempt = array_shift($attempts);
        $this->assertEquals(hippotrack_attempt::FINISHED, $attempt->state);
        $this->assertEquals($u1->id, $attempt->userid);
        $this->assertEquals($hippotrack2->id, $attempt->hippotrack);
        $attempt = array_shift($attempts);
        $this->assertEquals(hippotrack_attempt::IN_PROGRESS, $attempt->state);
        $this->assertEquals($u1->id, $attempt->userid);
        $this->assertEquals($hippotrack2->id, $attempt->hippotrack);
    }

    /**
     * Test for hippotrack_get_group_override_priorities().
     */
    public function test_hippotrack_get_group_override_priorities() {
        global $DB;
        $this->resetAfterTest();

        $dg = $this->getDataGenerator();
        $hippotrackgen = $dg->get_plugin_generator('mod_hippotrack');
        $course = $dg->create_course();

        $hippotrack = $hippotrackgen->create_instance(['course' => $course->id, 'sumgrades' => 2]);

        $this->assertNull(hippotrack_get_group_override_priorities($hippotrack->id));

        $group1 = $this->getDataGenerator()->create_group(array('courseid' => $course->id));
        $group2 = $this->getDataGenerator()->create_group(array('courseid' => $course->id));

        $now = 100;
        $override1 = (object)[
            'hippotrack' => $hippotrack->id,
            'groupid' => $group1->id,
            'timeopen' => $now,
            'timeclose' => $now + 20
        ];
        $DB->insert_record('hippotrack_overrides', $override1);

        $override2 = (object)[
            'hippotrack' => $hippotrack->id,
            'groupid' => $group2->id,
            'timeopen' => $now - 10,
            'timeclose' => $now + 10
        ];
        $DB->insert_record('hippotrack_overrides', $override2);

        $priorities = hippotrack_get_group_override_priorities($hippotrack->id);
        $this->assertNotEmpty($priorities);

        $openpriorities = $priorities['open'];
        // Override 2's time open has higher priority since it is sooner than override 1's.
        $this->assertEquals(2, $openpriorities[$override1->timeopen]);
        $this->assertEquals(1, $openpriorities[$override2->timeopen]);

        $closepriorities = $priorities['close'];
        // Override 1's time close has higher priority since it is later than override 2's.
        $this->assertEquals(1, $closepriorities[$override1->timeclose]);
        $this->assertEquals(2, $closepriorities[$override2->timeclose]);
    }

    public function test_hippotrack_core_calendar_provide_event_action_open() {
        $this->resetAfterTest();

        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();
        // Create a student and enrol into the course.
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        // Create a hippotrack.
        $hippotrack = $this->getDataGenerator()->create_module('hippotrack', array('course' => $course->id,
            'timeopen' => time() - DAYSECS, 'timeclose' => time() + DAYSECS));

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $hippotrack->id, HIPPOTRACK_EVENT_TYPE_OPEN);
        // Now, log in as student.
        $this->setUser($student);
        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event.
        $actionevent = mod_hippotrack_core_calendar_provide_event_action($event, $factory);

        // Confirm the event was decorated.
        $this->assertInstanceOf('\core_calendar\local\event\value_objects\action', $actionevent);
        $this->assertEquals(get_string('attempthippotracknow', 'hippotrack'), $actionevent->get_name());
        $this->assertInstanceOf('moodle_url', $actionevent->get_url());
        $this->assertEquals(1, $actionevent->get_item_count());
        $this->assertTrue($actionevent->is_actionable());
    }

    public function test_hippotrack_core_calendar_provide_event_action_open_for_user() {
        $this->resetAfterTest();

        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();
        // Create a student and enrol into the course.
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        // Create a hippotrack.
        $hippotrack = $this->getDataGenerator()->create_module('hippotrack', array('course' => $course->id,
            'timeopen' => time() - DAYSECS, 'timeclose' => time() + DAYSECS));

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $hippotrack->id, HIPPOTRACK_EVENT_TYPE_OPEN);

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event for the student.
        $actionevent = mod_hippotrack_core_calendar_provide_event_action($event, $factory, $student->id);

        // Confirm the event was decorated.
        $this->assertInstanceOf('\core_calendar\local\event\value_objects\action', $actionevent);
        $this->assertEquals(get_string('attempthippotracknow', 'hippotrack'), $actionevent->get_name());
        $this->assertInstanceOf('moodle_url', $actionevent->get_url());
        $this->assertEquals(1, $actionevent->get_item_count());
        $this->assertTrue($actionevent->is_actionable());
    }

    public function test_hippotrack_core_calendar_provide_event_action_closed() {
        $this->resetAfterTest();

        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Create a hippotrack.
        $hippotrack = $this->getDataGenerator()->create_module('hippotrack', array('course' => $course->id,
            'timeclose' => time() - DAYSECS));

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $hippotrack->id, HIPPOTRACK_EVENT_TYPE_CLOSE);

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Confirm the result was null.
        $this->assertNull(mod_hippotrack_core_calendar_provide_event_action($event, $factory));
    }

    public function test_hippotrack_core_calendar_provide_event_action_closed_for_user() {
        $this->resetAfterTest();

        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Create a student.
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        // Create a hippotrack.
        $hippotrack = $this->getDataGenerator()->create_module('hippotrack', array('course' => $course->id,
            'timeclose' => time() - DAYSECS));

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $hippotrack->id, HIPPOTRACK_EVENT_TYPE_CLOSE);

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Confirm the result was null.
        $this->assertNull(mod_hippotrack_core_calendar_provide_event_action($event, $factory, $student->id));
    }

    public function test_hippotrack_core_calendar_provide_event_action_open_in_future() {
        $this->resetAfterTest();

        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();
        // Create a student and enrol into the course.
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        // Create a hippotrack.
        $hippotrack = $this->getDataGenerator()->create_module('hippotrack', array('course' => $course->id,
            'timeopen' => time() + DAYSECS));

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $hippotrack->id, HIPPOTRACK_EVENT_TYPE_CLOSE);
        // Now, log in as student.
        $this->setUser($student);
        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event.
        $actionevent = mod_hippotrack_core_calendar_provide_event_action($event, $factory);

        // Confirm the event was decorated.
        $this->assertInstanceOf('\core_calendar\local\event\value_objects\action', $actionevent);
        $this->assertEquals(get_string('attempthippotracknow', 'hippotrack'), $actionevent->get_name());
        $this->assertInstanceOf('moodle_url', $actionevent->get_url());
        $this->assertEquals(1, $actionevent->get_item_count());
        $this->assertFalse($actionevent->is_actionable());
    }

    public function test_hippotrack_core_calendar_provide_event_action_open_in_future_for_user() {
        $this->resetAfterTest();

        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();
        // Create a student and enrol into the course.
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        // Create a hippotrack.
        $hippotrack = $this->getDataGenerator()->create_module('hippotrack', array('course' => $course->id,
            'timeopen' => time() + DAYSECS));

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $hippotrack->id, HIPPOTRACK_EVENT_TYPE_CLOSE);

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event for the student.
        $actionevent = mod_hippotrack_core_calendar_provide_event_action($event, $factory, $student->id);

        // Confirm the event was decorated.
        $this->assertInstanceOf('\core_calendar\local\event\value_objects\action', $actionevent);
        $this->assertEquals(get_string('attempthippotracknow', 'hippotrack'), $actionevent->get_name());
        $this->assertInstanceOf('moodle_url', $actionevent->get_url());
        $this->assertEquals(1, $actionevent->get_item_count());
        $this->assertFalse($actionevent->is_actionable());
    }

    public function test_hippotrack_core_calendar_provide_event_action_no_capability() {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Create a student.
        $student = $this->getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));

        // Enrol student.
        $this->assertTrue($this->getDataGenerator()->enrol_user($student->id, $course->id, $studentrole->id));

        // Create a hippotrack.
        $hippotrack = $this->getDataGenerator()->create_module('hippotrack', array('course' => $course->id));

        // Remove the permission to attempt or review the hippotrack for the student role.
        $coursecontext = \context_course::instance($course->id);
        assign_capability('mod/hippotrack:reviewmyattempts', CAP_PROHIBIT, $studentrole->id, $coursecontext);
        assign_capability('mod/hippotrack:attempt', CAP_PROHIBIT, $studentrole->id, $coursecontext);

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $hippotrack->id, HIPPOTRACK_EVENT_TYPE_OPEN);

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Set current user to the student.
        $this->setUser($student);

        // Confirm null is returned.
        $this->assertNull(mod_hippotrack_core_calendar_provide_event_action($event, $factory));
    }

    public function test_hippotrack_core_calendar_provide_event_action_no_capability_for_user() {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Create a student.
        $student = $this->getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));

        // Enrol student.
        $this->assertTrue($this->getDataGenerator()->enrol_user($student->id, $course->id, $studentrole->id));

        // Create a hippotrack.
        $hippotrack = $this->getDataGenerator()->create_module('hippotrack', array('course' => $course->id));

        // Remove the permission to attempt or review the hippotrack for the student role.
        $coursecontext = \context_course::instance($course->id);
        assign_capability('mod/hippotrack:reviewmyattempts', CAP_PROHIBIT, $studentrole->id, $coursecontext);
        assign_capability('mod/hippotrack:attempt', CAP_PROHIBIT, $studentrole->id, $coursecontext);

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $hippotrack->id, HIPPOTRACK_EVENT_TYPE_OPEN);

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Confirm null is returned.
        $this->assertNull(mod_hippotrack_core_calendar_provide_event_action($event, $factory, $student->id));
    }

    public function test_hippotrack_core_calendar_provide_event_action_already_finished() {
        global $DB;

        $this->resetAfterTest();

        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Create a student.
        $student = $this->getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));

        // Enrol student.
        $this->assertTrue($this->getDataGenerator()->enrol_user($student->id, $course->id, $studentrole->id));

        // Create a hippotrack.
        $hippotrack = $this->getDataGenerator()->create_module('hippotrack', array('course' => $course->id,
            'sumgrades' => 1));

        // Add a question to the hippotrack.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $question = $questiongenerator->create_question('numerical', null, array('category' => $cat->id));
        hippotrack_add_hippotrack_question($question->id, $hippotrack);

        // Get the hippotrack object.
        $hippotrackobj = hippotrack::create($hippotrack->id, $student->id);

        // Create an attempt for the student in the hippotrack.
        $timenow = time();
        $attempt = hippotrack_create_attempt($hippotrackobj, 1, false, $timenow, false, $student->id);
        $quba = \question_engine::make_questions_usage_by_activity('mod_hippotrack', $hippotrackobj->get_context());
        $quba->set_preferred_behaviour($hippotrackobj->get_hippotrack()->preferredbehaviour);
        hippotrack_start_new_attempt($hippotrackobj, $quba, $attempt, 1, $timenow);
        hippotrack_attempt_save_started($hippotrackobj, $quba, $attempt);

        // Finish the attempt.
        $attemptobj = hippotrack_attempt::create($attempt->id);
        $attemptobj->process_finish($timenow, false);

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $hippotrack->id, HIPPOTRACK_EVENT_TYPE_OPEN);

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Set current user to the student.
        $this->setUser($student);

        // Confirm null is returned.
        $this->assertNull(mod_hippotrack_core_calendar_provide_event_action($event, $factory));
    }

    public function test_hippotrack_core_calendar_provide_event_action_already_finished_for_user() {
        global $DB;

        $this->resetAfterTest();

        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Create a student.
        $student = $this->getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));

        // Enrol student.
        $this->assertTrue($this->getDataGenerator()->enrol_user($student->id, $course->id, $studentrole->id));

        // Create a hippotrack.
        $hippotrack = $this->getDataGenerator()->create_module('hippotrack', array('course' => $course->id,
            'sumgrades' => 1));

        // Add a question to the hippotrack.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $question = $questiongenerator->create_question('numerical', null, array('category' => $cat->id));
        hippotrack_add_hippotrack_question($question->id, $hippotrack);

        // Get the hippotrack object.
        $hippotrackobj = hippotrack::create($hippotrack->id, $student->id);

        // Create an attempt for the student in the hippotrack.
        $timenow = time();
        $attempt = hippotrack_create_attempt($hippotrackobj, 1, false, $timenow, false, $student->id);
        $quba = \question_engine::make_questions_usage_by_activity('mod_hippotrack', $hippotrackobj->get_context());
        $quba->set_preferred_behaviour($hippotrackobj->get_hippotrack()->preferredbehaviour);
        hippotrack_start_new_attempt($hippotrackobj, $quba, $attempt, 1, $timenow);
        hippotrack_attempt_save_started($hippotrackobj, $quba, $attempt);

        // Finish the attempt.
        $attemptobj = hippotrack_attempt::create($attempt->id);
        $attemptobj->process_finish($timenow, false);

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $hippotrack->id, HIPPOTRACK_EVENT_TYPE_OPEN);

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Confirm null is returned.
        $this->assertNull(mod_hippotrack_core_calendar_provide_event_action($event, $factory, $student->id));
    }

    public function test_hippotrack_core_calendar_provide_event_action_already_completed() {
        $this->resetAfterTest();
        set_config('enablecompletion', 1);
        $this->setAdminUser();

        // Create the activity.
        $course = $this->getDataGenerator()->create_course(array('enablecompletion' => 1));
        $hippotrack = $this->getDataGenerator()->create_module('hippotrack', array('course' => $course->id),
            array('completion' => 2, 'completionview' => 1, 'completionexpected' => time() + DAYSECS));

        // Get some additional data.
        $cm = get_coursemodule_from_instance('hippotrack', $hippotrack->id);

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $hippotrack->id,
            \core_completion\api::COMPLETION_EVENT_TYPE_DATE_COMPLETION_EXPECTED);

        // Mark the activity as completed.
        $completion = new \completion_info($course);
        $completion->set_module_viewed($cm);

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event.
        $actionevent = mod_hippotrack_core_calendar_provide_event_action($event, $factory);

        // Ensure result was null.
        $this->assertNull($actionevent);
    }

    public function test_hippotrack_core_calendar_provide_event_action_already_completed_for_user() {
        $this->resetAfterTest();
        set_config('enablecompletion', 1);
        $this->setAdminUser();

        // Create the activity.
        $course = $this->getDataGenerator()->create_course(array('enablecompletion' => 1));
        $hippotrack = $this->getDataGenerator()->create_module('hippotrack', array('course' => $course->id),
            array('completion' => 2, 'completionview' => 1, 'completionexpected' => time() + DAYSECS));

        // Enrol a student in the course.
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        // Get some additional data.
        $cm = get_coursemodule_from_instance('hippotrack', $hippotrack->id);

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $hippotrack->id,
            \core_completion\api::COMPLETION_EVENT_TYPE_DATE_COMPLETION_EXPECTED);

        // Mark the activity as completed for the student.
        $completion = new \completion_info($course);
        $completion->set_module_viewed($cm, $student->id);

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event for the student.
        $actionevent = mod_hippotrack_core_calendar_provide_event_action($event, $factory, $student->id);

        // Ensure result was null.
        $this->assertNull($actionevent);
    }

    /**
     * Creates an action event.
     *
     * @param int $courseid
     * @param int $instanceid The hippotrack id.
     * @param string $eventtype The event type. eg. HIPPOTRACK_EVENT_TYPE_OPEN.
     * @return bool|calendar_event
     */
    private function create_action_event($courseid, $instanceid, $eventtype) {
        $event = new \stdClass();
        $event->name = 'Calendar event';
        $event->modulename  = 'hippotrack';
        $event->courseid = $courseid;
        $event->instance = $instanceid;
        $event->type = CALENDAR_EVENT_TYPE_ACTION;
        $event->eventtype = $eventtype;
        $event->timestart = time();

        return \calendar_event::create($event);
    }

    /**
     * Test the callback responsible for returning the completion rule descriptions.
     * This function should work given either an instance of the module (cm_info), such as when checking the active rules,
     * or if passed a stdClass of similar structure, such as when checking the the default completion settings for a mod type.
     */
    public function test_mod_hippotrack_completion_get_active_rule_descriptions() {
        $this->resetAfterTest();
        $this->setAdminUser();

        // Two activities, both with automatic completion. One has the 'completionsubmit' rule, one doesn't.
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 2]);
        $hippotrack1 = $this->getDataGenerator()->create_module('hippotrack', [
            'course' => $course->id,
            'completion' => 2,
            'completionusegrade' => 1,
            'completionpassgrade' => 1,
            'completionattemptsexhausted' => 1,
        ]);
        $hippotrack2 = $this->getDataGenerator()->create_module('hippotrack', [
            'course' => $course->id,
            'completion' => 2,
            'completionusegrade' => 0
        ]);
        $cm1 = \cm_info::create(get_coursemodule_from_instance('hippotrack', $hippotrack1->id));
        $cm2 = \cm_info::create(get_coursemodule_from_instance('hippotrack', $hippotrack2->id));

        // Data for the stdClass input type.
        // This type of input would occur when checking the default completion rules for an activity type, where we don't have
        // any access to cm_info, rather the input is a stdClass containing completion and customdata attributes, just like cm_info.
        $moddefaults = new \stdClass();
        $moddefaults->customdata = ['customcompletionrules' => [
            'completionattemptsexhausted' => 1,
        ]];
        $moddefaults->completion = 2;

        $activeruledescriptions = [
            get_string('completionpassorattemptsexhausteddesc', 'hippotrack'),
        ];
        $this->assertEquals(mod_hippotrack_get_completion_active_rule_descriptions($cm1), $activeruledescriptions);
        $this->assertEquals(mod_hippotrack_get_completion_active_rule_descriptions($cm2), []);
        $this->assertEquals(mod_hippotrack_get_completion_active_rule_descriptions($moddefaults), $activeruledescriptions);
        $this->assertEquals(mod_hippotrack_get_completion_active_rule_descriptions(new \stdClass()), []);
    }

    /**
     * A user who does not have capabilities to add events to the calendar should be able to create a hippotrack.
     */
    public function test_creation_with_no_calendar_capabilities() {
        $this->resetAfterTest();
        $course = self::getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $user = self::getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $roleid = self::getDataGenerator()->create_role();
        self::getDataGenerator()->role_assign($roleid, $user->id, $context->id);
        assign_capability('moodle/calendar:manageentries', CAP_PROHIBIT, $roleid, $context, true);
        $generator = self::getDataGenerator()->get_plugin_generator('mod_hippotrack');
        // Create an instance as a user without the calendar capabilities.
        $this->setUser($user);
        $time = time();
        $params = array(
            'course' => $course->id,
            'timeopen' => $time + 200,
            'timeclose' => $time + 2000,
        );
        $generator->create_instance($params);
    }
}
