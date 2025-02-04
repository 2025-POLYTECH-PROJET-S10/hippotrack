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

namespace mod_hippotrack;

use question_bank;
use question_engine;
use quiz;
use hippotrack_attempt;

/**
 * Quiz attempt walk through.
 *
 * @package    mod_hippotrack
 * @category   test
 * @copyright  2013 The Open University
 * @author     Jamie Pratt <me@jamiep.org>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \hippotrack_attempt
 */
final class attempt_walkthrough_test extends \advanced_testcase {
    #[\Override]
    public static function setUpBeforeClass(): void {
        global $CFG;

        parent::setUpBeforeClass();

        require_once($CFG->dirroot . '/mod/hippotrack/locallib.php');
    }

    /**
     * Create a quiz with questions and walk through a quiz attempt.
     */
    public function test_hippotrack_attempt_walkthrough() {
        global $SITE;

        $this->resetAfterTest(true);

        // Make a quiz.
        $quizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_hippotrack');

        $quiz = $quizgenerator->create_instance(array('course'=>$SITE->id, 'questionsperpage' => 0, 'grade' => 100.0,
                                                      'sumgrades' => 3));

        // Create a couple of questions.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');

        $cat = $questiongenerator->create_question_category();
        $saq = $questiongenerator->create_question('shortanswer', null, array('category' => $cat->id));
        $numq = $questiongenerator->create_question('numerical', null, array('category' => $cat->id));
        $matchq = $questiongenerator->create_question('match', null, ['category' => $cat->id]);
        $description = $questiongenerator->create_question('description', null, ['category' => $cat->id]);

        // Add them to the quiz.
        hippotrack_add_hippotrack_question($saq->id, $quiz);
        hippotrack_add_hippotrack_question($numq->id, $quiz);
        hippotrack_add_hippotrack_question($matchq->id, $quiz);
        hippotrack_add_hippotrack_question($description->id, $quiz);

        // Make a user to do the quiz.
        $user1 = $this->getDataGenerator()->create_user();

        $quizobj = quiz::create($quiz->id, $user1->id);

        // Start the attempt.
        $quba = question_engine::make_questions_usage_by_activity('mod_hippotrack', $quizobj->get_context());
        $quba->set_preferred_behaviour($quizobj->get_quiz()->preferredbehaviour);

        $timenow = time();
        $attempt = hippotrack_create_attempt($quizobj, 1, false, $timenow, false, $user1->id);

        hippotrack_start_new_attempt($quizobj, $quba, $attempt, 1, $timenow);
        $this->assertEquals('1,2,3,4,0', $attempt->layout);

        hippotrack_attempt_save_started($quizobj, $quba, $attempt);

        // Process some responses from the student.
        $attemptobj = hippotrack_attempt::create($attempt->id);
        $this->assertFalse($attemptobj->has_response_to_at_least_one_graded_question());
        // The student has not answered any questions.
        $this->assertEquals(3, $attemptobj->get_number_of_unanswered_questions());

        $tosubmit = array(1 => array('answer' => 'frog'),
                          2 => array('answer' => '3.14'));

        $attemptobj->process_submitted_actions($timenow, false, $tosubmit);
        // The student has answered two questions, and only one remaining.
        $this->assertEquals(1, $attemptobj->get_number_of_unanswered_questions());

        $tosubmit = [
            3 => [
                'frog' => 'amphibian',
                'cat' => 'mammal',
                'newt' => ''
            ]
        ];

        $attemptobj->process_submitted_actions($timenow, false, $tosubmit);
        // The student has answered three questions but one is invalid, so there is still one remaining.
        $this->assertEquals(1, $attemptobj->get_number_of_unanswered_questions());

        $tosubmit = [
            3 => [
                'frog' => 'amphibian',
                'cat' => 'mammal',
                'newt' => 'amphibian'
            ]
        ];

        $attemptobj->process_submitted_actions($timenow, false, $tosubmit);
        // The student has answered three questions, so there are no remaining.
        $this->assertEquals(0, $attemptobj->get_number_of_unanswered_questions());

        // Finish the attempt.
        $attemptobj = hippotrack_attempt::create($attempt->id);
        $this->assertTrue($attemptobj->has_response_to_at_least_one_graded_question());
        $attemptobj->process_finish($timenow, false);

        // Re-load quiz attempt data.
        $attemptobj = hippotrack_attempt::create($attempt->id);

        // Check that results are stored as expected.
        $this->assertEquals(1, $attemptobj->get_attempt_number());
        $this->assertEquals(3, $attemptobj->get_sum_marks());
        $this->assertEquals(true, $attemptobj->is_finished());
        $this->assertEquals($timenow, $attemptobj->get_submitted_date());
        $this->assertEquals($user1->id, $attemptobj->get_userid());
        $this->assertTrue($attemptobj->has_response_to_at_least_one_graded_question());
        $this->assertEquals(0, $attemptobj->get_number_of_unanswered_questions());

        // Check quiz grades.
        $grades = hippotrack_get_user_grades($quiz, $user1->id);
        $grade = array_shift($grades);
        $this->assertEquals(100.0, $grade->rawgrade);

        // Check grade book.
        $gradebookgrades = grade_get_grades($SITE->id, 'mod', 'quiz', $quiz->id, $user1->id);
        $gradebookitem = array_shift($gradebookgrades->items);
        $gradebookgrade = array_shift($gradebookitem->grades);
        $this->assertEquals(100, $gradebookgrade->grade);
    }

