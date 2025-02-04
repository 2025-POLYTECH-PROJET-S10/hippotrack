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
 * HippoTrack module external functions tests.
 *
 * @package    mod_hippotrack
 * @category   external
 * @copyright  2016 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.1
 */

namespace mod_hippotrack\external;

use core_question\local\bank\question_version_status;
use externallib_advanced_testcase;
use mod_hippotrack_external;
use mod_hippotrack_display_options;
use hippotrack;
use hippotrack_attempt;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * Silly class to access mod_hippotrack_external internal methods.
 *
 * @package mod_hippotrack
 * @copyright 2016 Juan Leyva <juan@moodle.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since  Moodle 3.1
 */
class testable_mod_hippotrack_external extends mod_hippotrack_external {

    /**
     * Public accessor.
     *
     * @param  array $params Array of parameters including the attemptid and preflight data
     * @param  bool $checkaccessrules whether to check the hippotrack access rules or not
     * @param  bool $failifoverdue whether to return error if the attempt is overdue
     * @return  array containing the attempt object and access messages
     */
    public static function validate_attempt($params, $checkaccessrules = true, $failifoverdue = true) {
        return parent::validate_attempt($params, $checkaccessrules, $failifoverdue);
    }

    /**
     * Public accessor.
     *
     * @param  array $params Array of parameters including the attemptid
     * @return  array containing the attempt object and display options
     */
    public static function validate_attempt_review($params) {
        return parent::validate_attempt_review($params);
    }
}

/**
 * HippoTrack module external functions tests
 *
 * @package    mod_hippotrack
 * @category   external
 * @copyright  2016 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.1
 */
class external_test extends externallib_advanced_testcase {

    /**
     * Set up for every test
     */
    public function setUp(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        // Setup test data.
        $this->course = $this->getDataGenerator()->create_course();
        $this->hippotrack = $this->getDataGenerator()->create_module('hippotrack', array('course' => $this->course->id));
        $this->context = \context_module::instance($this->hippotrack->cmid);
        $this->cm = get_coursemodule_from_instance('hippotrack', $this->hippotrack->id);

        // Create users.
        $this->student = self::getDataGenerator()->create_user();
        $this->teacher = self::getDataGenerator()->create_user();

        // Users enrolments.
        $this->studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $this->teacherrole = $DB->get_record('role', array('shortname' => 'editingteacher'));
        // Allow student to receive messages.
        $coursecontext = \context_course::instance($this->course->id);
        assign_capability('mod/hippotrack:emailnotifysubmission', CAP_ALLOW, $this->teacherrole->id, $coursecontext, true);

        $this->getDataGenerator()->enrol_user($this->student->id, $this->course->id, $this->studentrole->id, 'manual');
        $this->getDataGenerator()->enrol_user($this->teacher->id, $this->course->id, $this->teacherrole->id, 'manual');
    }

    /**
     * Create a hippotrack with questions including a started or finished attempt optionally
     *
     * @param  boolean $startattempt whether to start a new attempt
     * @param  boolean $finishattempt whether to finish the new attempt
     * @param  string $behaviour the hippotrack preferredbehaviour, defaults to 'deferredfeedback'.
     * @param  boolean $includeqattachments whether to include a question that supports attachments, defaults to false.
     * @param  array $extraoptions extra options for HippoTrack.
     * @return array array containing the hippotrack, context and the attempt
     */
    private function create_hippotrack_with_questions($startattempt = false, $finishattempt = false, $behaviour = 'deferredfeedback',
            $includeqattachments = false, $extraoptions = []) {

        // Create a new hippotrack with attempts.
        $hippotrackgenerator = $this->getDataGenerator()->get_plugin_generator('mod_hippotrack');
        $data = array('course' => $this->course->id,
                      'sumgrades' => 2,
                      'preferredbehaviour' => $behaviour);
        $data = array_merge($data, $extraoptions);
        $hippotrack = $hippotrackgenerator->create_instance($data);
        $context = \context_module::instance($hippotrack->cmid);

        // Create a couple of questions.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');

        $cat = $questiongenerator->create_question_category();
        $question = $questiongenerator->create_question('numerical', null, array('category' => $cat->id));
        hippotrack_add_hippotrack_question($question->id, $hippotrack);
        $question = $questiongenerator->create_question('numerical', null, array('category' => $cat->id));
        hippotrack_add_hippotrack_question($question->id, $hippotrack);

        if ($includeqattachments) {
            $question = $questiongenerator->create_question('essay', null, array('category' => $cat->id, 'attachments' => 1,
                'attachmentsrequired' => 1));
            hippotrack_add_hippotrack_question($question->id, $hippotrack);
        }

        $hippotrackobj = hippotrack::create($hippotrack->id, $this->student->id);

        // Set grade to pass.
        $item = \grade_item::fetch(array('courseid' => $this->course->id, 'itemtype' => 'mod',
                                        'itemmodule' => 'hippotrack', 'iteminstance' => $hippotrack->id, 'outcomeid' => null));
        $item->gradepass = 80;
        $item->update();

        if ($startattempt or $finishattempt) {
            // Now, do one attempt.
            $quba = \question_engine::make_questions_usage_by_activity('mod_hippotrack', $hippotrackobj->get_context());
            $quba->set_preferred_behaviour($hippotrackobj->get_hippotrack()->preferredbehaviour);

            $timenow = time();
            $attempt = hippotrack_create_attempt($hippotrackobj, 1, false, $timenow, false, $this->student->id);
            hippotrack_start_new_attempt($hippotrackobj, $quba, $attempt, 1, $timenow);
            hippotrack_attempt_save_started($hippotrackobj, $quba, $attempt);
            $attemptobj = hippotrack_attempt::create($attempt->id);

            if ($finishattempt) {
                // Process some responses from the student.
                $tosubmit = array(1 => array('answer' => '3.14'));
                $attemptobj->process_submitted_actions(time(), false, $tosubmit);

                // Finish the attempt.
                $attemptobj->process_finish(time(), false);
            }
            return array($hippotrack, $context, $hippotrackobj, $attempt, $attemptobj, $quba);
        } else {
            return array($hippotrack, $context, $hippotrackobj);
        }

    }

    /*
     * Test get hippotrackzes by courses
     */
    public function test_mod_hippotrack_get_hippotrackzes_by_courses() {
        global $DB;

        // Create additional course.
        $course2 = self::getDataGenerator()->create_course();

        // Second hippotrack.
        $record = new \stdClass();
        $record->course = $course2->id;
        $record->intro = '<button>Test with HTML allowed.</button>';
        $hippotrack2 = self::getDataGenerator()->create_module('hippotrack', $record);

        // Execute real Moodle enrolment as we'll call unenrol() method on the instance later.
        $enrol = enrol_get_plugin('manual');
        $enrolinstances = enrol_get_instances($course2->id, true);
        foreach ($enrolinstances as $courseenrolinstance) {
            if ($courseenrolinstance->enrol == "manual") {
                $instance2 = $courseenrolinstance;
                break;
            }
        }
        $enrol->enrol_user($instance2, $this->student->id, $this->studentrole->id);

        self::setUser($this->student);

        $returndescription = mod_hippotrack_external::get_hippotrackzes_by_courses_returns();

        // Create what we expect to be returned when querying the two courses.
        // First for the student user.
        $allusersfields = array('id', 'coursemodule', 'course', 'name', 'intro', 'introformat', 'introfiles', 'lang',
                                'timeopen', 'timeclose', 'grademethod', 'section', 'visible', 'groupmode', 'groupingid',
                                'attempts', 'timelimit', 'grademethod', 'decimalpoints', 'questiondecimalpoints', 'sumgrades',
                                'grade', 'preferredbehaviour', 'hasfeedback');
        $userswithaccessfields = array('attemptonlast', 'reviewattempt', 'reviewcorrectness', 'reviewmarks',
                                        'reviewspecificfeedback', 'reviewgeneralfeedback', 'reviewrightanswer',
                                        'reviewoverallfeedback', 'questionsperpage', 'navmethod',
                                        'browsersecurity', 'delay1', 'delay2', 'showuserpicture', 'showblocks',
                                        'completionattemptsexhausted', 'completionpass', 'autosaveperiod', 'hasquestions',
                                        'overduehandling', 'graceperiod', 'canredoquestions', 'allowofflineattempts');
        $managerfields = array('shuffleanswers', 'timecreated', 'timemodified', 'password', 'subnet');

        // Add expected coursemodule and other data.
        $hippotrack1 = $this->hippotrack;
        $hippotrack1->coursemodule = $hippotrack1->cmid;
        $hippotrack1->introformat = 1;
        $hippotrack1->section = 0;
        $hippotrack1->visible = true;
        $hippotrack1->groupmode = 0;
        $hippotrack1->groupingid = 0;
        $hippotrack1->hasquestions = 0;
        $hippotrack1->hasfeedback = 0;
        $hippotrack1->completionpass = 0;
        $hippotrack1->autosaveperiod = get_config('hippotrack', 'autosaveperiod');
        $hippotrack1->introfiles = [];
        $hippotrack1->lang = '';

        $hippotrack2->coursemodule = $hippotrack2->cmid;
        $hippotrack2->introformat = 1;
        $hippotrack2->section = 0;
        $hippotrack2->visible = true;
        $hippotrack2->groupmode = 0;
        $hippotrack2->groupingid = 0;
        $hippotrack2->hasquestions = 0;
        $hippotrack2->hasfeedback = 0;
        $hippotrack2->completionpass = 0;
        $hippotrack2->autosaveperiod = get_config('hippotrack', 'autosaveperiod');
        $hippotrack2->introfiles = [];
        $hippotrack2->lang = '';

        foreach (array_merge($allusersfields, $userswithaccessfields) as $field) {
            $expected1[$field] = $hippotrack1->{$field};
            $expected2[$field] = $hippotrack2->{$field};
        }

        $expectedhippotrackzes = array($expected2, $expected1);

        // Call the external function passing course ids.
        $result = mod_hippotrack_external::get_hippotrackzes_by_courses(array($course2->id, $this->course->id));
        $result = \external_api::clean_returnvalue($returndescription, $result);

        $this->assertEquals($expectedhippotrackzes, $result['hippotrackzes']);
        $this->assertCount(0, $result['warnings']);

        // Call the external function without passing course id.
        $result = mod_hippotrack_external::get_hippotrackzes_by_courses();
        $result = \external_api::clean_returnvalue($returndescription, $result);
        $this->assertEquals($expectedhippotrackzes, $result['hippotrackzes']);
        $this->assertCount(0, $result['warnings']);

        // Unenrol user from second course and alter expected hippotrackzes.
        $enrol->unenrol_user($instance2, $this->student->id);
        array_shift($expectedhippotrackzes);

        // Call the external function without passing course id.
        $result = mod_hippotrack_external::get_hippotrackzes_by_courses();
        $result = \external_api::clean_returnvalue($returndescription, $result);
        $this->assertEquals($expectedhippotrackzes, $result['hippotrackzes']);

        // Call for the second course we unenrolled the user from, expected warning.
        $result = mod_hippotrack_external::get_hippotrackzes_by_courses(array($course2->id));
        $this->assertCount(1, $result['warnings']);
        $this->assertEquals('1', $result['warnings'][0]['warningcode']);
        $this->assertEquals($course2->id, $result['warnings'][0]['itemid']);

        // Now, try as a teacher for getting all the additional fields.
        self::setUser($this->teacher);

        foreach ($managerfields as $field) {
            $expectedhippotrackzes[0][$field] = $hippotrack1->{$field};
        }

        $result = mod_hippotrack_external::get_hippotrackzes_by_courses();
        $result = \external_api::clean_returnvalue($returndescription, $result);
        $this->assertEquals($expectedhippotrackzes, $result['hippotrackzes']);

        // Admin also should get all the information.
        self::setAdminUser();

        $result = mod_hippotrack_external::get_hippotrackzes_by_courses(array($this->course->id));
        $result = \external_api::clean_returnvalue($returndescription, $result);
        $this->assertEquals($expectedhippotrackzes, $result['hippotrackzes']);

        // Now, prevent access.
        $enrol->enrol_user($instance2, $this->student->id);

        self::setUser($this->student);

        $hippotrack2->timeclose = time() - DAYSECS;
        $DB->update_record('hippotrack', $hippotrack2);

        $result = mod_hippotrack_external::get_hippotrackzes_by_courses();
        $result = \external_api::clean_returnvalue($returndescription, $result);
        $this->assertCount(2, $result['hippotrackzes']);
        // We only see a limited set of fields.
        $this->assertCount(5, $result['hippotrackzes'][0]);
        $this->assertEquals($hippotrack2->id, $result['hippotrackzes'][0]['id']);
        $this->assertEquals($hippotrack2->cmid, $result['hippotrackzes'][0]['coursemodule']);
        $this->assertEquals($hippotrack2->course, $result['hippotrackzes'][0]['course']);
        $this->assertEquals($hippotrack2->name, $result['hippotrackzes'][0]['name']);
        $this->assertEquals($hippotrack2->course, $result['hippotrackzes'][0]['course']);

        $this->assertFalse(isset($result['hippotrackzes'][0]['timelimit']));

    }

