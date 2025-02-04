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
 * HippoTrack events tests.
 *
 * @package    mod_hippotrack
 * @category   phpunit
 * @copyright  2013 Adrian Greeve
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_hippotrack\event;

use hippotrack;
use hippotrack_attempt;
use context_module;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/hippotrack/attemptlib.php');

/**
 * Unit tests for hippotrack events.
 *
 * @package    mod_hippotrack
 * @category   phpunit
 * @copyright  2013 Adrian Greeve
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class events_test extends \advanced_testcase {

    /**
     * Setup a hippotrack.
     *
     * @return hippotrack the generated hippotrack.
     */
    protected function prepare_hippotrack() {

        $this->resetAfterTest(true);

        // Create a course
        $course = $this->getDataGenerator()->create_course();

        // Make a hippotrack.
        $hippotrackgenerator = $this->getDataGenerator()->get_plugin_generator('mod_hippotrack');

        $hippotrack = $hippotrackgenerator->create_instance(array('course' => $course->id, 'questionsperpage' => 0,
                'grade' => 100.0, 'sumgrades' => 2));

        $cm = get_coursemodule_from_instance('hippotrack', $hippotrack->id, $course->id);

        // Create a couple of questions.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');

        $cat = $questiongenerator->create_question_category();
        $saq = $questiongenerator->create_question('shortanswer', null, array('category' => $cat->id));
        $numq = $questiongenerator->create_question('numerical', null, array('category' => $cat->id));

        // Add them to the hippotrack.
        hippotrack_add_hippotrack_question($saq->id, $hippotrack);
        hippotrack_add_hippotrack_question($numq->id, $hippotrack);

        // Make a user to do the hippotrack.
        $user1 = $this->getDataGenerator()->create_user();
        $this->setUser($user1);

        return hippotrack::create($hippotrack->id, $user1->id);
    }

    /**
     * Setup a hippotrack attempt at the hippotrack created by {@link prepare_hippotrack()}.
     *
     * @param hippotrack $hippotrackobj the generated hippotrack.
     * @param bool $ispreview Make the attempt a preview attempt when true.
     * @return array with three elements, array($hippotrackobj, $quba, $attempt)
     */
    protected function prepare_hippotrack_attempt($hippotrackobj, $ispreview = false) {
        // Start the attempt.
        $quba = \question_engine::make_questions_usage_by_activity('mod_hippotrack', $hippotrackobj->get_context());
        $quba->set_preferred_behaviour($hippotrackobj->get_hippotrack()->preferredbehaviour);

        $timenow = time();
        $attempt = hippotrack_create_attempt($hippotrackobj, 1, false, $timenow, $ispreview);
        hippotrack_start_new_attempt($hippotrackobj, $quba, $attempt, 1, $timenow);
        hippotrack_attempt_save_started($hippotrackobj, $quba, $attempt);

        return array($hippotrackobj, $quba, $attempt);
    }

    /**
     * Setup some convenience test data with a single attempt.
     *
     * @param bool $ispreview Make the attempt a preview attempt when true.
     * @return array with three elements, array($hippotrackobj, $quba, $attempt)
     */
    protected function prepare_hippotrack_data($ispreview = false) {
        $hippotrackobj = $this->prepare_hippotrack();
        return $this->prepare_hippotrack_attempt($hippotrackobj, $ispreview);
    }

    public function test_attempt_submitted() {

        list($hippotrackobj, $quba, $attempt) = $this->prepare_hippotrack_data();
        $attemptobj = hippotrack_attempt::create($attempt->id);

        // Catch the event.
        $sink = $this->redirectEvents();

        $timefinish = time();
        $attemptobj->process_finish($timefinish, false);
        $events = $sink->get_events();
        $sink->close();

        // Validate the event.
        $this->assertCount(3, $events);
        $event = $events[2];
        $this->assertInstanceOf('\mod_hippotrack\event\attempt_submitted', $event);
        $this->assertEquals('hippotrack_attempts', $event->objecttable);
        $this->assertEquals($hippotrackobj->get_context(), $event->get_context());
        $this->assertEquals($attempt->userid, $event->relateduserid);
        $this->assertEquals(null, $event->other['submitterid']); // Should be the user, but PHP Unit complains...
        $this->assertEquals('hippotrack_attempt_submitted', $event->get_legacy_eventname());
        $legacydata = new \stdClass();
        $legacydata->component = 'mod_hippotrack';
        $legacydata->attemptid = (string) $attempt->id;
        $legacydata->timestamp = $timefinish;
        $legacydata->userid = $attempt->userid;
        $legacydata->cmid = $hippotrackobj->get_cmid();
        $legacydata->courseid = $hippotrackobj->get_courseid();
        $legacydata->hippotrackid = $hippotrackobj->get_hippotrackid();
        // Submitterid should be the user, but as we are in PHP Unit, CLI_SCRIPT is set to true which sets null in submitterid.
        $legacydata->submitterid = null;
        $legacydata->timefinish = $timefinish;
        $this->assertEventLegacyData($legacydata, $event);
        $this->assertEventContextNotUsed($event);
    }

    public function test_attempt_becameoverdue() {

        list($hippotrackobj, $quba, $attempt) = $this->prepare_hippotrack_data();
        $attemptobj = hippotrack_attempt::create($attempt->id);

        // Catch the event.
        $sink = $this->redirectEvents();
        $timefinish = time();
        $attemptobj->process_going_overdue($timefinish, false);
        $events = $sink->get_events();
        $sink->close();

        $this->assertCount(1, $events);
        $event = $events[0];
        $this->assertInstanceOf('\mod_hippotrack\event\attempt_becameoverdue', $event);
        $this->assertEquals('hippotrack_attempts', $event->objecttable);
        $this->assertEquals($hippotrackobj->get_context(), $event->get_context());
        $this->assertEquals($attempt->userid, $event->relateduserid);
        $this->assertNotEmpty($event->get_description());
        // Submitterid should be the user, but as we are in PHP Unit, CLI_SCRIPT is set to true which sets null in submitterid.
        $this->assertEquals(null, $event->other['submitterid']);
        $this->assertEquals('hippotrack_attempt_overdue', $event->get_legacy_eventname());
        $legacydata = new \stdClass();
        $legacydata->component = 'mod_hippotrack';
        $legacydata->attemptid = (string) $attempt->id;
        $legacydata->timestamp = $timefinish;
        $legacydata->userid = $attempt->userid;
        $legacydata->cmid = $hippotrackobj->get_cmid();
        $legacydata->courseid = $hippotrackobj->get_courseid();
        $legacydata->hippotrackid = $hippotrackobj->get_hippotrackid();
        $legacydata->submitterid = null; // Should be the user, but PHP Unit complains...
        $this->assertEventLegacyData($legacydata, $event);
        $this->assertEventContextNotUsed($event);
    }

    public function test_attempt_abandoned() {

        list($hippotrackobj, $quba, $attempt) = $this->prepare_hippotrack_data();
        $attemptobj = hippotrack_attempt::create($attempt->id);

        // Catch the event.
        $sink = $this->redirectEvents();
        $timefinish = time();
        $attemptobj->process_abandon($timefinish, false);
        $events = $sink->get_events();
        $sink->close();

        $this->assertCount(1, $events);
        $event = $events[0];
        $this->assertInstanceOf('\mod_hippotrack\event\attempt_abandoned', $event);
        $this->assertEquals('hippotrack_attempts', $event->objecttable);
        $this->assertEquals($hippotrackobj->get_context(), $event->get_context());
        $this->assertEquals($attempt->userid, $event->relateduserid);
        // Submitterid should be the user, but as we are in PHP Unit, CLI_SCRIPT is set to true which sets null in submitterid.
        $this->assertEquals(null, $event->other['submitterid']);
        $this->assertEquals('hippotrack_attempt_abandoned', $event->get_legacy_eventname());
        $legacydata = new \stdClass();
        $legacydata->component = 'mod_hippotrack';
        $legacydata->attemptid = (string) $attempt->id;
        $legacydata->timestamp = $timefinish;
        $legacydata->userid = $attempt->userid;
        $legacydata->cmid = $hippotrackobj->get_cmid();
        $legacydata->courseid = $hippotrackobj->get_courseid();
        $legacydata->hippotrackid = $hippotrackobj->get_hippotrackid();
        $legacydata->submitterid = null; // Should be the user, but PHP Unit complains...
        $this->assertEventLegacyData($legacydata, $event);
        $this->assertEventContextNotUsed($event);
    }

    public function test_attempt_started() {
        $hippotrackobj = $this->prepare_hippotrack();

        $quba = \question_engine::make_questions_usage_by_activity('mod_hippotrack', $hippotrackobj->get_context());
        $quba->set_preferred_behaviour($hippotrackobj->get_hippotrack()->preferredbehaviour);

        $timenow = time();
        $attempt = hippotrack_create_attempt($hippotrackobj, 1, false, $timenow);
        hippotrack_start_new_attempt($hippotrackobj, $quba, $attempt, 1, $timenow);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        hippotrack_attempt_save_started($hippotrackobj, $quba, $attempt);
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_hippotrack\event\attempt_started', $event);
        $this->assertEquals('hippotrack_attempts', $event->objecttable);
        $this->assertEquals($attempt->id, $event->objectid);
        $this->assertEquals($attempt->userid, $event->relateduserid);
        $this->assertEquals($hippotrackobj->get_context(), $event->get_context());
        $this->assertEquals('hippotrack_attempt_started', $event->get_legacy_eventname());
        $this->assertEquals(\context_module::instance($hippotrackobj->get_cmid()), $event->get_context());
        // Check legacy log data.
        $expected = array($hippotrackobj->get_courseid(), 'hippotrack', 'attempt', 'review.php?attempt=' . $attempt->id,
            $hippotrackobj->get_hippotrackid(), $hippotrackobj->get_cmid());
        $this->assertEventLegacyLogData($expected, $event);
        // Check legacy event data.
        $legacydata = new \stdClass();
        $legacydata->component = 'mod_hippotrack';
        $legacydata->attemptid = $attempt->id;
        $legacydata->timestart = $attempt->timestart;
        $legacydata->timestamp = $attempt->timestart;
        $legacydata->userid = $attempt->userid;
        $legacydata->hippotrackid = $hippotrackobj->get_hippotrackid();
        $legacydata->cmid = $hippotrackobj->get_cmid();
        $legacydata->courseid = $hippotrackobj->get_courseid();
        $this->assertEventLegacyData($legacydata, $event);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the attempt question restarted event.
     *
     * There is no external API for replacing a question, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_attempt_question_restarted() {
        list($hippotrackobj, $quba, $attempt) = $this->prepare_hippotrack_data();

        $params = [
            'objectid' => 1,
            'relateduserid' => 2,
            'courseid' => $hippotrackobj->get_courseid(),
            'context' => \context_module::instance($hippotrackobj->get_cmid()),
            'other' => [
                'hippotrackid' => $hippotrackobj->get_hippotrackid(),
                'page' => 2,
                'slot' => 3,
                'newquestionid' => 2
            ]
        ];
        $event = \mod_hippotrack\event\attempt_question_restarted::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_hippotrack\event\attempt_question_restarted', $event);
        $this->assertEquals(\context_module::instance($hippotrackobj->get_cmid()), $event->get_context());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the attempt updated event.
     *
     * There is no external API for updating an attempt, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_attempt_updated() {
        list($hippotrackobj, $quba, $attempt) = $this->prepare_hippotrack_data();

        $params = [
            'objectid' => 1,
            'relateduserid' => 2,
            'courseid' => $hippotrackobj->get_courseid(),
            'context' => \context_module::instance($hippotrackobj->get_cmid()),
            'other' => [
                'hippotrackid' => $hippotrackobj->get_hippotrackid(),
                'page' => 0
            ]
        ];
        $event = \mod_hippotrack\event\attempt_updated::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_hippotrack\event\attempt_updated', $event);
        $this->assertEquals(\context_module::instance($hippotrackobj->get_cmid()), $event->get_context());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the attempt auto-saved event.
     *
     * There is no external API for auto-saving an attempt, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_attempt_autosaved() {
        list($hippotrackobj, $quba, $attempt) = $this->prepare_hippotrack_data();

        $params = [
            'objectid' => 1,
            'relateduserid' => 2,
            'courseid' => $hippotrackobj->get_courseid(),
            'context' => \context_module::instance($hippotrackobj->get_cmid()),
            'other' => [
                'hippotrackid' => $hippotrackobj->get_hippotrackid(),
                'page' => 0
            ]
        ];

        $event = \mod_hippotrack\event\attempt_autosaved::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_hippotrack\event\attempt_autosaved', $event);
        $this->assertEquals(\context_module::instance($hippotrackobj->get_cmid()), $event->get_context());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the edit page viewed event.
     *
     * There is no external API for updating a hippotrack, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_edit_page_viewed() {
        $this->resetAfterTest();

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $hippotrack = $this->getDataGenerator()->create_module('hippotrack', array('course' => $course->id));

        $params = array(
            'courseid' => $course->id,
            'context' => \context_module::instance($hippotrack->cmid),
            'other' => array(
                'hippotrackid' => $hippotrack->id
            )
        );
        $event = \mod_hippotrack\event\edit_page_viewed::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_hippotrack\event\edit_page_viewed', $event);
        $this->assertEquals(\context_module::instance($hippotrack->cmid), $event->get_context());
        $expected = array($course->id, 'hippotrack', 'editquestions', 'view.php?id=' . $hippotrack->cmid, $hippotrack->id, $hippotrack->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the attempt deleted event.
     */
    public function test_attempt_deleted() {
        list($hippotrackobj, $quba, $attempt) = $this->prepare_hippotrack_data();

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        hippotrack_delete_attempt($attempt, $hippotrackobj->get_hippotrack());
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_hippotrack\event\attempt_deleted', $event);
        $this->assertEquals(\context_module::instance($hippotrackobj->get_cmid()), $event->get_context());
        $expected = array($hippotrackobj->get_courseid(), 'hippotrack', 'delete attempt', 'report.php?id=' . $hippotrackobj->get_cmid(),
            $attempt->id, $hippotrackobj->get_cmid());
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test that preview attempt deletions are not logged.
     */
    public function test_preview_attempt_deleted() {
        // Create hippotrack with preview attempt.
        list($hippotrackobj, $quba, $previewattempt) = $this->prepare_hippotrack_data(true);

        // Delete a preview attempt, capturing events.
        $sink = $this->redirectEvents();
        hippotrack_delete_attempt($previewattempt, $hippotrackobj->get_hippotrack());

        // Verify that no events were generated.
        $this->assertEmpty($sink->get_events());
    }

    /**
     * Test the report viewed event.
     *
     * There is no external API for viewing reports, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_report_viewed() {
        $this->resetAfterTest();

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $hippotrack = $this->getDataGenerator()->create_module('hippotrack', array('course' => $course->id));

        $params = array(
            'context' => $context = \context_module::instance($hippotrack->cmid),
            'other' => array(
                'hippotrackid' => $hippotrack->id,
                'reportname' => 'overview'
            )
        );
        $event = \mod_hippotrack\event\report_viewed::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_hippotrack\event\report_viewed', $event);
        $this->assertEquals(\context_module::instance($hippotrack->cmid), $event->get_context());
        $expected = array($course->id, 'hippotrack', 'report', 'report.php?id=' . $hippotrack->cmid . '&mode=overview',
            $hippotrack->id, $hippotrack->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the attempt reviewed event.
     *
     * There is no external API for reviewing attempts, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_attempt_reviewed() {
        $this->resetAfterTest();

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $hippotrack = $this->getDataGenerator()->create_module('hippotrack', array('course' => $course->id));

        $params = array(
            'objectid' => 1,
            'relateduserid' => 2,
            'courseid' => $course->id,
            'context' => \context_module::instance($hippotrack->cmid),
            'other' => array(
                'hippotrackid' => $hippotrack->id
            )
        );
        $event = \mod_hippotrack\event\attempt_reviewed::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_hippotrack\event\attempt_reviewed', $event);
        $this->assertEquals(\context_module::instance($hippotrack->cmid), $event->get_context());
        $expected = array($course->id, 'hippotrack', 'review', 'review.php?attempt=1', $hippotrack->id, $hippotrack->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the attempt summary viewed event.
     *
     * There is no external API for viewing the attempt summary, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_attempt_summary_viewed() {
        $this->resetAfterTest();

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $hippotrack = $this->getDataGenerator()->create_module('hippotrack', array('course' => $course->id));

        $params = array(
            'objectid' => 1,
            'relateduserid' => 2,
            'courseid' => $course->id,
            'context' => \context_module::instance($hippotrack->cmid),
            'other' => array(
                'hippotrackid' => $hippotrack->id
            )
        );
        $event = \mod_hippotrack\event\attempt_summary_viewed::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_hippotrack\event\attempt_summary_viewed', $event);
        $this->assertEquals(\context_module::instance($hippotrack->cmid), $event->get_context());
        $expected = array($course->id, 'hippotrack', 'view summary', 'summary.php?attempt=1', $hippotrack->id, $hippotrack->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the user override created event.
     *
     * There is no external API for creating a user override, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_user_override_created() {
        $this->resetAfterTest();

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $hippotrack = $this->getDataGenerator()->create_module('hippotrack', array('course' => $course->id));

        $params = array(
            'objectid' => 1,
            'relateduserid' => 2,
            'context' => \context_module::instance($hippotrack->cmid),
            'other' => array(
                'hippotrackid' => $hippotrack->id
            )
        );
        $event = \mod_hippotrack\event\user_override_created::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_hippotrack\event\user_override_created', $event);
        $this->assertEquals(\context_module::instance($hippotrack->cmid), $event->get_context());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the group override created event.
     *
     * There is no external API for creating a group override, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_group_override_created() {
        $this->resetAfterTest();

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $hippotrack = $this->getDataGenerator()->create_module('hippotrack', array('course' => $course->id));

        $params = array(
            'objectid' => 1,
            'context' => \context_module::instance($hippotrack->cmid),
            'other' => array(
                'hippotrackid' => $hippotrack->id,
                'groupid' => 2
            )
        );
        $event = \mod_hippotrack\event\group_override_created::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_hippotrack\event\group_override_created', $event);
        $this->assertEquals(\context_module::instance($hippotrack->cmid), $event->get_context());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the user override updated event.
     *
     * There is no external API for updating a user override, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_user_override_updated() {
        $this->resetAfterTest();

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $hippotrack = $this->getDataGenerator()->create_module('hippotrack', array('course' => $course->id));

        $params = array(
            'objectid' => 1,
            'relateduserid' => 2,
            'context' => \context_module::instance($hippotrack->cmid),
            'other' => array(
                'hippotrackid' => $hippotrack->id
            )
        );
        $event = \mod_hippotrack\event\user_override_updated::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_hippotrack\event\user_override_updated', $event);
        $this->assertEquals(\context_module::instance($hippotrack->cmid), $event->get_context());
        $expected = array($course->id, 'hippotrack', 'edit override', 'overrideedit.php?id=1', $hippotrack->id, $hippotrack->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the group override updated event.
     *
     * There is no external API for updating a group override, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_group_override_updated() {
        $this->resetAfterTest();

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $hippotrack = $this->getDataGenerator()->create_module('hippotrack', array('course' => $course->id));

        $params = array(
            'objectid' => 1,
            'context' => \context_module::instance($hippotrack->cmid),
            'other' => array(
                'hippotrackid' => $hippotrack->id,
                'groupid' => 2
            )
        );
        $event = \mod_hippotrack\event\group_override_updated::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_hippotrack\event\group_override_updated', $event);
        $this->assertEquals(\context_module::instance($hippotrack->cmid), $event->get_context());
        $expected = array($course->id, 'hippotrack', 'edit override', 'overrideedit.php?id=1', $hippotrack->id, $hippotrack->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the user override deleted event.
     */
    public function test_user_override_deleted() {
        global $DB;

        $this->resetAfterTest();

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $hippotrack = $this->getDataGenerator()->create_module('hippotrack', array('course' => $course->id));

        // Create an override.
        $override = new \stdClass();
        $override->hippotrack = $hippotrack->id;
        $override->userid = 2;
        $override->id = $DB->insert_record('hippotrack_overrides', $override);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        hippotrack_delete_override($hippotrack, $override->id);
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_hippotrack\event\user_override_deleted', $event);
        $this->assertEquals(\context_module::instance($hippotrack->cmid), $event->get_context());
        $expected = array($course->id, 'hippotrack', 'delete override', 'overrides.php?cmid=' . $hippotrack->cmid, $hippotrack->id, $hippotrack->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the group override deleted event.
     */
    public function test_group_override_deleted() {
        global $DB;

        $this->resetAfterTest();

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $hippotrack = $this->getDataGenerator()->create_module('hippotrack', array('course' => $course->id));

        // Create an override.
        $override = new \stdClass();
        $override->hippotrack = $hippotrack->id;
        $override->groupid = 2;
        $override->id = $DB->insert_record('hippotrack_overrides', $override);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        hippotrack_delete_override($hippotrack, $override->id);
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_hippotrack\event\group_override_deleted', $event);
        $this->assertEquals(\context_module::instance($hippotrack->cmid), $event->get_context());
        $expected = array($course->id, 'hippotrack', 'delete override', 'overrides.php?cmid=' . $hippotrack->cmid, $hippotrack->id, $hippotrack->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the attempt viewed event.
     *
     * There is no external API for continuing an attempt, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_attempt_viewed() {
        $this->resetAfterTest();

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $hippotrack = $this->getDataGenerator()->create_module('hippotrack', array('course' => $course->id));

        $params = array(
            'objectid' => 1,
            'relateduserid' => 2,
            'courseid' => $course->id,
            'context' => \context_module::instance($hippotrack->cmid),
            'other' => array(
                'hippotrackid' => $hippotrack->id,
                'page' => 0
            )
        );
        $event = \mod_hippotrack\event\attempt_viewed::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_hippotrack\event\attempt_viewed', $event);
        $this->assertEquals(\context_module::instance($hippotrack->cmid), $event->get_context());
        $expected = array($course->id, 'hippotrack', 'continue attempt', 'review.php?attempt=1', $hippotrack->id, $hippotrack->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the attempt previewed event.
     */
    public function test_attempt_preview_started() {
        $hippotrackobj = $this->prepare_hippotrack();

        $quba = \question_engine::make_questions_usage_by_activity('mod_hippotrack', $hippotrackobj->get_context());
        $quba->set_preferred_behaviour($hippotrackobj->get_hippotrack()->preferredbehaviour);

        $timenow = time();
        $attempt = hippotrack_create_attempt($hippotrackobj, 1, false, $timenow, true);
        hippotrack_start_new_attempt($hippotrackobj, $quba, $attempt, 1, $timenow);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        hippotrack_attempt_save_started($hippotrackobj, $quba, $attempt);
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_hippotrack\event\attempt_preview_started', $event);
        $this->assertEquals(\context_module::instance($hippotrackobj->get_cmid()), $event->get_context());
        $expected = array($hippotrackobj->get_courseid(), 'hippotrack', 'preview', 'view.php?id=' . $hippotrackobj->get_cmid(),
            $hippotrackobj->get_hippotrackid(), $hippotrackobj->get_cmid());
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the question manually graded event.
     *
     * There is no external API for manually grading a question, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_question_manually_graded() {
        list($hippotrackobj, $quba, $attempt) = $this->prepare_hippotrack_data();

        $params = array(
            'objectid' => 1,
            'courseid' => $hippotrackobj->get_courseid(),
            'context' => \context_module::instance($hippotrackobj->get_cmid()),
            'other' => array(
                'hippotrackid' => $hippotrackobj->get_hippotrackid(),
                'attemptid' => 2,
                'slot' => 3
            )
        );
        $event = \mod_hippotrack\event\question_manually_graded::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_hippotrack\event\question_manually_graded', $event);
        $this->assertEquals(\context_module::instance($hippotrackobj->get_cmid()), $event->get_context());
        $expected = array($hippotrackobj->get_courseid(), 'hippotrack', 'manualgrade', 'comment.php?attempt=2&slot=3',
            $hippotrackobj->get_hippotrackid(), $hippotrackobj->get_cmid());
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the attempt regraded event.
     *
     * There is no external API for regrading attempts, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_attempt_regraded() {
      $this->resetAfterTest();

      $this->setAdminUser();
      $course = $this->getDataGenerator()->create_course();
      $hippotrack = $this->getDataGenerator()->create_module('hippotrack', array('course' => $course->id));

      $params = array(
        'objectid' => 1,
        'relateduserid' => 2,
        'courseid' => $course->id,
        'context' => \context_module::instance($hippotrack->cmid),
        'other' => array(
          'hippotrackid' => $hippotrack->id
        )
      );
      $event = \mod_hippotrack\event\attempt_regraded::create($params);

      // Trigger and capture the event.
      $sink = $this->redirectEvents();
      $event->trigger();
      $events = $sink->get_events();
      $event = reset($events);

      // Check that the event data is valid.
      $this->assertInstanceOf('\mod_hippotrack\event\attempt_regraded', $event);
      $this->assertEquals(\context_module::instance($hippotrack->cmid), $event->get_context());
      $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the attempt notify manual graded event.
     * There is no external API for notification email when manual grading of user's attempt is completed,
     * so the unit test will simply create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_attempt_manual_grading_completed() {
        $this->resetAfterTest();
        list($hippotrackobj, $quba, $attempt) = $this->prepare_hippotrack_data();
        $attemptobj = hippotrack_attempt::create($attempt->id);

        $params = [
            'objectid' => $attemptobj->get_attemptid(),
            'relateduserid' => $attemptobj->get_userid(),
            'courseid' => $attemptobj->get_course()->id,
            'context' => \context_module::instance($attemptobj->get_cmid()),
            'other' => [
                'hippotrackid' => $attemptobj->get_hippotrackid()
            ]
        ];
        $event = \mod_hippotrack\event\attempt_manual_grading_completed::create($params);

        // Catch the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $sink->close();

        // Validate the event.
        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertInstanceOf('\mod_hippotrack\event\attempt_manual_grading_completed', $event);
        $this->assertEquals('hippotrack_attempts', $event->objecttable);
        $this->assertEquals($hippotrackobj->get_context(), $event->get_context());
        $this->assertEquals($attempt->userid, $event->relateduserid);
        $this->assertNotEmpty($event->get_description());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the page break created event.
     *
     * There is no external API for creating page break, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_page_break_created() {
        $hippotrackobj = $this->prepare_hippotrack();

        $params = [
            'objectid' => 1,
            'context' => context_module::instance($hippotrackobj->get_cmid()),
            'other' => [
                'hippotrackid' => $hippotrackobj->get_hippotrackid(),
                'slotnumber' => 3,
            ]
        ];
        $event = \mod_hippotrack\event\page_break_created::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_hippotrack\event\page_break_created', $event);
        $this->assertEquals(context_module::instance($hippotrackobj->get_cmid()), $event->get_context());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the page break deleted event.
     *
     * There is no external API for deleting page break, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_page_deleted_created() {
        $hippotrackobj = $this->prepare_hippotrack();

        $params = [
            'objectid' => 1,
            'context' => context_module::instance($hippotrackobj->get_cmid()),
            'other' => [
                'hippotrackid' => $hippotrackobj->get_hippotrackid(),
                'slotnumber' => 3,
            ]
        ];
        $event = \mod_hippotrack\event\page_break_deleted::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_hippotrack\event\page_break_deleted', $event);
        $this->assertEquals(context_module::instance($hippotrackobj->get_cmid()), $event->get_context());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the hippotrack grade updated event.
     *
     * There is no external API for updating hippotrack grade, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_hippotrack_grade_updated() {
        $hippotrackobj = $this->prepare_hippotrack();

        $params = [
            'objectid' => $hippotrackobj->get_hippotrackid(),
            'context' => context_module::instance($hippotrackobj->get_cmid()),
            'other' => [
                'oldgrade' => 1,
                'newgrade' => 3,
            ]
        ];
        $event = \mod_hippotrack\event\hippotrack_grade_updated::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_hippotrack\event\hippotrack_grade_updated', $event);
        $this->assertEquals(context_module::instance($hippotrackobj->get_cmid()), $event->get_context());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the hippotrack re-paginated event.
     *
     * There is no external API for re-paginating hippotrack, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_hippotrack_repaginated() {
        $hippotrackobj = $this->prepare_hippotrack();

        $params = [
            'objectid' => $hippotrackobj->get_hippotrackid(),
            'context' => context_module::instance($hippotrackobj->get_cmid()),
            'other' => [
                'slotsperpage' => 3,
            ]
        ];
        $event = \mod_hippotrack\event\hippotrack_repaginated::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_hippotrack\event\hippotrack_repaginated', $event);
        $this->assertEquals(context_module::instance($hippotrackobj->get_cmid()), $event->get_context());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the section break created event.
     *
     * There is no external API for creating section break, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_section_break_created() {
        $hippotrackobj = $this->prepare_hippotrack();

        $params = [
            'objectid' => 1,
            'context' => context_module::instance($hippotrackobj->get_cmid()),
            'other' => [
                'hippotrackid' => $hippotrackobj->get_hippotrackid(),
                'firstslotid' => 1,
                'firstslotnumber' => 2,
                'title' => 'New title'
            ]
        ];
        $event = \mod_hippotrack\event\section_break_created::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_hippotrack\event\section_break_created', $event);
        $this->assertEquals(context_module::instance($hippotrackobj->get_cmid()), $event->get_context());
        $this->assertStringContainsString($params['other']['title'], $event->get_description());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the section break deleted event.
     *
     * There is no external API for deleting section break, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_section_break_deleted() {
        $hippotrackobj = $this->prepare_hippotrack();

        $params = [
            'objectid' => 1,
            'context' => context_module::instance($hippotrackobj->get_cmid()),
            'other' => [
                'hippotrackid' => $hippotrackobj->get_hippotrackid(),
                'firstslotid' => 1,
                'firstslotnumber' => 2
            ]
        ];
        $event = \mod_hippotrack\event\section_break_deleted::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_hippotrack\event\section_break_deleted', $event);
        $this->assertEquals(context_module::instance($hippotrackobj->get_cmid()), $event->get_context());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the section shuffle updated event.
     *
     * There is no external API for updating section shuffle, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_section_shuffle_updated() {
        $hippotrackobj = $this->prepare_hippotrack();

        $params = [
            'objectid' => 1,
            'context' => context_module::instance($hippotrackobj->get_cmid()),
            'other' => [
                'hippotrackid' => $hippotrackobj->get_hippotrackid(),
                'firstslotnumber' => 2,
                'shuffle' => true
            ]
        ];
        $event = \mod_hippotrack\event\section_shuffle_updated::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_hippotrack\event\section_shuffle_updated', $event);
        $this->assertEquals(context_module::instance($hippotrackobj->get_cmid()), $event->get_context());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the section title updated event.
     *
     * There is no external API for updating section title, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_section_title_updated() {
        $hippotrackobj = $this->prepare_hippotrack();

        $params = [
            'objectid' => 1,
            'context' => context_module::instance($hippotrackobj->get_cmid()),
            'other' => [
                'hippotrackid' => $hippotrackobj->get_hippotrackid(),
                'firstslotid' => 1,
                'firstslotnumber' => 2,
                'newtitle' => 'New title'
            ]
        ];
        $event = \mod_hippotrack\event\section_title_updated::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_hippotrack\event\section_title_updated', $event);
        $this->assertEquals(context_module::instance($hippotrackobj->get_cmid()), $event->get_context());
        $this->assertStringContainsString($params['other']['newtitle'], $event->get_description());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the slot created event.
     *
     * There is no external API for creating slot, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_slot_created() {
        $hippotrackobj = $this->prepare_hippotrack();

        $params = [
            'objectid' => 1,
            'context' => context_module::instance($hippotrackobj->get_cmid()),
            'other' => [
                'hippotrackid' => $hippotrackobj->get_hippotrackid(),
                'slotnumber' => 1,
                'page' => 1
            ]
        ];
        $event = \mod_hippotrack\event\slot_created::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_hippotrack\event\slot_created', $event);
        $this->assertEquals(context_module::instance($hippotrackobj->get_cmid()), $event->get_context());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the slot deleted event.
     *
     * There is no external API for deleting slot, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_slot_deleted() {
        $hippotrackobj = $this->prepare_hippotrack();

        $params = [
            'objectid' => 1,
            'context' => context_module::instance($hippotrackobj->get_cmid()),
            'other' => [
                'hippotrackid' => $hippotrackobj->get_hippotrackid(),
                'slotnumber' => 1,
            ]
        ];
        $event = \mod_hippotrack\event\slot_deleted::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_hippotrack\event\slot_deleted', $event);
        $this->assertEquals(context_module::instance($hippotrackobj->get_cmid()), $event->get_context());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the slot mark updated event.
     *
     * There is no external API for updating slot mark, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_slot_mark_updated() {
        $hippotrackobj = $this->prepare_hippotrack();

        $params = [
            'objectid' => 1,
            'context' => context_module::instance($hippotrackobj->get_cmid()),
            'other' => [
                'hippotrackid' => $hippotrackobj->get_hippotrackid(),
                'previousmaxmark' => 1,
                'newmaxmark' => 2,
            ]
        ];
        $event = \mod_hippotrack\event\slot_mark_updated::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_hippotrack\event\slot_mark_updated', $event);
        $this->assertEquals(context_module::instance($hippotrackobj->get_cmid()), $event->get_context());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the slot moved event.
     *
     * There is no external API for moving slot, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_slot_moved() {
        $hippotrackobj = $this->prepare_hippotrack();

        $params = [
            'objectid' => 1,
            'context' => context_module::instance($hippotrackobj->get_cmid()),
            'other' => [
                'hippotrackid' => $hippotrackobj->get_hippotrackid(),
                'previousslotnumber' => 1,
                'afterslotnumber' => 2,
                'page' => 1
            ]
        ];
        $event = \mod_hippotrack\event\slot_moved::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_hippotrack\event\slot_moved', $event);
        $this->assertEquals(context_module::instance($hippotrackobj->get_cmid()), $event->get_context());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test the slot require previous updated event.
     *
     * There is no external API for updating slot require previous option, so the unit test will simply
     * create and trigger the event and ensure the event data is returned as expected.
     */
    public function test_slot_requireprevious_updated() {
        $hippotrackobj = $this->prepare_hippotrack();

        $params = [
            'objectid' => 1,
            'context' => context_module::instance($hippotrackobj->get_cmid()),
            'other' => [
                'hippotrackid' => $hippotrackobj->get_hippotrackid(),
                'requireprevious' => true
            ]
        ];
        $event = \mod_hippotrack\event\slot_requireprevious_updated::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        // Check that the event data is valid.
        $this->assertInstanceOf('\mod_hippotrack\event\slot_requireprevious_updated', $event);
        $this->assertEquals(context_module::instance($hippotrackobj->get_cmid()), $event->get_context());
        $this->assertEventContextNotUsed($event);
    }
}