    /**
     * Create a quiz containing one question and a close time.
     *
     * The question is the standard shortanswer test question.
     * The quiz is set to close 1 hour from now.
     * The quiz is set to use a grade period of 1 hour once time expires.
     *
     * @return \stdClass the quiz that was created.
     */
    protected function create_hippotrack_with_one_question(): \stdClass {
        global $SITE;
        $this->resetAfterTest();

        // Make a quiz.
        $timeclose = time() + HOURSECS;
        $quizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_hippotrack');

        $quiz = $quizgenerator->create_instance(
                ['course' => $SITE->id, 'timeclose' => $timeclose,
                        'overduehandling' => 'graceperiod', 'graceperiod' => HOURSECS]);

        // Create a question.
        /** @var \core_question_generator $questiongenerator */
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $saq = $questiongenerator->create_question('shortanswer', null, array('category' => $cat->id));

        // Add them to the quiz.
        hippotrack_add_hippotrack_question($saq->id, $quiz, 0, 1);
        hippotrack_update_sumgrades($quiz);

        return $quiz;
    }

    public function test_hippotrack_attempt_walkthrough_submit_time_recorded_correctly_when_overdue() {

        $quiz = $this->create_hippotrack_with_one_question();

        // Make a user to do the quiz.
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $quizobj = quiz::create($quiz->id, $user->id);

        // Start the attempt.
        $attempt = hippotrack_prepare_and_start_new_attempt($quizobj, 1, null);

        // Process some responses from the student.
        $attemptobj = hippotrack_attempt::create($attempt->id);
        $this->assertEquals(1, $attemptobj->get_number_of_unanswered_questions());
        $attemptobj->process_submitted_actions($quiz->timeclose - 30 * MINSECS, false, [1 => ['answer' => 'frog']]);

        // Attempt goes overdue (e.g. if cron ran).
        $attemptobj = hippotrack_attempt::create($attempt->id);
        $attemptobj->process_going_overdue($quiz->timeclose + 2 * get_config('quiz', 'graceperiodmin'), false);

        // Verify the attempt state.
        $attemptobj = hippotrack_attempt::create($attempt->id);
        $this->assertEquals(1, $attemptobj->get_attempt_number());
        $this->assertEquals(false, $attemptobj->is_finished());
        $this->assertEquals(0, $attemptobj->get_submitted_date());
        $this->assertEquals($user->id, $attemptobj->get_userid());
        $this->assertTrue($attemptobj->has_response_to_at_least_one_graded_question());
        $this->assertEquals(0, $attemptobj->get_number_of_unanswered_questions());

        // Student submits the attempt during the grace period.
        $attemptobj = hippotrack_attempt::create($attempt->id);
        $attemptobj->process_attempt($quiz->timeclose + 30 * MINSECS, true, false, 1);

        // Verify the attempt state.
        $attemptobj = hippotrack_attempt::create($attempt->id);
        $this->assertEquals(1, $attemptobj->get_attempt_number());
        $this->assertEquals(true, $attemptobj->is_finished());
        $this->assertEquals($quiz->timeclose + 30 * MINSECS, $attemptobj->get_submitted_date());
        $this->assertEquals($user->id, $attemptobj->get_userid());
        $this->assertTrue($attemptobj->has_response_to_at_least_one_graded_question());
        $this->assertEquals(0, $attemptobj->get_number_of_unanswered_questions());
    }