    /**
     * Test test_view_hippotrack
     */
    public function test_view_hippotrack() {
        global $DB;

        // Test invalid instance id.
        try {
            mod_hippotrack_external::view_hippotrack(0);
            $this->fail('Exception expected due to invalid mod_hippotrack instance id.');
        } catch (\moodle_exception $e) {
            $this->assertEquals('invalidrecord', $e->errorcode);
        }

        // Test not-enrolled user.
        $usernotenrolled = self::getDataGenerator()->create_user();
        $this->setUser($usernotenrolled);
        try {
            mod_hippotrack_external::view_hippotrack($this->hippotrack->id);
            $this->fail('Exception expected due to not enrolled user.');
        } catch (\moodle_exception $e) {
            $this->assertEquals('requireloginerror', $e->errorcode);
        }

        // Test user with full capabilities.
        $this->setUser($this->student);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();

        $result = mod_hippotrack_external::view_hippotrack($this->hippotrack->id);
        $result = \external_api::clean_returnvalue(mod_hippotrack_external::view_hippotrack_returns(), $result);
        $this->assertTrue($result['status']);

        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = array_shift($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_hippotrack\event\course_module_viewed', $event);
        $this->assertEquals($this->context, $event->get_context());
        $moodlehippotrack = new \moodle_url('/mod/hippotrack/view.php', array('id' => $this->cm->id));
        $this->assertEquals($moodlehippotrack, $event->get_url());
        $this->assertEventContextNotUsed($event);
        $this->assertNotEmpty($event->get_name());

        // Test user with no capabilities.
        // We need a explicit prohibit since this capability is only defined in authenticated user and guest roles.
        assign_capability('mod/hippotrack:view', CAP_PROHIBIT, $this->studentrole->id, $this->context->id);
        // Empty all the caches that may be affected  by this change.
        accesslib_clear_all_caches_for_unit_testing();
        \course_modinfo::clear_instance_cache();

        try {
            mod_hippotrack_external::view_hippotrack($this->hippotrack->id);
            $this->fail('Exception expected due to missing capability.');
        } catch (\moodle_exception $e) {
            $this->assertEquals('requireloginerror', $e->errorcode);
        }

    }

    /**
     * Test get_user_attempts
     */
    public function test_get_user_attempts() {

        // Create a hippotrack with one attempt finished.
        list($hippotrack, $context, $hippotrackobj, $attempt, $attemptobj) = $this->create_hippotrack_with_questions(true, true);

        $this->setUser($this->student);
        $result = mod_hippotrack_external::get_user_attempts($hippotrack->id);
        $result = \external_api::clean_returnvalue(mod_hippotrack_external::get_user_attempts_returns(), $result);

        $this->assertCount(1, $result['attempts']);
        $this->assertEquals($attempt->id, $result['attempts'][0]['id']);
        $this->assertEquals($hippotrack->id, $result['attempts'][0]['hippotrack']);
        $this->assertEquals($this->student->id, $result['attempts'][0]['userid']);
        $this->assertEquals(1, $result['attempts'][0]['attempt']);
        $this->assertArrayHasKey('sumgrades', $result['attempts'][0]);
        $this->assertEquals(1.0, $result['attempts'][0]['sumgrades']);

        // Test filters. Only finished.
        $result = mod_hippotrack_external::get_user_attempts($hippotrack->id, 0, 'finished', false);
        $result = \external_api::clean_returnvalue(mod_hippotrack_external::get_user_attempts_returns(), $result);

        $this->assertCount(1, $result['attempts']);
        $this->assertEquals($attempt->id, $result['attempts'][0]['id']);

        // Test filters. All attempts.
        $result = mod_hippotrack_external::get_user_attempts($hippotrack->id, 0, 'all', false);
        $result = \external_api::clean_returnvalue(mod_hippotrack_external::get_user_attempts_returns(), $result);

        $this->assertCount(1, $result['attempts']);
        $this->assertEquals($attempt->id, $result['attempts'][0]['id']);

        // Test filters. Unfinished.
        $result = mod_hippotrack_external::get_user_attempts($hippotrack->id, 0, 'unfinished', false);
        $result = \external_api::clean_returnvalue(mod_hippotrack_external::get_user_attempts_returns(), $result);

        $this->assertCount(0, $result['attempts']);

        // Start a new attempt, but not finish it.
        $timenow = time();
        $attempt = hippotrack_create_attempt($hippotrackobj, 2, false, $timenow, false, $this->student->id);
        $quba = \question_engine::make_questions_usage_by_activity('mod_hippotrack', $hippotrackobj->get_context());
        $quba->set_preferred_behaviour($hippotrackobj->get_hippotrack()->preferredbehaviour);

        hippotrack_start_new_attempt($hippotrackobj, $quba, $attempt, 1, $timenow);
        hippotrack_attempt_save_started($hippotrackobj, $quba, $attempt);

        // Test filters. All attempts.
        $result = mod_hippotrack_external::get_user_attempts($hippotrack->id, 0, 'all', false);
        $result = \external_api::clean_returnvalue(mod_hippotrack_external::get_user_attempts_returns(), $result);

        $this->assertCount(2, $result['attempts']);

        // Test filters. Unfinished.
        $result = mod_hippotrack_external::get_user_attempts($hippotrack->id, 0, 'unfinished', false);
        $result = \external_api::clean_returnvalue(mod_hippotrack_external::get_user_attempts_returns(), $result);

        $this->assertCount(1, $result['attempts']);

        // Test manager can see user attempts.
        $this->setUser($this->teacher);
        $result = mod_hippotrack_external::get_user_attempts($hippotrack->id, $this->student->id);
        $result = \external_api::clean_returnvalue(mod_hippotrack_external::get_user_attempts_returns(), $result);

        $this->assertCount(1, $result['attempts']);
        $this->assertEquals($this->student->id, $result['attempts'][0]['userid']);

        $result = mod_hippotrack_external::get_user_attempts($hippotrack->id, $this->student->id, 'all');
        $result = \external_api::clean_returnvalue(mod_hippotrack_external::get_user_attempts_returns(), $result);

        $this->assertCount(2, $result['attempts']);
        $this->assertEquals($this->student->id, $result['attempts'][0]['userid']);

        // Invalid parameters.
        try {
            mod_hippotrack_external::get_user_attempts($hippotrack->id, $this->student->id, 'INVALID_PARAMETER');
            $this->fail('Exception expected due to missing capability.');
        } catch (\invalid_parameter_exception $e) {
            $this->assertEquals('invalidparameter', $e->errorcode);
        }
    }

    /**
     * Test get_user_attempts with marks hidden
     */
    public function test_get_user_attempts_with_marks_hidden() {
        // Create hippotrack with one attempt finished and hide the mark.
        list($hippotrack, $context, $hippotrackobj, $attempt, $attemptobj) = $this->create_hippotrack_with_questions(
                true, true, 'deferredfeedback', false,
                ['marksduring' => 0, 'marksimmediately' => 0, 'marksopen' => 0, 'marksclosed' => 0]);

        // Student cannot see the grades.
        $this->setUser($this->student);
        $result = mod_hippotrack_external::get_user_attempts($hippotrack->id);
        $result = \external_api::clean_returnvalue(mod_hippotrack_external::get_user_attempts_returns(), $result);

        $this->assertCount(1, $result['attempts']);
        $this->assertEquals($attempt->id, $result['attempts'][0]['id']);
        $this->assertEquals($hippotrack->id, $result['attempts'][0]['hippotrack']);
        $this->assertEquals($this->student->id, $result['attempts'][0]['userid']);
        $this->assertEquals(1, $result['attempts'][0]['attempt']);
        $this->assertArrayHasKey('sumgrades', $result['attempts'][0]);
        $this->assertEquals(null, $result['attempts'][0]['sumgrades']);

        // Test manager can see user grades.
        $this->setUser($this->teacher);
        $result = mod_hippotrack_external::get_user_attempts($hippotrack->id, $this->student->id);
        $result = \external_api::clean_returnvalue(mod_hippotrack_external::get_user_attempts_returns(), $result);

        $this->assertCount(1, $result['attempts']);
        $this->assertEquals($attempt->id, $result['attempts'][0]['id']);
        $this->assertEquals($hippotrack->id, $result['attempts'][0]['hippotrack']);
        $this->assertEquals($this->student->id, $result['attempts'][0]['userid']);
        $this->assertEquals(1, $result['attempts'][0]['attempt']);
        $this->assertArrayHasKey('sumgrades', $result['attempts'][0]);
        $this->assertEquals(1.0, $result['attempts'][0]['sumgrades']);
    }

    /**
     * Test get_user_best_grade
     */
    public function test_get_user_best_grade() {
        $hippotrackgenerator = $this->getDataGenerator()->get_plugin_generator('mod_hippotrack');
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $questioncat = $questiongenerator->create_question_category();

        // Create a new hippotrack.
        $hippotrackapi1 = $hippotrackgenerator->create_instance([
                'name' => 'Test HippoTrack API 1',
                'course' => $this->course->id,
                'sumgrades' => 1
        ]);
        $hippotrackapi2 = $hippotrackgenerator->create_instance([
                'name' => 'Test HippoTrack API 2',
                'course' => $this->course->id,
                'sumgrades' => 1,
                'marksduring' => 0,
                'marksimmediately' => 0,
                'marksopen' => 0,
                'marksclosed' => 0
        ]);

        // Create a question.
        $question = $questiongenerator->create_question('numerical', null, ['category' => $questioncat->id]);

        // Add question to the hippotrackzes.
        hippotrack_add_hippotrack_question($question->id, $hippotrackapi1);
        hippotrack_add_hippotrack_question($question->id, $hippotrackapi2);

        // Create hippotrack object.
        $hippotrackapiobj1 = hippotrack::create($hippotrackapi1->id, $this->student->id);
        $hippotrackapiobj2 = hippotrack::create($hippotrackapi2->id, $this->student->id);

        // Set grade to pass.
        $item = \grade_item::fetch([
                'courseid' => $this->course->id,
                'itemtype' => 'mod',
                'itemmodule' => 'hippotrack',
                'iteminstance' => $hippotrackapi1->id,
                'outcomeid' => null
        ]);
        $item->gradepass = 80;
        $item->update();

        $item = \grade_item::fetch([
                'courseid' => $this->course->id,
                'itemtype' => 'mod',
                'itemmodule' => 'hippotrack',
                'iteminstance' => $hippotrackapi2->id,
                'outcomeid' => null
        ]);
        $item->gradepass = 80;
        $item->update();

        // Start the passing attempt.
        $quba1 = \question_engine::make_questions_usage_by_activity('mod_hippotrack', $hippotrackapiobj1->get_context());
        $quba1->set_preferred_behaviour($hippotrackapiobj1->get_hippotrack()->preferredbehaviour);

        $quba2 = \question_engine::make_questions_usage_by_activity('mod_hippotrack', $hippotrackapiobj2->get_context());
        $quba2->set_preferred_behaviour($hippotrackapiobj2->get_hippotrack()->preferredbehaviour);

        // Start the testing for hippotrackapi1 that allow the student to view the grade.

        $this->setUser($this->student);
        $result = mod_hippotrack_external::get_user_best_grade($hippotrackapi1->id);
        $result = \external_api::clean_returnvalue(mod_hippotrack_external::get_user_best_grade_returns(), $result);

        // No grades yet.
        $this->assertFalse($result['hasgrade']);
        $this->assertTrue(!isset($result['grade']));

        // Start the attempt.
        $timenow = time();
        $attempt = hippotrack_create_attempt($hippotrackapiobj1, 1, false, $timenow, false, $this->student->id);
        hippotrack_start_new_attempt($hippotrackapiobj1, $quba1, $attempt, 1, $timenow);
        hippotrack_attempt_save_started($hippotrackapiobj1, $quba1, $attempt);

        // Process some responses from the student.
        $attemptobj = hippotrack_attempt::create($attempt->id);
        $attemptobj->process_submitted_actions($timenow, false, [1 => ['answer' => '3.14']]);

        // Finish the attempt.
        $attemptobj->process_finish($timenow, false);

        $result = mod_hippotrack_external::get_user_best_grade($hippotrackapi1->id);
        $result = \external_api::clean_returnvalue(mod_hippotrack_external::get_user_best_grade_returns(), $result);

        // Now I have grades.
        $this->assertTrue($result['hasgrade']);
        $this->assertEquals(100.0, $result['grade']);
        $this->assertEquals(80, $result['gradetopass']);

        // We should not see other users grades.
        $anotherstudent = self::getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($anotherstudent->id, $this->course->id, $this->studentrole->id, 'manual');

        try {
            mod_hippotrack_external::get_user_best_grade($hippotrackapi1->id, $anotherstudent->id);
            $this->fail('Exception expected due to missing capability.');
        } catch (\required_capability_exception $e) {
            $this->assertEquals('nopermissions', $e->errorcode);
        }

        // Teacher must be able to see student grades.
        $this->setUser($this->teacher);

        $result = mod_hippotrack_external::get_user_best_grade($hippotrackapi1->id, $this->student->id);
        $result = \external_api::clean_returnvalue(mod_hippotrack_external::get_user_best_grade_returns(), $result);

        $this->assertTrue($result['hasgrade']);
        $this->assertEquals(100.0, $result['grade']);
        $this->assertEquals(80, $result['gradetopass']);

        // Invalid user.
        try {
            mod_hippotrack_external::get_user_best_grade($this->hippotrack->id, -1);
            $this->fail('Exception expected due to missing capability.');
        } catch (\dml_missing_record_exception $e) {
            $this->assertEquals('invaliduser', $e->errorcode);
        }

        // End the testing for hippotrackapi1 that allow the student to view the grade.

        // Start the testing for hippotrackapi2 that do not allow the student to view the grade.

        $this->setUser($this->student);
        $result = mod_hippotrack_external::get_user_best_grade($hippotrackapi2->id);
        $result = \external_api::clean_returnvalue(mod_hippotrack_external::get_user_best_grade_returns(), $result);

        // No grades yet.
        $this->assertFalse($result['hasgrade']);
        $this->assertTrue(!isset($result['grade']));

        // Start the attempt.
        $timenow = time();
        $attempt = hippotrack_create_attempt($hippotrackapiobj2, 1, false, $timenow, false, $this->student->id);
        hippotrack_start_new_attempt($hippotrackapiobj2, $quba2, $attempt, 1, $timenow);
        hippotrack_attempt_save_started($hippotrackapiobj2, $quba2, $attempt);

        // Process some responses from the student.
        $attemptobj = hippotrack_attempt::create($attempt->id);
        $attemptobj->process_submitted_actions($timenow, false, [1 => ['answer' => '3.14']]);

        // Finish the attempt.
        $attemptobj->process_finish($timenow, false);

        $result = mod_hippotrack_external::get_user_best_grade($hippotrackapi2->id);
        $result = \external_api::clean_returnvalue(mod_hippotrack_external::get_user_best_grade_returns(), $result);

        // Now I have grades but I will not be allowed to see it.
        $this->assertFalse($result['hasgrade']);
        $this->assertTrue(!isset($result['grade']));

        // Teacher must be able to see student grades.
        $this->setUser($this->teacher);

        $result = mod_hippotrack_external::get_user_best_grade($hippotrackapi2->id, $this->student->id);
        $result = \external_api::clean_returnvalue(mod_hippotrack_external::get_user_best_grade_returns(), $result);

        $this->assertTrue($result['hasgrade']);
        $this->assertEquals(100.0, $result['grade']);

        // End the testing for hippotrackapi2 that do not allow the student to view the grade.

    }
    /**
     * Test get_combined_review_options.
     * This is a basic test, this is already tested in mod_hippotrack_display_options_testcase.
     */
    public function test_get_combined_review_options() {
        global $DB;

        // Create a new hippotrack with attempts.
        $hippotrackgenerator = $this->getDataGenerator()->get_plugin_generator('mod_hippotrack');
        $data = array('course' => $this->course->id,
                      'sumgrades' => 1);
        $hippotrack = $hippotrackgenerator->create_instance($data);

        // Create a couple of questions.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');

        $cat = $questiongenerator->create_question_category();
        $question = $questiongenerator->create_question('numerical', null, array('category' => $cat->id));
        hippotrack_add_hippotrack_question($question->id, $hippotrack);

        $hippotrackobj = hippotrack::create($hippotrack->id, $this->student->id);

        // Set grade to pass.
        $item = \grade_item::fetch(array('courseid' => $this->course->id, 'itemtype' => 'mod',
                                        'itemmodule' => 'hippotrack', 'iteminstance' => $hippotrack->id, 'outcomeid' => null));
        $item->gradepass = 80;
        $item->update();

        // Start the passing attempt.
        $quba = \question_engine::make_questions_usage_by_activity('mod_hippotrack', $hippotrackobj->get_context());
        $quba->set_preferred_behaviour($hippotrackobj->get_hippotrack()->preferredbehaviour);

        $timenow = time();
        $attempt = hippotrack_create_attempt($hippotrackobj, 1, false, $timenow, false, $this->student->id);
        hippotrack_start_new_attempt($hippotrackobj, $quba, $attempt, 1, $timenow);
        hippotrack_attempt_save_started($hippotrackobj, $quba, $attempt);

        $this->setUser($this->student);

        $result = mod_hippotrack_external::get_combined_review_options($hippotrack->id);
        $result = \external_api::clean_returnvalue(mod_hippotrack_external::get_combined_review_options_returns(), $result);

        // Expected values.
        $expected = array(
            "someoptions" => array(
                array("name" => "feedback", "value" => 1),
                array("name" => "generalfeedback", "value" => 1),
                array("name" => "rightanswer", "value" => 1),
                array("name" => "overallfeedback", "value" => 0),
                array("name" => "marks", "value" => 2),
            ),
            "alloptions" => array(
                array("name" => "feedback", "value" => 1),
                array("name" => "generalfeedback", "value" => 1),
                array("name" => "rightanswer", "value" => 1),
                array("name" => "overallfeedback", "value" => 0),
                array("name" => "marks", "value" => 2),
            ),
            "warnings" => [],
        );

        $this->assertEquals($expected, $result);

        // Now, finish the attempt.
        $attemptobj = hippotrack_attempt::create($attempt->id);
        $attemptobj->process_finish($timenow, false);

        $expected = array(
            "someoptions" => array(
                array("name" => "feedback", "value" => 1),
                array("name" => "generalfeedback", "value" => 1),
                array("name" => "rightanswer", "value" => 1),
                array("name" => "overallfeedback", "value" => 1),
                array("name" => "marks", "value" => 2),
            ),
            "alloptions" => array(
                array("name" => "feedback", "value" => 1),
                array("name" => "generalfeedback", "value" => 1),
                array("name" => "rightanswer", "value" => 1),
                array("name" => "overallfeedback", "value" => 1),
                array("name" => "marks", "value" => 2),
            ),
            "warnings" => [],
        );

        // We should see now the overall feedback.
        $result = mod_hippotrack_external::get_combined_review_options($hippotrack->id);
        $result = \external_api::clean_returnvalue(mod_hippotrack_external::get_combined_review_options_returns(), $result);
        $this->assertEquals($expected, $result);

        // Start a new attempt, but not finish it.
        $timenow = time();
        $attempt = hippotrack_create_attempt($hippotrackobj, 2, false, $timenow, false, $this->student->id);
        $quba = \question_engine::make_questions_usage_by_activity('mod_hippotrack', $hippotrackobj->get_context());
        $quba->set_preferred_behaviour($hippotrackobj->get_hippotrack()->preferredbehaviour);
        hippotrack_start_new_attempt($hippotrackobj, $quba, $attempt, 1, $timenow);
        hippotrack_attempt_save_started($hippotrackobj, $quba, $attempt);

        $expected = array(
            "someoptions" => array(
                array("name" => "feedback", "value" => 1),
                array("name" => "generalfeedback", "value" => 1),
                array("name" => "rightanswer", "value" => 1),
                array("name" => "overallfeedback", "value" => 1),
                array("name" => "marks", "value" => 2),
            ),
            "alloptions" => array(
                array("name" => "feedback", "value" => 1),
                array("name" => "generalfeedback", "value" => 1),
                array("name" => "rightanswer", "value" => 1),
                array("name" => "overallfeedback", "value" => 0),
                array("name" => "marks", "value" => 2),
            ),
            "warnings" => [],
        );

        $result = mod_hippotrack_external::get_combined_review_options($hippotrack->id);
        $result = \external_api::clean_returnvalue(mod_hippotrack_external::get_combined_review_options_returns(), $result);
        $this->assertEquals($expected, $result);

        // Teacher, for see student options.
        $this->setUser($this->teacher);

        $result = mod_hippotrack_external::get_combined_review_options($hippotrack->id, $this->student->id);
        $result = \external_api::clean_returnvalue(mod_hippotrack_external::get_combined_review_options_returns(), $result);

        $this->assertEquals($expected, $result);

        // Invalid user.
        try {
            mod_hippotrack_external::get_combined_review_options($hippotrack->id, -1);
            $this->fail('Exception expected due to missing capability.');
        } catch (\dml_missing_record_exception $e) {
            $this->assertEquals('invaliduser', $e->errorcode);
        }
    }

    /**
     * Test start_attempt
     */
    public function test_start_attempt() {
        global $DB;

        // Create a new hippotrack with questions.
        list($hippotrack, $context, $hippotrackobj) = $this->create_hippotrack_with_questions();

        $this->setUser($this->student);

        // Try to open attempt in closed hippotrack.
        $hippotrack->timeopen = time() - WEEKSECS;
        $hippotrack->timeclose = time() - DAYSECS;
        $DB->update_record('hippotrack', $hippotrack);
        $result = mod_hippotrack_external::start_attempt($hippotrack->id);
        $result = \external_api::clean_returnvalue(mod_hippotrack_external::start_attempt_returns(), $result);

        $this->assertEquals([], $result['attempt']);
        $this->assertCount(1, $result['warnings']);

        // Now with a password.
        $hippotrack->timeopen = 0;
        $hippotrack->timeclose = 0;
        $hippotrack->password = 'abc';
        $DB->update_record('hippotrack', $hippotrack);

        try {
            mod_hippotrack_external::start_attempt($hippotrack->id, array(array("name" => "hippotrackpassword", "value" => 'bad')));
            $this->fail('Exception expected due to invalid passwod.');
        } catch (\moodle_exception $e) {
            $this->assertEquals(get_string('passworderror', 'hippotrackaccess_password'), $e->errorcode);
        }

        // Now, try everything correct.
        $result = mod_hippotrack_external::start_attempt($hippotrack->id, array(array("name" => "hippotrackpassword", "value" => 'abc')));
        $result = \external_api::clean_returnvalue(mod_hippotrack_external::start_attempt_returns(), $result);

        $this->assertEquals(1, $result['attempt']['attempt']);
        $this->assertEquals($this->student->id, $result['attempt']['userid']);
        $this->assertEquals($hippotrack->id, $result['attempt']['hippotrack']);
        $this->assertCount(0, $result['warnings']);
        $attemptid = $result['attempt']['id'];

        // We are good, try to start a new attempt now.

        try {
            mod_hippotrack_external::start_attempt($hippotrack->id, array(array("name" => "hippotrackpassword", "value" => 'abc')));
            $this->fail('Exception expected due to attempt not finished.');
        } catch (\moodle_hippotrack_exception $e) {
            $this->assertEquals('attemptstillinprogress', $e->errorcode);
        }

        // Finish the started attempt.

        // Process some responses from the student.
        $timenow = time();
        $attemptobj = hippotrack_attempt::create($attemptid);
        $tosubmit = array(1 => array('answer' => '3.14'));
        $attemptobj->process_submitted_actions($timenow, false, $tosubmit);

        // Finish the attempt.
        $attemptobj = hippotrack_attempt::create($attemptid);
        $this->assertTrue($attemptobj->has_response_to_at_least_one_graded_question());
        $attemptobj->process_finish($timenow, false);

        // We should be able to start a new attempt.
        $result = mod_hippotrack_external::start_attempt($hippotrack->id, array(array("name" => "hippotrackpassword", "value" => 'abc')));
        $result = \external_api::clean_returnvalue(mod_hippotrack_external::start_attempt_returns(), $result);

        $this->assertEquals(2, $result['attempt']['attempt']);
        $this->assertEquals($this->student->id, $result['attempt']['userid']);
        $this->assertEquals($hippotrack->id, $result['attempt']['hippotrack']);
        $this->assertCount(0, $result['warnings']);

        // Test user with no capabilities.
        // We need a explicit prohibit since this capability is only defined in authenticated user and guest roles.
        assign_capability('mod/hippotrack:attempt', CAP_PROHIBIT, $this->studentrole->id, $context->id);
        // Empty all the caches that may be affected  by this change.
        accesslib_clear_all_caches_for_unit_testing();
        \course_modinfo::clear_instance_cache();

        try {
            mod_hippotrack_external::start_attempt($hippotrack->id);
            $this->fail('Exception expected due to missing capability.');
        } catch (\required_capability_exception $e) {
            $this->assertEquals('nopermissions', $e->errorcode);
        }

    }

    /**
     * Test validate_attempt
     */
    public function test_validate_attempt() {
        global $DB;

        // Create a new hippotrack with one attempt started.
        list($hippotrack, $context, $hippotrackobj, $attempt, $attemptobj) = $this->create_hippotrack_with_questions(true);

        $this->setUser($this->student);

        // Invalid attempt.
        try {
            $params = array('attemptid' => -1, 'page' => 0);
            testable_mod_hippotrack_external::validate_attempt($params);
            $this->fail('Exception expected due to invalid attempt id.');
        } catch (\dml_missing_record_exception $e) {
            $this->assertEquals('invalidrecord', $e->errorcode);
        }

        // Test OK case.
        $params = array('attemptid' => $attempt->id, 'page' => 0);
        $result = testable_mod_hippotrack_external::validate_attempt($params);
        $this->assertEquals($attempt->id, $result[0]->get_attempt()->id);
        $this->assertEquals([], $result[1]);

        // Test with preflight data.
        $hippotrack->password = 'abc';
        $DB->update_record('hippotrack', $hippotrack);

        try {
            $params = array('attemptid' => $attempt->id, 'page' => 0,
                            'preflightdata' => array(array("name" => "hippotrackpassword", "value" => 'bad')));
            testable_mod_hippotrack_external::validate_attempt($params);
            $this->fail('Exception expected due to invalid passwod.');
        } catch (\moodle_exception $e) {
            $this->assertEquals(get_string('passworderror', 'hippotrackaccess_password'), $e->errorcode);
        }

        // Now, try everything correct.
        $params['preflightdata'][0]['value'] = 'abc';
        $result = testable_mod_hippotrack_external::validate_attempt($params);
        $this->assertEquals($attempt->id, $result[0]->get_attempt()->id);
        $this->assertEquals([], $result[1]);

        // Page out of range.
        $DB->update_record('hippotrack', $hippotrack);
        $params['page'] = 4;
        try {
            testable_mod_hippotrack_external::validate_attempt($params);
            $this->fail('Exception expected due to page out of range.');
        } catch (\moodle_hippotrack_exception $e) {
            $this->assertEquals('Invalid page number', $e->errorcode);
        }

        $params['page'] = 0;
        // Try to open attempt in closed hippotrack.
        $hippotrack->timeopen = time() - WEEKSECS;
        $hippotrack->timeclose = time() - DAYSECS;
        $DB->update_record('hippotrack', $hippotrack);

        // This should work, ommit access rules.
        testable_mod_hippotrack_external::validate_attempt($params, false);

        // Get a generic error because prior to checking the dates the attempt is closed.
        try {
            testable_mod_hippotrack_external::validate_attempt($params);
            $this->fail('Exception expected due to passed dates.');
        } catch (\moodle_hippotrack_exception $e) {
            $this->assertEquals('attempterror', $e->errorcode);
        }

        // Finish the attempt.
        $attemptobj = hippotrack_attempt::create($attempt->id);
        $attemptobj->process_finish(time(), false);

        try {
            testable_mod_hippotrack_external::validate_attempt($params, false);
            $this->fail('Exception expected due to attempt finished.');
        } catch (\moodle_hippotrack_exception $e) {
            $this->assertEquals('attemptalreadyclosed', $e->errorcode);
        }

        // Test user with no capabilities.
        // We need a explicit prohibit since this capability is only defined in authenticated user and guest roles.
        assign_capability('mod/hippotrack:attempt', CAP_PROHIBIT, $this->studentrole->id, $context->id);
        // Empty all the caches that may be affected  by this change.
        accesslib_clear_all_caches_for_unit_testing();
        \course_modinfo::clear_instance_cache();

        try {
            testable_mod_hippotrack_external::validate_attempt($params);
            $this->fail('Exception expected due to missing permissions.');
        } catch (\required_capability_exception $e) {
            $this->assertEquals('nopermissions', $e->errorcode);
        }

        // Now try with a different user.
        $this->setUser($this->teacher);

        $params['page'] = 0;
        try {
            testable_mod_hippotrack_external::validate_attempt($params);
            $this->fail('Exception expected due to not your attempt.');
        } catch (\moodle_hippotrack_exception $e) {
            $this->assertEquals('notyourattempt', $e->errorcode);
        }
    }

    /**
     * Test get_attempt_data
     */
    public function test_get_attempt_data() {
        global $DB;

        $timenow = time();
        // Create a new hippotrack with one attempt started.
        list($hippotrack, $context, $hippotrackobj, $attempt, $attemptobj) = $this->create_hippotrack_with_questions(true);

        // Set correctness mask so questions state can be fetched only after finishing the attempt.
        $DB->set_field('hippotrack', 'reviewcorrectness', mod_hippotrack_display_options::IMMEDIATELY_AFTER, array('id' => $hippotrack->id));

        $hippotrackobj = $attemptobj->get_hippotrackobj();
        $hippotrackobj->preload_questions();
        $hippotrackobj->load_questions();
        $questions = $hippotrackobj->get_questions();

        $this->setUser($this->student);

        // We receive one question per page.
        $result = mod_hippotrack_external::get_attempt_data($attempt->id, 0);
        $result = \external_api::clean_returnvalue(mod_hippotrack_external::get_attempt_data_returns(), $result);

        $this->assertEquals($attempt, (object) $result['attempt']);
        $this->assertEquals(1, $result['nextpage']);
        $this->assertCount(0, $result['messages']);
        $this->assertCount(1, $result['questions']);
        $this->assertEquals(1, $result['questions'][0]['slot']);
        $this->assertEquals(1, $result['questions'][0]['number']);
        $this->assertEquals('numerical', $result['questions'][0]['type']);
        $this->assertArrayNotHasKey('state', $result['questions'][0]);  // We don't receive the state yet.
        $this->assertEquals(get_string('notyetanswered', 'question'), $result['questions'][0]['status']);
        $this->assertFalse($result['questions'][0]['flagged']);
        $this->assertEquals(0, $result['questions'][0]['page']);
        $this->assertEmpty($result['questions'][0]['mark']);
        $this->assertEquals(1, $result['questions'][0]['maxmark']);
        $this->assertEquals(1, $result['questions'][0]['sequencecheck']);
        $this->assertGreaterThanOrEqual($timenow, $result['questions'][0]['lastactiontime']);
        $this->assertEquals(false, $result['questions'][0]['hasautosavedstep']);

        // Now try the last page.
        $result = mod_hippotrack_external::get_attempt_data($attempt->id, 1);
        $result = \external_api::clean_returnvalue(mod_hippotrack_external::get_attempt_data_returns(), $result);

        $this->assertEquals($attempt, (object) $result['attempt']);
        $this->assertEquals(-1, $result['nextpage']);
        $this->assertCount(0, $result['messages']);
        $this->assertCount(1, $result['questions']);
        $this->assertEquals(2, $result['questions'][0]['slot']);
        $this->assertEquals(2, $result['questions'][0]['number']);
        $this->assertEquals('numerical', $result['questions'][0]['type']);
        $this->assertArrayNotHasKey('state', $result['questions'][0]);  // We don't receive the state yet.
        $this->assertEquals(get_string('notyetanswered', 'question'), $result['questions'][0]['status']);
        $this->assertFalse($result['questions'][0]['flagged']);
        $this->assertEquals(1, $result['questions'][0]['page']);
        $this->assertEquals(1, $result['questions'][0]['sequencecheck']);
        $this->assertGreaterThanOrEqual($timenow, $result['questions'][0]['lastactiontime']);
        $this->assertEquals(false, $result['questions'][0]['hasautosavedstep']);

        // Finish previous attempt.
        $attemptobj->process_finish(time(), false);

        // Now we should receive the question state.
        $result = mod_hippotrack_external::get_attempt_review($attempt->id, 1);
        $result = \external_api::clean_returnvalue(mod_hippotrack_external::get_attempt_review_returns(), $result);
        $this->assertEquals('gaveup', $result['questions'][0]['state']);

        // Change setting and expect two pages.
        $hippotrack->questionsperpage = 4;
        $DB->update_record('hippotrack', $hippotrack);
        hippotrack_repaginate_questions($hippotrack->id, $hippotrack->questionsperpage);

        // Start with new attempt with the new layout.
        $quba = \question_engine::make_questions_usage_by_activity('mod_hippotrack', $hippotrackobj->get_context());
        $quba->set_preferred_behaviour($hippotrackobj->get_hippotrack()->preferredbehaviour);

        $timenow = time();
        $attempt = hippotrack_create_attempt($hippotrackobj, 2, false, $timenow, false, $this->student->id);
        hippotrack_start_new_attempt($hippotrackobj, $quba, $attempt, 1, $timenow);
        hippotrack_attempt_save_started($hippotrackobj, $quba, $attempt);

        // We receive two questions per page.
        $result = mod_hippotrack_external::get_attempt_data($attempt->id, 0);
        $result = \external_api::clean_returnvalue(mod_hippotrack_external::get_attempt_data_returns(), $result);
        $this->assertCount(2, $result['questions']);
        $this->assertEquals(-1, $result['nextpage']);

        // Check questions looks good.
        $found = 0;
        foreach ($questions as $question) {
            foreach ($result['questions'] as $rquestion) {
                if ($rquestion['slot'] == $question->slot) {
                    $this->assertTrue(strpos($rquestion['html'], "qid=$question->id") !== false);
                    $found++;
                }
            }
        }
        $this->assertEquals(2, $found);

    }

    /**
     * Test get_attempt_data with blocked questions.
     * @since 3.2
     */
    public function test_get_attempt_data_with_blocked_questions() {
        global $DB;

        // Create a new hippotrack with one attempt started and using immediatefeedback.
        list($hippotrack, $context, $hippotrackobj, $attempt, $attemptobj) = $this->create_hippotrack_with_questions(
                true, false, 'immediatefeedback');

        $hippotrackobj = $attemptobj->get_hippotrackobj();

        // Make second question blocked by the first one.
        $structure = $hippotrackobj->get_structure();
        $slots = $structure->get_slots();
        $structure->update_question_dependency(end($slots)->id, true);

        $hippotrackobj->preload_questions();
        $hippotrackobj->load_questions();
        $questions = $hippotrackobj->get_questions();

        $this->setUser($this->student);

        // We receive one question per page.
        $result = mod_hippotrack_external::get_attempt_data($attempt->id, 0);
        $result = \external_api::clean_returnvalue(mod_hippotrack_external::get_attempt_data_returns(), $result);

        $this->assertEquals($attempt, (object) $result['attempt']);
        $this->assertCount(1, $result['questions']);
        $this->assertEquals(1, $result['questions'][0]['slot']);
        $this->assertEquals(1, $result['questions'][0]['number']);
        $this->assertEquals(false, $result['questions'][0]['blockedbyprevious']);

        // Now try the last page.
        $result = mod_hippotrack_external::get_attempt_data($attempt->id, 1);
        $result = \external_api::clean_returnvalue(mod_hippotrack_external::get_attempt_data_returns(), $result);

        $this->assertEquals($attempt, (object) $result['attempt']);
        $this->assertCount(1, $result['questions']);
        $this->assertEquals(2, $result['questions'][0]['slot']);
        $this->assertEquals(2, $result['questions'][0]['number']);
        $this->assertEquals(true, $result['questions'][0]['blockedbyprevious']);
    }

    /**
     * Test get_attempt_summary
     */
    public function test_get_attempt_summary() {

        $timenow = time();
        // Create a new hippotrack with one attempt started.
        list($hippotrack, $context, $hippotrackobj, $attempt, $attemptobj) = $this->create_hippotrack_with_questions(true);

        $this->setUser($this->student);
        $result = mod_hippotrack_external::get_attempt_summary($attempt->id);
        $result = \external_api::clean_returnvalue(mod_hippotrack_external::get_attempt_summary_returns(), $result);

        // Check the state, flagged and mark data is correct.
        $this->assertEquals('todo', $result['questions'][0]['state']);
        $this->assertEquals('todo', $result['questions'][1]['state']);
        $this->assertEquals(1, $result['questions'][0]['number']);
        $this->assertEquals(2, $result['questions'][1]['number']);
        $this->assertFalse($result['questions'][0]['flagged']);
        $this->assertFalse($result['questions'][1]['flagged']);
        $this->assertEmpty($result['questions'][0]['mark']);
        $this->assertEmpty($result['questions'][1]['mark']);
        $this->assertEquals(1, $result['questions'][0]['sequencecheck']);
        $this->assertEquals(1, $result['questions'][1]['sequencecheck']);
        $this->assertGreaterThanOrEqual($timenow, $result['questions'][0]['lastactiontime']);
        $this->assertGreaterThanOrEqual($timenow, $result['questions'][1]['lastactiontime']);
        $this->assertEquals(false, $result['questions'][0]['hasautosavedstep']);
        $this->assertEquals(false, $result['questions'][1]['hasautosavedstep']);

        // Check question options.
        $this->assertNotEmpty(5, $result['questions'][0]['settings']);
        // Check at least some settings returned.
        $this->assertCount(4, (array) json_decode($result['questions'][0]['settings']));

        // Submit a response for the first question.
        $tosubmit = array(1 => array('answer' => '3.14'));
        $attemptobj->process_submitted_actions(time(), false, $tosubmit);
        $result = mod_hippotrack_external::get_attempt_summary($attempt->id);
        $result = \external_api::clean_returnvalue(mod_hippotrack_external::get_attempt_summary_returns(), $result);

        // Check it's marked as completed only the first one.
        $this->assertEquals('complete', $result['questions'][0]['state']);
        $this->assertEquals('todo', $result['questions'][1]['state']);
        $this->assertEquals(1, $result['questions'][0]['number']);
        $this->assertEquals(2, $result['questions'][1]['number']);
        $this->assertFalse($result['questions'][0]['flagged']);
        $this->assertFalse($result['questions'][1]['flagged']);
        $this->assertEmpty($result['questions'][0]['mark']);
        $this->assertEmpty($result['questions'][1]['mark']);
        $this->assertEquals(2, $result['questions'][0]['sequencecheck']);
        $this->assertEquals(1, $result['questions'][1]['sequencecheck']);
        $this->assertGreaterThanOrEqual($timenow, $result['questions'][0]['lastactiontime']);
        $this->assertGreaterThanOrEqual($timenow, $result['questions'][1]['lastactiontime']);
        $this->assertEquals(false, $result['questions'][0]['hasautosavedstep']);
        $this->assertEquals(false, $result['questions'][1]['hasautosavedstep']);

    }

    /**
     * Test save_attempt
     */
    public function test_save_attempt() {

        $timenow = time();
        // Create a new hippotrack with one attempt started.
        list($hippotrack, $context, $hippotrackobj, $attempt, $attemptobj, $quba) = $this->create_hippotrack_with_questions(true);

        // Response for slot 1.
        $prefix = $quba->get_field_prefix(1);
        $data = array(
            array('name' => 'slots', 'value' => 1),
            array('name' => $prefix . ':sequencecheck',
                    'value' => $attemptobj->get_question_attempt(1)->get_sequence_check_count()),
            array('name' => $prefix . 'answer', 'value' => 1),
        );

        $this->setUser($this->student);

        $result = mod_hippotrack_external::save_attempt($attempt->id, $data);
        $result = \external_api::clean_returnvalue(mod_hippotrack_external::save_attempt_returns(), $result);
        $this->assertTrue($result['status']);

        // Now, get the summary.
        $result = mod_hippotrack_external::get_attempt_summary($attempt->id);
        $result = \external_api::clean_returnvalue(mod_hippotrack_external::get_attempt_summary_returns(), $result);

        // Check it's marked as completed only the first one.
        $this->assertEquals('complete', $result['questions'][0]['state']);
        $this->assertEquals('todo', $result['questions'][1]['state']);
        $this->assertEquals(1, $result['questions'][0]['number']);
        $this->assertEquals(2, $result['questions'][1]['number']);
        $this->assertFalse($result['questions'][0]['flagged']);
        $this->assertFalse($result['questions'][1]['flagged']);
        $this->assertEmpty($result['questions'][0]['mark']);
        $this->assertEmpty($result['questions'][1]['mark']);
        $this->assertEquals(1, $result['questions'][0]['sequencecheck']);
        $this->assertEquals(1, $result['questions'][1]['sequencecheck']);
        $this->assertGreaterThanOrEqual($timenow, $result['questions'][0]['lastactiontime']);
        $this->assertGreaterThanOrEqual($timenow, $result['questions'][1]['lastactiontime']);
        $this->assertEquals(true, $result['questions'][0]['hasautosavedstep']);
        $this->assertEquals(false, $result['questions'][1]['hasautosavedstep']);

        // Now, second slot.
        $prefix = $quba->get_field_prefix(2);
        $data = array(
            array('name' => 'slots', 'value' => 2),
            array('name' => $prefix . ':sequencecheck',
                    'value' => $attemptobj->get_question_attempt(1)->get_sequence_check_count()),
            array('name' => $prefix . 'answer', 'value' => 1),
        );

        $result = mod_hippotrack_external::save_attempt($attempt->id, $data);
        $result = \external_api::clean_returnvalue(mod_hippotrack_external::save_attempt_returns(), $result);
        $this->assertTrue($result['status']);

        // Now, get the summary.
        $result = mod_hippotrack_external::get_attempt_summary($attempt->id);
        $result = \external_api::clean_returnvalue(mod_hippotrack_external::get_attempt_summary_returns(), $result);

        // Check it's marked as completed only the first one.
        $this->assertEquals('complete', $result['questions'][0]['state']);
        $this->assertEquals(1, $result['questions'][0]['sequencecheck']);
        $this->assertEquals('complete', $result['questions'][1]['state']);
        $this->assertEquals(1, $result['questions'][1]['sequencecheck']);

    }

    /**
     * Test process_attempt
     */
    public function test_process_attempt() {
        global $DB;

        $timenow = time();
        // Create a new hippotrack with three questions and one attempt started.
        list($hippotrack, $context, $hippotrackobj, $attempt, $attemptobj, $quba) = $this->create_hippotrack_with_questions(true, false,
            'deferredfeedback', true);

        // Response for slot 1.
        $prefix = $quba->get_field_prefix(1);
        $data = array(
            array('name' => 'slots', 'value' => 1),
            array('name' => $prefix . ':sequencecheck',
                    'value' => $attemptobj->get_question_attempt(1)->get_sequence_check_count()),
            array('name' => $prefix . 'answer', 'value' => 1),
        );

        $this->setUser($this->student);

        $result = mod_hippotrack_external::process_attempt($attempt->id, $data);
        $result = \external_api::clean_returnvalue(mod_hippotrack_external::process_attempt_returns(), $result);
        $this->assertEquals(hippotrack_attempt::IN_PROGRESS, $result['state']);

        $result = mod_hippotrack_external::get_attempt_data($attempt->id, 2);

        // Now, get the summary.
        $result = mod_hippotrack_external::get_attempt_summary($attempt->id);
        $result = \external_api::clean_returnvalue(mod_hippotrack_external::get_attempt_summary_returns(), $result);

        // Check it's marked as completed only the first one.
        $this->assertEquals('complete', $result['questions'][0]['state']);
        $this->assertEquals('todo', $result['questions'][1]['state']);
        $this->assertEquals(1, $result['questions'][0]['number']);
        $this->assertEquals(2, $result['questions'][1]['number']);
        $this->assertFalse($result['questions'][0]['flagged']);
        $this->assertFalse($result['questions'][1]['flagged']);
        $this->assertEmpty($result['questions'][0]['mark']);
        $this->assertEmpty($result['questions'][1]['mark']);
        $this->assertEquals(2, $result['questions'][0]['sequencecheck']);
        $this->assertEquals(2, $result['questions'][0]['sequencecheck']);
        $this->assertGreaterThanOrEqual($timenow, $result['questions'][0]['lastactiontime']);
        $this->assertGreaterThanOrEqual($timenow, $result['questions'][0]['lastactiontime']);
        $this->assertEquals(false, $result['questions'][0]['hasautosavedstep']);
        $this->assertEquals(false, $result['questions'][0]['hasautosavedstep']);

        // Now, second slot.
        $prefix = $quba->get_field_prefix(2);
        $data = array(
            array('name' => 'slots', 'value' => 2),
            array('name' => $prefix . ':sequencecheck',
                    'value' => $attemptobj->get_question_attempt(1)->get_sequence_check_count()),
            array('name' => $prefix . 'answer', 'value' => 1),
            array('name' => $prefix . ':flagged', 'value' => 1),
        );

        $result = mod_hippotrack_external::process_attempt($attempt->id, $data);
        $result = \external_api::clean_returnvalue(mod_hippotrack_external::process_attempt_returns(), $result);
        $this->assertEquals(hippotrack_attempt::IN_PROGRESS, $result['state']);

        // Now, get the summary.
        $result = mod_hippotrack_external::get_attempt_summary($attempt->id);
        $result = \external_api::clean_returnvalue(mod_hippotrack_external::get_attempt_summary_returns(), $result);

        // Check it's marked as completed the two first questions.
        $this->assertEquals('complete', $result['questions'][0]['state']);
        $this->assertEquals('complete', $result['questions'][1]['state']);
        $this->assertFalse($result['questions'][0]['flagged']);
        $this->assertTrue($result['questions'][1]['flagged']);

        // Add files in the attachment response.
        $draftitemid = file_get_unused_draft_itemid();
        $filerecordinline = array(
            'contextid' => \context_user::instance($this->student->id)->id,
            'component' => 'user',
            'filearea'  => 'draft',
            'itemid'    => $draftitemid,
            'filepath'  => '/',
            'filename'  => 'faketxt.txt',
        );
        $fs = get_file_storage();
        $fs->create_file_from_string($filerecordinline, 'fake txt contents 1.');

        // Last slot.
        $prefix = $quba->get_field_prefix(3);
        $data = array(
            array('name' => 'slots', 'value' => 3),
            array('name' => $prefix . ':sequencecheck',
                    'value' => $attemptobj->get_question_attempt(1)->get_sequence_check_count()),
            array('name' => $prefix . 'answer', 'value' => 'Some test'),
            array('name' => $prefix . 'answerformat', 'value' => FORMAT_HTML),
            array('name' => $prefix . 'attachments', 'value' => $draftitemid),
        );

        $result = mod_hippotrack_external::process_attempt($attempt->id, $data);
        $result = \external_api::clean_returnvalue(mod_hippotrack_external::process_attempt_returns(), $result);
        $this->assertEquals(hippotrack_attempt::IN_PROGRESS, $result['state']);

        // Now, get the summary.
        $result = mod_hippotrack_external::get_attempt_summary($attempt->id);
        $result = \external_api::clean_returnvalue(mod_hippotrack_external::get_attempt_summary_returns(), $result);

        $this->assertEquals('complete', $result['questions'][0]['state']);
        $this->assertEquals('complete', $result['questions'][1]['state']);
        $this->assertEquals('complete', $result['questions'][2]['state']);
        $this->assertFalse($result['questions'][0]['flagged']);
        $this->assertTrue($result['questions'][1]['flagged']);
        $this->assertFalse($result['questions'][2]['flagged']);

        // Check submitted files are there.
        $this->assertCount(1, $result['questions'][2]['responsefileareas']);
        $this->assertEquals('attachments', $result['questions'][2]['responsefileareas'][0]['area']);
        $this->assertCount(1, $result['questions'][2]['responsefileareas'][0]['files']);
        $this->assertEquals($filerecordinline['filename'], $result['questions'][2]['responsefileareas'][0]['files'][0]['filename']);

        // Finish the attempt.
        $sink = $this->redirectMessages();
        $result = mod_hippotrack_external::process_attempt($attempt->id, array(), true);
        $result = \external_api::clean_returnvalue(mod_hippotrack_external::process_attempt_returns(), $result);
        $this->assertEquals(hippotrack_attempt::FINISHED, $result['state']);
        $messages = $sink->get_messages();
        $message = reset($messages);
        $sink->close();
        // Test customdata.
        if (!empty($message->customdata)) {
            $customdata = json_decode($message->customdata);
            $this->assertEquals($hippotrackobj->get_hippotrackid(), $customdata->instance);
            $this->assertEquals($hippotrackobj->get_cmid(), $customdata->cmid);
            $this->assertEquals($attempt->id, $customdata->attemptid);
            $this->assertObjectHasAttribute('notificationiconurl', $customdata);
        }

        // Start new attempt.
        $quba = \question_engine::make_questions_usage_by_activity('mod_hippotrack', $hippotrackobj->get_context());
        $quba->set_preferred_behaviour($hippotrackobj->get_hippotrack()->preferredbehaviour);

        $timenow = time();
        $attempt = hippotrack_create_attempt($hippotrackobj, 2, false, $timenow, false, $this->student->id);
        hippotrack_start_new_attempt($hippotrackobj, $quba, $attempt, 2, $timenow);
        hippotrack_attempt_save_started($hippotrackobj, $quba, $attempt);

        // Force grace period, attempt going to overdue.
        $hippotrack->timeclose = $timenow - 10;
        $hippotrack->graceperiod = 60;
        $hippotrack->overduehandling = 'graceperiod';
        $DB->update_record('hippotrack', $hippotrack);

        $result = mod_hippotrack_external::process_attempt($attempt->id, array());
        $result = \external_api::clean_returnvalue(mod_hippotrack_external::process_attempt_returns(), $result);
        $this->assertEquals(hippotrack_attempt::OVERDUE, $result['state']);

        // Force grace period for time limit.
        $hippotrack->timeclose = 0;
        $hippotrack->timelimit = 1;
        $hippotrack->graceperiod = 60;
        $hippotrack->overduehandling = 'graceperiod';
        $DB->update_record('hippotrack', $hippotrack);

        $timenow = time();
        $quba = \question_engine::make_questions_usage_by_activity('mod_hippotrack', $hippotrackobj->get_context());
        $quba->set_preferred_behaviour($hippotrackobj->get_hippotrack()->preferredbehaviour);
        $attempt = hippotrack_create_attempt($hippotrackobj, 3, 2, $timenow - 10, false, $this->student->id);
        hippotrack_start_new_attempt($hippotrackobj, $quba, $attempt, 2, $timenow - 10);
        hippotrack_attempt_save_started($hippotrackobj, $quba, $attempt);

        $result = mod_hippotrack_external::process_attempt($attempt->id, array());
        $result = \external_api::clean_returnvalue(mod_hippotrack_external::process_attempt_returns(), $result);
        $this->assertEquals(hippotrack_attempt::OVERDUE, $result['state']);

        // New attempt.
        $timenow = time();
        $quba = \question_engine::make_questions_usage_by_activity('mod_hippotrack', $hippotrackobj->get_context());
        $quba->set_preferred_behaviour($hippotrackobj->get_hippotrack()->preferredbehaviour);
        $attempt = hippotrack_create_attempt($hippotrackobj, 4, 3, $timenow, false, $this->student->id);
        hippotrack_start_new_attempt($hippotrackobj, $quba, $attempt, 3, $timenow);
        hippotrack_attempt_save_started($hippotrackobj, $quba, $attempt);

        // Force abandon.
        $hippotrack->timeclose = $timenow - HOURSECS;
        $DB->update_record('hippotrack', $hippotrack);

        $result = mod_hippotrack_external::process_attempt($attempt->id, array());
        $result = \external_api::clean_returnvalue(mod_hippotrack_external::process_attempt_returns(), $result);
        $this->assertEquals(hippotrack_attempt::ABANDONED, $result['state']);

    }

    /**
     * Test validate_attempt_review
     */
    public function test_validate_attempt_review() {
        global $DB;

        // Create a new hippotrack with one attempt started.
        list($hippotrack, $context, $hippotrackobj, $attempt, $attemptobj) = $this->create_hippotrack_with_questions(true);

        $this->setUser($this->student);

        // Invalid attempt, invalid id.
        try {
            $params = array('attemptid' => -1);
            testable_mod_hippotrack_external::validate_attempt_review($params);
            $this->fail('Exception expected due invalid id.');
        } catch (\dml_missing_record_exception $e) {
            $this->assertEquals('invalidrecord', $e->errorcode);
        }

        // Invalid attempt, not closed.
        try {
            $params = array('attemptid' => $attempt->id);
            testable_mod_hippotrack_external::validate_attempt_review($params);
            $this->fail('Exception expected due not closed attempt.');
        } catch (\moodle_hippotrack_exception $e) {
            $this->assertEquals('attemptclosed', $e->errorcode);
        }

        // Test ok case (finished attempt).
        list($hippotrack, $context, $hippotrackobj, $attempt, $attemptobj) = $this->create_hippotrack_with_questions(true, true);

        $params = array('attemptid' => $attempt->id);
        testable_mod_hippotrack_external::validate_attempt_review($params);

        // Teacher should be able to view the review of one student's attempt.
        $this->setUser($this->teacher);
        testable_mod_hippotrack_external::validate_attempt_review($params);

        // We should not see other students attempts.
        $anotherstudent = self::getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($anotherstudent->id, $this->course->id, $this->studentrole->id, 'manual');

        $this->setUser($anotherstudent);
        try {
            $params = array('attemptid' => $attempt->id);
            testable_mod_hippotrack_external::validate_attempt_review($params);
            $this->fail('Exception expected due missing permissions.');
        } catch (\moodle_hippotrack_exception $e) {
            $this->assertEquals('noreviewattempt', $e->errorcode);
        }
    }


    /**
     * Test get_attempt_review
     */
    public function test_get_attempt_review() {
        global $DB;

        // Create a new hippotrack with two questions and one attempt finished.
        list($hippotrack, $context, $hippotrackobj, $attempt, $attemptobj, $quba) = $this->create_hippotrack_with_questions(true, true);

        // Add feedback to the hippotrack.
        $feedback = new \stdClass();
        $feedback->hippotrackid = $hippotrack->id;
        $feedback->feedbacktext = 'Feedback text 1';
        $feedback->feedbacktextformat = 1;
        $feedback->mingrade = 49;
        $feedback->maxgrade = 100;
        $feedback->id = $DB->insert_record('hippotrack_feedback', $feedback);

        $feedback->feedbacktext = 'Feedback text 2';
        $feedback->feedbacktextformat = 1;
        $feedback->mingrade = 30;
        $feedback->maxgrade = 48;
        $feedback->id = $DB->insert_record('hippotrack_feedback', $feedback);

        $result = mod_hippotrack_external::get_attempt_review($attempt->id);
        $result = \external_api::clean_returnvalue(mod_hippotrack_external::get_attempt_review_returns(), $result);

        // Two questions, one completed and correct, the other gave up.
        $this->assertEquals(50, $result['grade']);
        $this->assertEquals(1, $result['attempt']['attempt']);
        $this->assertEquals('finished', $result['attempt']['state']);
        $this->assertEquals(1, $result['attempt']['sumgrades']);
        $this->assertCount(2, $result['questions']);
        $this->assertEquals('gradedright', $result['questions'][0]['state']);
        $this->assertEquals(1, $result['questions'][0]['slot']);
        $this->assertEquals('gaveup', $result['questions'][1]['state']);
        $this->assertEquals(2, $result['questions'][1]['slot']);

        $this->assertCount(1, $result['additionaldata']);
        $this->assertEquals('feedback', $result['additionaldata'][0]['id']);
        $this->assertEquals('Feedback', $result['additionaldata'][0]['title']);
        $this->assertEquals('Feedback text 1', $result['additionaldata'][0]['content']);

        // Only first page.
        $result = mod_hippotrack_external::get_attempt_review($attempt->id, 0);
        $result = \external_api::clean_returnvalue(mod_hippotrack_external::get_attempt_review_returns(), $result);

        $this->assertEquals(50, $result['grade']);
        $this->assertEquals(1, $result['attempt']['attempt']);
        $this->assertEquals('finished', $result['attempt']['state']);
        $this->assertEquals(1, $result['attempt']['sumgrades']);
        $this->assertCount(1, $result['questions']);
        $this->assertEquals('gradedright', $result['questions'][0]['state']);
        $this->assertEquals(1, $result['questions'][0]['slot']);

         $this->assertCount(1, $result['additionaldata']);
        $this->assertEquals('feedback', $result['additionaldata'][0]['id']);
        $this->assertEquals('Feedback', $result['additionaldata'][0]['title']);
        $this->assertEquals('Feedback text 1', $result['additionaldata'][0]['content']);

    }

    /**
     * Test test_view_attempt
     */
    public function test_view_attempt() {
        global $DB;

        // Create a new hippotrack with two questions and one attempt started.
        list($hippotrack, $context, $hippotrackobj, $attempt, $attemptobj, $quba) = $this->create_hippotrack_with_questions(true, false);

        // Test user with full capabilities.
        $this->setUser($this->student);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();

        $result = mod_hippotrack_external::view_attempt($attempt->id, 0);
        $result = \external_api::clean_returnvalue(mod_hippotrack_external::view_attempt_returns(), $result);
        $this->assertTrue($result['status']);

        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = array_shift($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_hippotrack\event\attempt_viewed', $event);
        $this->assertEquals($context, $event->get_context());
        $this->assertEventContextNotUsed($event);
        $this->assertNotEmpty($event->get_name());

        // Now, force the hippotrack with HIPPOTRACK_NAVMETHOD_SEQ (sequential) navigation method.
        $DB->set_field('hippotrack', 'navmethod', HIPPOTRACK_NAVMETHOD_SEQ, array('id' => $hippotrack->id));
        // HippoTrack requiring preflightdata.
        $DB->set_field('hippotrack', 'password', 'abcdef', array('id' => $hippotrack->id));
        $preflightdata = array(array("name" => "hippotrackpassword", "value" => 'abcdef'));

        // See next page.
        $result = mod_hippotrack_external::view_attempt($attempt->id, 1, $preflightdata);
        $result = \external_api::clean_returnvalue(mod_hippotrack_external::view_attempt_returns(), $result);
        $this->assertTrue($result['status']);

        $events = $sink->get_events();
        $this->assertCount(2, $events);

        // Try to go to previous page.
        try {
            mod_hippotrack_external::view_attempt($attempt->id, 0);
            $this->fail('Exception expected due to try to see a previous page.');
        } catch (\moodle_hippotrack_exception $e) {
            $this->assertEquals('Out of sequence access', $e->errorcode);
        }

    }

    /**
     * Test test_view_attempt_summary
     */
    public function test_view_attempt_summary() {
        global $DB;

        // Create a new hippotrack with two questions and one attempt started.
        list($hippotrack, $context, $hippotrackobj, $attempt, $attemptobj, $quba) = $this->create_hippotrack_with_questions(true, false);

        // Test user with full capabilities.
        $this->setUser($this->student);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();

        $result = mod_hippotrack_external::view_attempt_summary($attempt->id);
        $result = \external_api::clean_returnvalue(mod_hippotrack_external::view_attempt_summary_returns(), $result);
        $this->assertTrue($result['status']);

        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = array_shift($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_hippotrack\event\attempt_summary_viewed', $event);
        $this->assertEquals($context, $event->get_context());
        $moodlehippotrack = new \moodle_url('/mod/hippotrack/summary.php', array('attempt' => $attempt->id));
        $this->assertEquals($moodlehippotrack, $event->get_url());
        $this->assertEventContextNotUsed($event);
        $this->assertNotEmpty($event->get_name());

        // HippoTrack requiring preflightdata.
        $DB->set_field('hippotrack', 'password', 'abcdef', array('id' => $hippotrack->id));
        $preflightdata = array(array("name" => "hippotrackpassword", "value" => 'abcdef'));

        $result = mod_hippotrack_external::view_attempt_summary($attempt->id, $preflightdata);
        $result = \external_api::clean_returnvalue(mod_hippotrack_external::view_attempt_summary_returns(), $result);
        $this->assertTrue($result['status']);

    }

    /**
     * Test test_view_attempt_summary
     */
    public function test_view_attempt_review() {
        global $DB;

        // Create a new hippotrack with two questions and one attempt finished.
        list($hippotrack, $context, $hippotrackobj, $attempt, $attemptobj, $quba) = $this->create_hippotrack_with_questions(true, true);

        // Test user with full capabilities.
        $this->setUser($this->student);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();

        $result = mod_hippotrack_external::view_attempt_review($attempt->id, 0);
        $result = \external_api::clean_returnvalue(mod_hippotrack_external::view_attempt_review_returns(), $result);
        $this->assertTrue($result['status']);

        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = array_shift($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_hippotrack\event\attempt_reviewed', $event);
        $this->assertEquals($context, $event->get_context());
        $moodlehippotrack = new \moodle_url('/mod/hippotrack/review.php', array('attempt' => $attempt->id));
        $this->assertEquals($moodlehippotrack, $event->get_url());
        $this->assertEventContextNotUsed($event);
        $this->assertNotEmpty($event->get_name());

    }

    /**
     * Test get_hippotrack_feedback_for_grade
     */
    public function test_get_hippotrack_feedback_for_grade() {
        global $DB;

        // Add feedback to the hippotrack.
        $feedback = new \stdClass();
        $feedback->hippotrackid = $this->hippotrack->id;
        $feedback->feedbacktext = 'Feedback text 1';
        $feedback->feedbacktextformat = 1;
        $feedback->mingrade = 49;
        $feedback->maxgrade = 100;
        $feedback->id = $DB->insert_record('hippotrack_feedback', $feedback);
        // Add a fake inline image to the feedback text.
        $filename = 'shouldbeanimage.jpg';
        $filerecordinline = array(
            'contextid' => $this->context->id,
            'component' => 'mod_hippotrack',
            'filearea'  => 'feedback',
            'itemid'    => $feedback->id,
            'filepath'  => '/',
            'filename'  => $filename,
        );
        $fs = get_file_storage();
        $fs->create_file_from_string($filerecordinline, 'image contents (not really)');

        $feedback->feedbacktext = 'Feedback text 2';
        $feedback->feedbacktextformat = 1;
        $feedback->mingrade = 30;
        $feedback->maxgrade = 49;
        $feedback->id = $DB->insert_record('hippotrack_feedback', $feedback);

        $result = mod_hippotrack_external::get_hippotrack_feedback_for_grade($this->hippotrack->id, 50);
        $result = \external_api::clean_returnvalue(mod_hippotrack_external::get_hippotrack_feedback_for_grade_returns(), $result);
        $this->assertEquals('Feedback text 1', $result['feedbacktext']);
        $this->assertEquals($filename, $result['feedbackinlinefiles'][0]['filename']);
        $this->assertEquals(FORMAT_HTML, $result['feedbacktextformat']);

        $result = mod_hippotrack_external::get_hippotrack_feedback_for_grade($this->hippotrack->id, 30);
        $result = \external_api::clean_returnvalue(mod_hippotrack_external::get_hippotrack_feedback_for_grade_returns(), $result);
        $this->assertEquals('Feedback text 2', $result['feedbacktext']);
        $this->assertEquals(FORMAT_HTML, $result['feedbacktextformat']);

        $result = mod_hippotrack_external::get_hippotrack_feedback_for_grade($this->hippotrack->id, 10);
        $result = \external_api::clean_returnvalue(mod_hippotrack_external::get_hippotrack_feedback_for_grade_returns(), $result);
        $this->assertEquals('', $result['feedbacktext']);
        $this->assertEquals(FORMAT_MOODLE, $result['feedbacktextformat']);
    }

    /**
     * Test get_hippotrack_access_information
     */
    public function test_get_hippotrack_access_information() {
        global $DB;

        // Create a new hippotrack.
        $hippotrackgenerator = $this->getDataGenerator()->get_plugin_generator('mod_hippotrack');
        $data = array('course' => $this->course->id);
        $hippotrack = $hippotrackgenerator->create_instance($data);

        $this->setUser($this->student);

        // Default restrictions (none).
        $result = mod_hippotrack_external::get_hippotrack_access_information($hippotrack->id);
        $result = \external_api::clean_returnvalue(mod_hippotrack_external::get_hippotrack_access_information_returns(), $result);

        $expected = array(
            'canattempt' => true,
            'canmanage' => false,
            'canpreview' => false,
            'canreviewmyattempts' => true,
            'canviewreports' => false,
            'accessrules' => [],
            // This rule is always used, even if the hippotrack has no open or close date.
            'activerulenames' => ['hippotrackaccess_openclosedate'],
            'preventaccessreasons' => [],
            'warnings' => []
        );

        $this->assertEquals($expected, $result);

        // Now teacher, different privileges.
        $this->setUser($this->teacher);
        $result = mod_hippotrack_external::get_hippotrack_access_information($hippotrack->id);
        $result = \external_api::clean_returnvalue(mod_hippotrack_external::get_hippotrack_access_information_returns(), $result);

        $expected['canmanage'] = true;
        $expected['canpreview'] = true;
        $expected['canviewreports'] = true;
        $expected['canattempt'] = false;
        $expected['canreviewmyattempts'] = false;

        $this->assertEquals($expected, $result);

        $this->setUser($this->student);
        // Now add some restrictions.
        $hippotrack->timeopen = time() + DAYSECS;
        $hippotrack->timeclose = time() + WEEKSECS;
        $hippotrack->password = '123456';
        $DB->update_record('hippotrack', $hippotrack);

        $result = mod_hippotrack_external::get_hippotrack_access_information($hippotrack->id);
        $result = \external_api::clean_returnvalue(mod_hippotrack_external::get_hippotrack_access_information_returns(), $result);

        // Access is limited by time and password, but only the password limit has a description.
        $this->assertCount(1, $result['accessrules']);
        // Two rule names, password and open/close date.
        $this->assertCount(2, $result['activerulenames']);
        $this->assertCount(1, $result['preventaccessreasons']);

    }

    /**
     * Test get_attempt_access_information
     */
    public function test_get_attempt_access_information() {
        global $DB;

        $this->setAdminUser();

        // Create a new hippotrack with attempts.
        $hippotrackgenerator = $this->getDataGenerator()->get_plugin_generator('mod_hippotrack');
        $data = array('course' => $this->course->id,
                      'sumgrades' => 2);
        $hippotrack = $hippotrackgenerator->create_instance($data);

        // Create some questions.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');

        $cat = $questiongenerator->create_question_category();
        $question = $questiongenerator->create_question('numerical', null, array('category' => $cat->id));
        hippotrack_add_hippotrack_question($question->id, $hippotrack);

        $question = $questiongenerator->create_question('shortanswer', null, array('category' => $cat->id));
        hippotrack_add_hippotrack_question($question->id, $hippotrack);

        // Add new question types in the category (for the random one).
        $question = $questiongenerator->create_question('truefalse', null, array('category' => $cat->id));
        $question = $questiongenerator->create_question('essay', null, array('category' => $cat->id));

        hippotrack_add_random_questions($hippotrack, 0, $cat->id, 1, false);

        $hippotrackobj = hippotrack::create($hippotrack->id, $this->student->id);

        // Set grade to pass.
        $item = \grade_item::fetch(array('courseid' => $this->course->id, 'itemtype' => 'mod',
                                        'itemmodule' => 'hippotrack', 'iteminstance' => $hippotrack->id, 'outcomeid' => null));
        $item->gradepass = 80;
        $item->update();

        $this->setUser($this->student);

        // Default restrictions (none).
        $result = mod_hippotrack_external::get_attempt_access_information($hippotrack->id);
        $result = \external_api::clean_returnvalue(mod_hippotrack_external::get_attempt_access_information_returns(), $result);

        $expected = array(
            'isfinished' => false,
            'preventnewattemptreasons' => [],
            'warnings' => []
        );

        $this->assertEquals($expected, $result);

        // Limited attempts.
        $hippotrack->attempts = 1;
        $DB->update_record('hippotrack', $hippotrack);

        // Now, do one attempt.
        $quba = \question_engine::make_questions_usage_by_activity('mod_hippotrack', $hippotrackobj->get_context());
        $quba->set_preferred_behaviour($hippotrackobj->get_hippotrack()->preferredbehaviour);

        $timenow = time();
        $attempt = hippotrack_create_attempt($hippotrackobj, 1, false, $timenow, false, $this->student->id);
        hippotrack_start_new_attempt($hippotrackobj, $quba, $attempt, 1, $timenow);
        hippotrack_attempt_save_started($hippotrackobj, $quba, $attempt);

        // Process some responses from the student.
        $attemptobj = hippotrack_attempt::create($attempt->id);
        $tosubmit = array(1 => array('answer' => '3.14'));
        $attemptobj->process_submitted_actions($timenow, false, $tosubmit);

        // Finish the attempt.
        $attemptobj = hippotrack_attempt::create($attempt->id);
        $this->assertTrue($attemptobj->has_response_to_at_least_one_graded_question());
        $attemptobj->process_finish($timenow, false);

        // Can we start a new attempt? We shall not!
        $result = mod_hippotrack_external::get_attempt_access_information($hippotrack->id, $attempt->id);
        $result = \external_api::clean_returnvalue(mod_hippotrack_external::get_attempt_access_information_returns(), $result);

        // Now new attemps allowed.
        $this->assertCount(1, $result['preventnewattemptreasons']);
        $this->assertFalse($result['ispreflightcheckrequired']);
        $this->assertEquals(get_string('nomoreattempts', 'hippotrack'), $result['preventnewattemptreasons'][0]);

    }

    /**
     * Test get_hippotrack_required_qtypes
     */
    public function test_get_hippotrack_required_qtypes() {
        $this->setAdminUser();

        // Create a new hippotrack.
        $hippotrackgenerator = $this->getDataGenerator()->get_plugin_generator('mod_hippotrack');
        $data = array('course' => $this->course->id);
        $hippotrack = $hippotrackgenerator->create_instance($data);

        // Create some questions.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');

        $cat = $questiongenerator->create_question_category();
        $question = $questiongenerator->create_question('numerical', null, array('category' => $cat->id));
        hippotrack_add_hippotrack_question($question->id, $hippotrack);

        $question = $questiongenerator->create_question('shortanswer', null, array('category' => $cat->id));
        hippotrack_add_hippotrack_question($question->id, $hippotrack);

        $question = $questiongenerator->create_question('truefalse', null, array('category' => $cat->id));
        hippotrack_add_hippotrack_question($question->id, $hippotrack);

        $question = $questiongenerator->create_question('essay', null, array('category' => $cat->id));
        hippotrack_add_hippotrack_question($question->id, $hippotrack);

        $question = $questiongenerator->create_question('multichoice', null,
                ['category' => $cat->id, 'status' => question_version_status::QUESTION_STATUS_DRAFT]);
        hippotrack_add_hippotrack_question($question->id, $hippotrack);

        $this->setUser($this->student);

        $result = mod_hippotrack_external::get_hippotrack_required_qtypes($hippotrack->id);
        $result = \external_api::clean_returnvalue(mod_hippotrack_external::get_hippotrack_required_qtypes_returns(), $result);

        $expected = array(
            'questiontypes' => ['essay', 'numerical', 'shortanswer', 'truefalse'],
            'warnings' => []
        );

        $this->assertEquals($expected, $result);

    }

    /**
     * Test get_hippotrack_required_qtypes for hippotrack with random questions
     */
    public function test_get_hippotrack_required_qtypes_random() {
        $this->setAdminUser();

        // Create a new hippotrack.
        $hippotrackgenerator = $this->getDataGenerator()->get_plugin_generator('mod_hippotrack');
        $hippotrack = $hippotrackgenerator->create_instance(['course' => $this->course->id]);

        // Create some questions.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');

        $cat = $questiongenerator->create_question_category();
        $anothercat = $questiongenerator->create_question_category();

        $question = $questiongenerator->create_question('numerical', null, ['category' => $cat->id]);
        $question = $questiongenerator->create_question('shortanswer', null, ['category' => $cat->id]);
        $question = $questiongenerator->create_question('truefalse', null, ['category' => $cat->id]);
        // Question in a different category.
        $question = $questiongenerator->create_question('essay', null, ['category' => $anothercat->id]);

        // Add a couple of random questions from the same category.
        hippotrack_add_random_questions($hippotrack, 0, $cat->id, 1, false);
        hippotrack_add_random_questions($hippotrack, 0, $cat->id, 1, false);

        $this->setUser($this->student);

        $result = mod_hippotrack_external::get_hippotrack_required_qtypes($hippotrack->id);
        $result = \external_api::clean_returnvalue(mod_hippotrack_external::get_hippotrack_required_qtypes_returns(), $result);

        $expected = ['numerical', 'shortanswer', 'truefalse'];
        ksort($result['questiontypes']);

        $this->assertEquals($expected, $result['questiontypes']);

        // Add more questions to the hippotrack, this time from the other category.
        $this->setAdminUser();
        hippotrack_add_random_questions($hippotrack, 0, $anothercat->id, 1, false);

        $this->setUser($this->student);
        $result = mod_hippotrack_external::get_hippotrack_required_qtypes($hippotrack->id);
        $result = \external_api::clean_returnvalue(mod_hippotrack_external::get_hippotrack_required_qtypes_returns(), $result);

        // The new question from the new category is returned as a potential random question for the hippotrack.
        $expected = ['essay', 'numerical', 'shortanswer', 'truefalse'];
        ksort($result['questiontypes']);

        $this->assertEquals($expected, $result['questiontypes']);
    }

    /**
     * Test that a sequential navigation hippotrack is not allowing to see questions in advance except if reviewing
     */
    public function test_sequential_navigation_view_attempt() {
        // Test user with full capabilities.
        $hippotrack = $this->prepare_sequential_hippotrack();
        $attemptobj = $this->create_hippotrack_attempt_object($hippotrack);
        $this->setUser($this->student);
        // Check out of sequence access for view.
        $this->assertNotEmpty(mod_hippotrack_external::view_attempt($attemptobj->get_attemptid(), 0, []));
        try {
            mod_hippotrack_external::view_attempt($attemptobj->get_attemptid(), 3, []);
            $this->fail('Exception expected due to out of sequence access.');
        } catch (\moodle_exception $e) {
            $this->assertStringContainsString('hippotrack/Out of sequence access', $e->getMessage());
        }
    }

    /**
     * Test that a sequential navigation hippotrack is not allowing to see questions in advance for a student
     */
    public function test_sequential_navigation_attempt_summary() {
        // Test user with full capabilities.
        $hippotrack = $this->prepare_sequential_hippotrack();
        $attemptobj = $this->create_hippotrack_attempt_object($hippotrack);
        $this->setUser($this->student);
        // Check that we do not return other questions than the one currently viewed.
        $result = mod_hippotrack_external::get_attempt_summary($attemptobj->get_attemptid());
        $this->assertCount(1, $result['questions']);
        $this->assertStringContainsString('Question (1)', $result['questions'][0]['html']);
    }

    /**
     * Test that a sequential navigation hippotrack is not allowing to see questions in advance for student
     */
    public function test_sequential_navigation_get_attempt_data() {
        // Test user with full capabilities.
        $hippotrack = $this->prepare_sequential_hippotrack();
        $attemptobj = $this->create_hippotrack_attempt_object($hippotrack);
        $this->setUser($this->student);
        // Test invalid instance id.
        try {
            mod_hippotrack_external::get_attempt_data($attemptobj->get_attemptid(), 2);
            $this->fail('Exception expected due to out of sequence access.');
        } catch (\moodle_exception $e) {
            $this->assertStringContainsString('hippotrack/Out of sequence access', $e->getMessage());
        }
        // Now we moved to page 1, we should see page 2 and 1 but not 0 or 3.
        $attemptobj->set_currentpage(1);
        // Test invalid instance id.
        try {
            mod_hippotrack_external::get_attempt_data($attemptobj->get_attemptid(), 0);
            $this->fail('Exception expected due to out of sequence access.');
        } catch (\moodle_exception $e) {
            $this->assertStringContainsString('hippotrack/Out of sequence access', $e->getMessage());
        }

        try {
            mod_hippotrack_external::get_attempt_data($attemptobj->get_attemptid(), 3);
            $this->fail('Exception expected due to out of sequence access.');
        } catch (\moodle_exception $e) {
            $this->assertStringContainsString('hippotrack/Out of sequence access', $e->getMessage());
        }

        // Now we can see page 1.
        $result = mod_hippotrack_external::get_attempt_data($attemptobj->get_attemptid(), 1);
        $this->assertCount(1, $result['questions']);
        $this->assertStringContainsString('Question (2)', $result['questions'][0]['html']);
    }

    /**
     * Prepare hippotrack for sequential navigation tests
     *
     * @return hippotrack
     */
    private function prepare_sequential_hippotrack(): hippotrack {
        // Create a new hippotrack with 5 questions and one attempt started.
        // Create a new hippotrack with attempts.
        $hippotrackgenerator = $this->getDataGenerator()->get_plugin_generator('mod_hippotrack');
        $data = [
            'course' => $this->course->id,
            'sumgrades' => 2,
            'preferredbehaviour' => 'deferredfeedback',
            'navmethod' => HIPPOTRACK_NAVMETHOD_SEQ
        ];
        $hippotrack = $hippotrackgenerator->create_instance($data);

        // Now generate the questions.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        for ($pageindex = 1; $pageindex <= 5; $pageindex++) {
            $question = $questiongenerator->create_question('truefalse', null, [
                'category' => $cat->id,
                'questiontext' => ['text' => "Question ($pageindex)"]
            ]);
            hippotrack_add_hippotrack_question($question->id, $hippotrack, $pageindex);
        }

        $hippotrackobj = hippotrack::create($hippotrack->id, $this->student->id);
        // Set grade to pass.
        $item = \grade_item::fetch(array('courseid' => $this->course->id, 'itemtype' => 'mod',
            'itemmodule' => 'hippotrack', 'iteminstance' => $hippotrack->id, 'outcomeid' => null));
        $item->gradepass = 80;
        $item->update();
        return $hippotrackobj;
    }

    /**
     * Create question attempt
     *
     * @param hippotrack $hippotrackobj
     * @param int|null $userid
     * @param bool|null $ispreview
     * @return hippotrack_attempt
     * @throws \moodle_exception
     */
    private function create_hippotrack_attempt_object(hippotrack $hippotrackobj, ?int $userid = null, ?bool $ispreview = false): hippotrack_attempt {
        global $USER;
        $timenow = time();
        // Now, do one attempt.
        $quba = \question_engine::make_questions_usage_by_activity('mod_hippotrack', $hippotrackobj->get_context());
        $quba->set_preferred_behaviour($hippotrackobj->get_hippotrack()->preferredbehaviour);
        $attemptnumber = 1;
        if (!empty($USER->id)) {
            $attemptnumber = count(hippotrack_get_user_attempts($hippotrackobj->get_hippotrackid(), $USER->id)) + 1;
        }
        $attempt = hippotrack_create_attempt($hippotrackobj, $attemptnumber, false, $timenow, $ispreview, $userid ?? $this->student->id);
        hippotrack_start_new_attempt($hippotrackobj, $quba, $attempt, $attemptnumber, $timenow);
        hippotrack_attempt_save_started($hippotrackobj, $quba, $attempt);
        $attemptobj = hippotrack_attempt::create($attempt->id);
        return $attemptobj;
    }
}
