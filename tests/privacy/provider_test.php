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
 * Privacy provider tests.
 *
 * @package    mod_hippotrack
 * @copyright  2018 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_hippotrack\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\deletion_criteria;
use core_privacy\local\request\writer;
use mod_hippotrack\privacy\provider;
use mod_hippotrack\privacy\helper;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/tests/privacy_helper.php');

/**
 * Privacy provider tests class.
 *
 * @package    mod_hippotrack
 * @copyright  2018 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider_test extends \core_privacy\tests\provider_testcase {

    use \core_question_privacy_helper;

    /**
     * Test that a user who has no data gets no contexts
     */
    public function test_get_contexts_for_userid_no_data() {
        global $USER;
        $this->resetAfterTest();
        $this->setAdminUser();

        $contextlist = provider::get_contexts_for_userid($USER->id);
        $this->assertEmpty($contextlist);
    }

    /**
     * Test for provider::get_contexts_for_userid() when there is no hippotrack attempt at all.
     */
    public function test_get_contexts_for_userid_no_attempt_with_override() {
        global $DB;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        // Make a hippotrack with an override.
        $this->setUser();
        $hippotrack = $this->create_test_hippotrack($course);
        $DB->insert_record('hippotrack_overrides', [
            'hippotrack' => $hippotrack->id,
            'userid' => $user->id,
            'timeclose' => 1300,
            'timelimit' => null,
        ]);

        $cm = get_coursemodule_from_instance('hippotrack', $hippotrack->id);
        $context = \context_module::instance($cm->id);

        // Fetch the contexts - only one context should be returned.
        $this->setUser();
        $contextlist = provider::get_contexts_for_userid($user->id);
        $this->assertCount(1, $contextlist);
        $this->assertEquals($context, $contextlist->current());
    }

    /**
     * The export function should handle an empty contextlist properly.
     */
    public function test_export_user_data_no_data() {
        global $USER;
        $this->resetAfterTest();
        $this->setAdminUser();

        $approvedcontextlist = new \core_privacy\tests\request\approved_contextlist(
            \core_user::get_user($USER->id),
            'mod_hippotrack',
            []
        );

        provider::export_user_data($approvedcontextlist);
        $this->assertDebuggingNotCalled();

        // No data should have been exported.
        $writer = \core_privacy\local\request\writer::with_context(\context_system::instance());
        $this->assertFalse($writer->has_any_data_in_any_context());
    }

    /**
     * The delete function should handle an empty contextlist properly.
     */
    public function test_delete_data_for_user_no_data() {
        global $USER;
        $this->resetAfterTest();
        $this->setAdminUser();

        $approvedcontextlist = new \core_privacy\tests\request\approved_contextlist(
            \core_user::get_user($USER->id),
            'mod_hippotrack',
            []
        );

        provider::delete_data_for_user($approvedcontextlist);
        $this->assertDebuggingNotCalled();
    }

    /**
     * Export + Delete hippotrack data for a user who has made a single attempt.
     */
    public function test_user_with_data() {
        global $DB;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $otheruser = $this->getDataGenerator()->create_user();

        // Make a hippotrack with an override.
        $this->setUser();
        $hippotrack = $this->create_test_hippotrack($course);
        $DB->insert_record('hippotrack_overrides', [
                'hippotrack' => $hippotrack->id,
                'userid' => $user->id,
                'timeclose' => 1300,
                'timelimit' => null,
            ]);

        // Run as the user and make an attempt on the hippotrack.
        list($hippotrackobj, $quba, $attemptobj) = $this->attempt_hippotrack($hippotrack, $user);
        $this->attempt_hippotrack($hippotrack, $otheruser);
        $context = $hippotrackobj->get_context();

        // Fetch the contexts - only one context should be returned.
        $this->setUser();
        $contextlist = provider::get_contexts_for_userid($user->id);
        $this->assertCount(1, $contextlist);
        $this->assertEquals($context, $contextlist->current());

        // Perform the export and check the data.
        $this->setUser($user);
        $approvedcontextlist = new \core_privacy\tests\request\approved_contextlist(
            \core_user::get_user($user->id),
            'mod_hippotrack',
            $contextlist->get_contextids()
        );
        provider::export_user_data($approvedcontextlist);

        // Ensure that the hippotrack data was exported correctly.
        $writer = writer::with_context($context);
        $this->assertTrue($writer->has_any_data());

        $hippotrackdata = $writer->get_data([]);
        $this->assertEquals($hippotrackobj->get_hippotrack_name(), $hippotrackdata->name);

        // Every module has an intro.
        $this->assertTrue(isset($hippotrackdata->intro));

        // Fetch the attempt data.
        $attempt = $attemptobj->get_attempt();
        $attemptsubcontext = [
            get_string('attempts', 'mod_hippotrack'),
            $attempt->attempt,
        ];
        $attemptdata = writer::with_context($context)->get_data($attemptsubcontext);

        $attempt = $attemptobj->get_attempt();
        $this->assertTrue(isset($attemptdata->state));
        $this->assertEquals(\hippotrack_attempt::state_name($attemptobj->get_state()), $attemptdata->state);
        $this->assertTrue(isset($attemptdata->timestart));
        $this->assertTrue(isset($attemptdata->timefinish));
        $this->assertTrue(isset($attemptdata->timemodified));
        $this->assertFalse(isset($attemptdata->timemodifiedoffline));
        $this->assertFalse(isset($attemptdata->timecheckstate));

        $this->assertTrue(isset($attemptdata->grade));
        $this->assertEquals(100.00, $attemptdata->grade->grade);

        // Check that the exported question attempts are correct.
        $attemptsubcontext = helper::get_hippotrack_attempt_subcontext($attemptobj->get_attempt(), $user);
        $this->assert_question_attempt_exported(
            $context,
            $attemptsubcontext,
            \question_engine::load_questions_usage_by_activity($attemptobj->get_uniqueid()),
            hippotrack_get_review_options($hippotrack, $attemptobj->get_attempt(), $context),
            $user
        );

        // Delete the data and check it is removed.
        $this->setUser();
        provider::delete_data_for_user($approvedcontextlist);
        $this->expectException(\dml_missing_record_exception::class);
        \hippotrack_attempt::create($attemptobj->get_hippotrackid());
    }

    /**
     * Export + Delete hippotrack data for a user who has made a single attempt.
     */
    public function test_user_with_preview() {
        global $DB;
        $this->resetAfterTest(true);

        // Make a hippotrack.
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $hippotrackgenerator = $this->getDataGenerator()->get_plugin_generator('mod_hippotrack');

        $hippotrack = $hippotrackgenerator->create_instance([
                'course' => $course->id,
                'questionsperpage' => 0,
                'grade' => 100.0,
                'sumgrades' => 2,
            ]);

        // Create a couple of questions.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();

        $saq = $questiongenerator->create_question('shortanswer', null, array('category' => $cat->id));
        hippotrack_add_hippotrack_question($saq->id, $hippotrack);
        $numq = $questiongenerator->create_question('numerical', null, array('category' => $cat->id));
        hippotrack_add_hippotrack_question($numq->id, $hippotrack);

        // Run as the user and make an attempt on the hippotrack.
        $this->setUser($user);
        $starttime = time();
        $hippotrackobj = \hippotrack::create($hippotrack->id, $user->id);
        $context = $hippotrackobj->get_context();

        $quba = \question_engine::make_questions_usage_by_activity('mod_hippotrack', $hippotrackobj->get_context());
        $quba->set_preferred_behaviour($hippotrackobj->get_hippotrack()->preferredbehaviour);

        // Start the attempt.
        $attempt = hippotrack_create_attempt($hippotrackobj, 1, false, $starttime, true, $user->id);
        hippotrack_start_new_attempt($hippotrackobj, $quba, $attempt, 1, $starttime);
        hippotrack_attempt_save_started($hippotrackobj, $quba, $attempt);

        // Answer the questions.
        $attemptobj = \hippotrack_attempt::create($attempt->id);

        $tosubmit = [
            1 => ['answer' => 'frog'],
            2 => ['answer' => '3.14'],
        ];

        $attemptobj->process_submitted_actions($starttime, false, $tosubmit);

        // Finish the attempt.
        $attemptobj = \hippotrack_attempt::create($attempt->id);
        $this->assertTrue($attemptobj->has_response_to_at_least_one_graded_question());
        $attemptobj->process_finish($starttime, false);

        // Fetch the contexts - no context should be returned.
        $this->setUser();
        $contextlist = provider::get_contexts_for_userid($user->id);
        $this->assertCount(0, $contextlist);
    }

    /**
     * Export + Delete hippotrack data for a user who has made a single attempt.
     */
    public function test_delete_data_for_all_users_in_context() {
        global $DB;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $otheruser = $this->getDataGenerator()->create_user();

        // Make a hippotrack with an override.
        $this->setUser();
        $hippotrack = $this->create_test_hippotrack($course);
        $DB->insert_record('hippotrack_overrides', [
                'hippotrack' => $hippotrack->id,
                'userid' => $user->id,
                'timeclose' => 1300,
                'timelimit' => null,
            ]);

        // Run as the user and make an attempt on the hippotrack.
        list($hippotrackobj, $quba, $attemptobj) = $this->attempt_hippotrack($hippotrack, $user);
        list($hippotrackobj, $quba, $attemptobj) = $this->attempt_hippotrack($hippotrack, $otheruser);

        // Create another hippotrack and questions, and repeat the data insertion.
        $this->setUser();
        $otherhippotrack = $this->create_test_hippotrack($course);
        $DB->insert_record('hippotrack_overrides', [
                'hippotrack' => $otherhippotrack->id,
                'userid' => $user->id,
                'timeclose' => 1300,
                'timelimit' => null,
            ]);

        // Run as the user and make an attempt on the hippotrack.
        list($otherhippotrackobj, $otherquba, $otherattemptobj) = $this->attempt_hippotrack($otherhippotrack, $user);
        list($otherhippotrackobj, $otherquba, $otherattemptobj) = $this->attempt_hippotrack($otherhippotrack, $otheruser);

        // Delete all data for all users in the context under test.
        $this->setUser();
        $context = $hippotrackobj->get_context();
        provider::delete_data_for_all_users_in_context($context);

        // The hippotrack attempt should have been deleted from this hippotrack.
        $this->assertCount(0, $DB->get_records('hippotrack_attempts', ['hippotrack' => $hippotrackobj->get_hippotrackid()]));
        $this->assertCount(0, $DB->get_records('hippotrack_overrides', ['hippotrack' => $hippotrackobj->get_hippotrackid()]));
        $this->assertCount(0, $DB->get_records('question_attempts', ['questionusageid' => $quba->get_id()]));

        // But not for the other hippotrack.
        $this->assertNotCount(0, $DB->get_records('hippotrack_attempts', ['hippotrack' => $otherhippotrackobj->get_hippotrackid()]));
        $this->assertNotCount(0, $DB->get_records('hippotrack_overrides', ['hippotrack' => $otherhippotrackobj->get_hippotrackid()]));
        $this->assertNotCount(0, $DB->get_records('question_attempts', ['questionusageid' => $otherquba->get_id()]));
    }

    /**
     * Export + Delete hippotrack data for a user who has made a single attempt.
     */
    public function test_wrong_context() {
        global $DB;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        // Make a choice.
        $this->setUser();
        $plugingenerator = $this->getDataGenerator()->get_plugin_generator('mod_choice');
        $choice = $plugingenerator->create_instance(['course' => $course->id]);
        $cm = get_coursemodule_from_instance('choice', $choice->id);
        $context = \context_module::instance($cm->id);

        // Fetch the contexts - no context should be returned.
        $this->setUser();
        $contextlist = provider::get_contexts_for_userid($user->id);
        $this->assertCount(0, $contextlist);

        // Perform the export and check the data.
        $this->setUser($user);
        $approvedcontextlist = new \core_privacy\tests\request\approved_contextlist(
            \core_user::get_user($user->id),
            'mod_hippotrack',
            [$context->id]
        );
        provider::export_user_data($approvedcontextlist);

        // Ensure that nothing was exported.
        $writer = writer::with_context($context);
        $this->assertFalse($writer->has_any_data_in_any_context());

        $this->setUser();

        $dbwrites = $DB->perf_get_writes();

        // Perform a deletion with the approved contextlist containing an incorrect context.
        $approvedcontextlist = new \core_privacy\tests\request\approved_contextlist(
            \core_user::get_user($user->id),
            'mod_hippotrack',
            [$context->id]
        );
        provider::delete_data_for_user($approvedcontextlist);
        $this->assertEquals($dbwrites, $DB->perf_get_writes());
        $this->assertDebuggingNotCalled();

        // Perform a deletion of all data in the context.
        provider::delete_data_for_all_users_in_context($context);
        $this->assertEquals($dbwrites, $DB->perf_get_writes());
        $this->assertDebuggingNotCalled();
    }

    /**
     * Create a test hippotrack for the specified course.
     *
     * @param   \stdClass $course
     * @return  array
     */
    protected function create_test_hippotrack($course) {
        global $DB;

        $hippotrackgenerator = $this->getDataGenerator()->get_plugin_generator('mod_hippotrack');

        $hippotrack = $hippotrackgenerator->create_instance([
                'course' => $course->id,
                'questionsperpage' => 0,
                'grade' => 100.0,
                'sumgrades' => 2,
            ]);

        // Create a couple of questions.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();

        $saq = $questiongenerator->create_question('shortanswer', null, array('category' => $cat->id));
        hippotrack_add_hippotrack_question($saq->id, $hippotrack);
        $numq = $questiongenerator->create_question('numerical', null, array('category' => $cat->id));
        hippotrack_add_hippotrack_question($numq->id, $hippotrack);

        return $hippotrack;
    }

    /**
     * Answer questions for a hippotrack + user.
     *
     * @param   \stdClass   $hippotrack
     * @param   \stdClass   $user
     * @return  array
     */
    protected function attempt_hippotrack($hippotrack, $user) {
        $this->setUser($user);

        $starttime = time();
        $hippotrackobj = \hippotrack::create($hippotrack->id, $user->id);
        $context = $hippotrackobj->get_context();

        $quba = \question_engine::make_questions_usage_by_activity('mod_hippotrack', $hippotrackobj->get_context());
        $quba->set_preferred_behaviour($hippotrackobj->get_hippotrack()->preferredbehaviour);

        // Start the attempt.
        $attempt = hippotrack_create_attempt($hippotrackobj, 1, false, $starttime, false, $user->id);
        hippotrack_start_new_attempt($hippotrackobj, $quba, $attempt, 1, $starttime);
        hippotrack_attempt_save_started($hippotrackobj, $quba, $attempt);

        // Answer the questions.
        $attemptobj = \hippotrack_attempt::create($attempt->id);

        $tosubmit = [
            1 => ['answer' => 'frog'],
            2 => ['answer' => '3.14'],
        ];

        $attemptobj->process_submitted_actions($starttime, false, $tosubmit);

        // Finish the attempt.
        $attemptobj = \hippotrack_attempt::create($attempt->id);
        $attemptobj->process_finish($starttime, false);

        $this->setUser();

        return [$hippotrackobj, $quba, $attemptobj];
    }

    /**
     * Test for provider::get_users_in_context().
     */
    public function test_get_users_in_context() {
        global $DB;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $anotheruser = $this->getDataGenerator()->create_user();
        $extrauser = $this->getDataGenerator()->create_user();

        // Make a hippotrack.
        $this->setUser();
        $hippotrack = $this->create_test_hippotrack($course);

        // Create an override for user1.
        $DB->insert_record('hippotrack_overrides', [
            'hippotrack' => $hippotrack->id,
            'userid' => $user->id,
            'timeclose' => 1300,
            'timelimit' => null,
        ]);

        // Make an attempt on the hippotrack as user2.
        list($hippotrackobj, $quba, $attemptobj) = $this->attempt_hippotrack($hippotrack, $anotheruser);
        $context = $hippotrackobj->get_context();

        // Fetch users - user1 and user2 should be returned.
        $userlist = new \core_privacy\local\request\userlist($context, 'mod_hippotrack');
        provider::get_users_in_context($userlist);
        $this->assertEqualsCanonicalizing(
                [$user->id, $anotheruser->id],
                $userlist->get_userids());
    }

    /**
     * Test for provider::delete_data_for_users().
     */
    public function test_delete_data_for_users() {
        global $DB;
        $this->resetAfterTest(true);

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();

        $course1 = $this->getDataGenerator()->create_course();
        $course2 = $this->getDataGenerator()->create_course();

        // Make a hippotrack in each course.
        $hippotrack1 = $this->create_test_hippotrack($course1);
        $hippotrack2 = $this->create_test_hippotrack($course2);

        // Attempt hippotrack1 as user1 and user2.
        list($hippotrack1obj) = $this->attempt_hippotrack($hippotrack1, $user1);
        $this->attempt_hippotrack($hippotrack1, $user2);

        // Create an override in hippotrack1 for user3.
        $DB->insert_record('hippotrack_overrides', [
            'hippotrack' => $hippotrack1->id,
            'userid' => $user3->id,
            'timeclose' => 1300,
            'timelimit' => null,
        ]);

        // Attempt hippotrack2 as user1.
        $this->attempt_hippotrack($hippotrack2, $user1);

        // Delete the data for user1 and user3 in course1 and check it is removed.
        $hippotrack1context = $hippotrack1obj->get_context();
        $approveduserlist = new \core_privacy\local\request\approved_userlist($hippotrack1context, 'mod_hippotrack',
                [$user1->id, $user3->id]);
        provider::delete_data_for_users($approveduserlist);

        // Only the attempt of user2 should be remained in hippotrack1.
        $this->assertEquals(
                [$user2->id],
                $DB->get_fieldset_select('hippotrack_attempts', 'userid', 'hippotrack = ?', [$hippotrack1->id])
        );

        // The attempt that user1 made in hippotrack2 should be remained.
        $this->assertEquals(
                [$user1->id],
                $DB->get_fieldset_select('hippotrack_attempts', 'userid', 'hippotrack = ?', [$hippotrack2->id])
        );

        // The hippotrack override in hippotrack1 that we had for user3 should be deleted.
        $this->assertEquals(
                [],
                $DB->get_fieldset_select('hippotrack_overrides', 'userid', 'hippotrack = ?', [$hippotrack1->id])
        );
    }
}