    public function test_hippotrack_attempt_walkthrough_close_time_extended_at_last_minute() {
        global $DB;

        $quiz = $this->create_hippotrack_with_one_question();
        $originaltimeclose = $quiz->timeclose;

        // Make a user to do the quiz.
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $quizobj = quiz::create($quiz->id, $user->id);

        // Start the attempt.
        $attempt = hippotrack_prepare_and_start_new_attempt($quizobj, 1, null);

        // Process some responses from the student during the attempt.
        $attemptobj = hippotrack_attempt::create($attempt->id);
        $attemptobj->process_submitted_actions($originaltimeclose - 30 * MINSECS, false, [1 => ['answer' => 'frog']]);

        // Teacher edits the quiz to extend the time-limit by one minute.
        $DB->set_field('quiz', 'timeclose', $originaltimeclose + MINSECS, ['id' => $quiz->id]);
        \course_modinfo::clear_instance_cache($quiz->course);

        // Timer expires in the student browser and thinks it is time to submit the quiz.
        // This sets $finishattempt to false - since the student did not click the button, and $timeup to true.
        $attemptobj = hippotrack_attempt::create($attempt->id);
        $attemptobj->process_attempt($originaltimeclose, false, true, 1);

        // Verify the attempt state - the $timeup was ignored becuase things have changed server-side.
        $attemptobj = hippotrack_attempt::create($attempt->id);
        $this->assertEquals(1, $attemptobj->get_attempt_number());
        $this->assertFalse($attemptobj->is_finished());
        $this->assertEquals(hippotrack_attempt::IN_PROGRESS, $attemptobj->get_state());
        $this->assertEquals(0, $attemptobj->get_submitted_date());
        $this->assertEquals($user->id, $attemptobj->get_userid());
    }

    /**
     * Create a quiz with a random as well as other questions and walk through quiz attempts.
     */
    public function test_hippotrack_with_random_question_attempt_walkthrough() {
        global $SITE;

        $this->resetAfterTest(true);
        question_bank::get_qtype('random')->clear_caches_before_testing();

        $this->setAdminUser();

        // Make a quiz.
        $quizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_hippotrack');

        $quiz = $quizgenerator->create_instance(array('course' => $SITE->id, 'questionsperpage' => 2, 'grade' => 100.0,
                                                      'sumgrades' => 4));

        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');

        // Add two questions to question category.
        $cat = $questiongenerator->create_question_category();
        $saq = $questiongenerator->create_question('shortanswer', null, array('category' => $cat->id));
        $numq = $questiongenerator->create_question('numerical', null, array('category' => $cat->id));

        // Add random question to the quiz.
        hippotrack_add_random_questions($quiz, 0, $cat->id, 1, false);

        // Make another category.
        $cat2 = $questiongenerator->create_question_category();
        $match = $questiongenerator->create_question('match', null, array('category' => $cat->id));

        hippotrack_add_hippotrack_question($match->id, $quiz, 0);

        $multichoicemulti = $questiongenerator->create_question('multichoice', 'two_of_four', array('category' => $cat->id));

        hippotrack_add_hippotrack_question($multichoicemulti->id, $quiz, 0);

        $multichoicesingle = $questiongenerator->create_question('multichoice', 'one_of_four', array('category' => $cat->id));

        hippotrack_add_hippotrack_question($multichoicesingle->id, $quiz, 0);

        foreach (array($saq->id => 'frog', $numq->id => '3.14') as $randomqidtoselect => $randqanswer) {
            // Make a new user to do the quiz each loop.
            $user1 = $this->getDataGenerator()->create_user();
            $this->setUser($user1);

            $quizobj = quiz::create($quiz->id, $user1->id);

            // Start the attempt.
            $quba = question_engine::make_questions_usage_by_activity('mod_hippotrack', $quizobj->get_context());
            $quba->set_preferred_behaviour($quizobj->get_quiz()->preferredbehaviour);

            $timenow = time();
            $attempt = hippotrack_create_attempt($quizobj, 1, false, $timenow);

            hippotrack_start_new_attempt($quizobj, $quba, $attempt, 1, $timenow, array(1 => $randomqidtoselect));
            $this->assertEquals('1,2,0,3,4,0', $attempt->layout);

            hippotrack_attempt_save_started($quizobj, $quba, $attempt);

            // Process some responses from the student.
            $attemptobj = hippotrack_attempt::create($attempt->id);
            $this->assertFalse($attemptobj->has_response_to_at_least_one_graded_question());
            $this->assertEquals(4, $attemptobj->get_number_of_unanswered_questions());

            $tosubmit = array();
            $selectedquestionid = $quba->get_question_attempt(1)->get_question_id();
            $tosubmit[1] = array('answer' => $randqanswer);
            $tosubmit[2] = array(
                'frog' => 'amphibian',
                'cat'  => 'mammal',
                'newt' => 'amphibian');
            $tosubmit[3] = array('One' => '1', 'Two' => '0', 'Three' => '1', 'Four' => '0'); // First and third choice.
            $tosubmit[4] = array('answer' => 'One'); // The first choice.

            $attemptobj->process_submitted_actions($timenow, false, $tosubmit);

            // Finish the attempt.
            $attemptobj = hippotrack_attempt::create($attempt->id);
            $this->assertTrue($attemptobj->has_response_to_at_least_one_graded_question());
            $this->assertEquals(0, $attemptobj->get_number_of_unanswered_questions());
            $attemptobj->process_finish($timenow, false);

            // Re-load quiz attempt data.
            $attemptobj = hippotrack_attempt::create($attempt->id);

            // Check that results are stored as expected.
            $this->assertEquals(1, $attemptobj->get_attempt_number());
            $this->assertEquals(4, $attemptobj->get_sum_marks());
            $this->assertEquals(true, $attemptobj->is_finished());
            $this->assertEquals($timenow, $attemptobj->get_submitted_date());
            $this->assertEquals($user1->id, $attemptobj->get_userid());
            $this->assertTrue($attemptobj->has_response_to_at_least_one_graded_question());
            $this->assertEquals(0, $attemptobj->get_number_of_unanswered_questions());

            // Check quiz grades.
            $grades = hippotrack_get_user_grades($quiz, $user1->id);
            $grade = array_shift($grades);
            $this->assertEquals(100.0, $grade->rawgrade);

            // Check grade book.
            $gradebookgrades = grade_get_grades($SITE->id, 'mod', 'quiz', $quiz->id, $user1->id);
            $gradebookitem = array_shift($gradebookgrades->items);
            $gradebookgrade = array_shift($gradebookitem->grades);
            $this->assertEquals(100, $gradebookgrade->grade);
        }
    }

    public static function get_correct_response_for_variants(): array {
        return [[1, 9.9], [2, 8.5], [5, 14.2], [10, 6.8, true]];
    }

    protected $quizwithvariants = null;

    /**
     * Create a quiz with a single question with variants and walk through quiz attempts.
     *
     * @dataProvider get_correct_response_for_variants
     */
    public function test_hippotrack_with_question_with_variants_attempt_walkthrough($variantno, $correctresponse, $done = false) {
        global $SITE;

        $this->resetAfterTest($done);

        $this->setAdminUser();

        if ($this->quizwithvariants === null) {
            // Make a quiz.
            $quizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_hippotrack');

            $this->quizwithvariants = $quizgenerator->create_instance(array('course'=>$SITE->id,
                                                                            'questionsperpage' => 0,
                                                                            'grade' => 100.0,
                                                                            'sumgrades' => 1));

            $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');

            $cat = $questiongenerator->create_question_category();
            $calc = $questiongenerator->create_question('calculatedsimple', 'sumwithvariants', array('category' => $cat->id));
            hippotrack_add_hippotrack_question($calc->id, $this->quizwithvariants, 0);
        }


        // Make a new user to do the quiz.
        $user1 = $this->getDataGenerator()->create_user();
        $this->setUser($user1);
        $quizobj = quiz::create($this->quizwithvariants->id, $user1->id);

        // Start the attempt.
        $quba = question_engine::make_questions_usage_by_activity('mod_hippotrack', $quizobj->get_context());
        $quba->set_preferred_behaviour($quizobj->get_quiz()->preferredbehaviour);

        $timenow = time();
        $attempt = hippotrack_create_attempt($quizobj, 1, false, $timenow);

        // Select variant.
        hippotrack_start_new_attempt($quizobj, $quba, $attempt, 1, $timenow, array(), array(1 => $variantno));
        $this->assertEquals('1,0', $attempt->layout);
        hippotrack_attempt_save_started($quizobj, $quba, $attempt);

        // Process some responses from the student.
        $attemptobj = hippotrack_attempt::create($attempt->id);
        $this->assertFalse($attemptobj->has_response_to_at_least_one_graded_question());
        $this->assertEquals(1, $attemptobj->get_number_of_unanswered_questions());

        $tosubmit = array(1 => array('answer' => $correctresponse));
        $attemptobj->process_submitted_actions($timenow, false, $tosubmit);

        // Finish the attempt.
        $attemptobj = hippotrack_attempt::create($attempt->id);
        $this->assertTrue($attemptobj->has_response_to_at_least_one_graded_question());
        $this->assertEquals(0, $attemptobj->get_number_of_unanswered_questions());

        $attemptobj->process_finish($timenow, false);

        // Re-load quiz attempt data.
        $attemptobj = hippotrack_attempt::create($attempt->id);

        // Check that results are stored as expected.
        $this->assertEquals(1, $attemptobj->get_attempt_number());
        $this->assertEquals(1, $attemptobj->get_sum_marks());
        $this->assertEquals(true, $attemptobj->is_finished());
        $this->assertEquals($timenow, $attemptobj->get_submitted_date());
        $this->assertEquals($user1->id, $attemptobj->get_userid());
        $this->assertTrue($attemptobj->has_response_to_at_least_one_graded_question());
        $this->assertEquals(0, $attemptobj->get_number_of_unanswered_questions());

        // Check quiz grades.
        $grades = hippotrack_get_user_grades($this->quizwithvariants, $user1->id);
        $grade = array_shift($grades);
        $this->assertEquals(100.0, $grade->rawgrade);

        // Check grade book.
        $gradebookgrades = grade_get_grades($SITE->id, 'mod', 'quiz', $this->quizwithvariants->id, $user1->id);
        $gradebookitem = array_shift($gradebookgrades->items);
        $gradebookgrade = array_shift($gradebookitem->grades);
        $this->assertEquals(100, $gradebookgrade->grade);
    }
}
