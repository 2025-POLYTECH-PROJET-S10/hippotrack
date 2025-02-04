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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/hippotrack/lib.php');

/**
 * Unit tests for the calendar event modification callbacks used
 * for dragging and dropping hippotrack calendar events in the calendar
 * UI.
 *
 * @package    mod_hippotrack
 * @category   test
 * @copyright  2017 Ryan Wyllie <ryan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU Public License
 */
class calendar_event_modified_test extends \advanced_testcase {

    /**
     * Create an instance of the hippotrack activity.
     *
     * @param array $properties Properties to set on the activity
     * @return stdClass HippoTrack activity instance
     */
    protected function create_hippotrack_instance(array $properties) {
        global $DB;

        $generator = $this->getDataGenerator();

        if (empty($properties['course'])) {
            $course = $generator->create_course();
            $courseid = $course->id;
        } else {
            $courseid = $properties['course'];
        }

        $hippotrackgenerator = $generator->get_plugin_generator('mod_hippotrack');
        $hippotrack = $hippotrackgenerator->create_instance(array_merge(['course' => $courseid], $properties));

        if (isset($properties['timemodified'])) {
            // The generator overrides the timemodified value to set it as
            // the current time even if a value is provided so we need to
            // make sure it's set back to the requested value.
            $hippotrack->timemodified = $properties['timemodified'];
            $DB->update_record('hippotrack', $hippotrack);
        }

        return $hippotrack;
    }

    /**
     * Create a calendar event for a hippotrack activity instance.
     *
     * @param \stdClass $hippotrack The activity instance
     * @param array $eventproperties Properties to set on the calendar event
     * @return calendar_event
     */
    protected function create_hippotrack_calendar_event(\stdClass $hippotrack, array $eventproperties) {
        $defaultproperties = [
            'name' => 'Test event',
            'description' => '',
            'format' => 1,
            'courseid' => $hippotrack->course,
            'groupid' => 0,
            'userid' => 2,
            'modulename' => 'hippotrack',
            'instance' => $hippotrack->id,
            'eventtype' => HIPPOTRACK_EVENT_TYPE_OPEN,
            'timestart' => time(),
            'timeduration' => 86400,
            'visible' => 1
        ];

        return new \calendar_event(array_merge($defaultproperties, $eventproperties));
    }

    /**
     * An unkown event type should not change the hippotrack instance.
     */
    public function test_mod_hippotrack_core_calendar_event_timestart_updated_unknown_event() {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $timeopen = time();
        $timeclose = $timeopen + DAYSECS;
        $hippotrack = $this->create_hippotrack_instance(['timeopen' => $timeopen, 'timeclose' => $timeclose]);
        $event = $this->create_hippotrack_calendar_event($hippotrack, [
            'eventtype' => HIPPOTRACK_EVENT_TYPE_OPEN . "SOMETHING ELSE",
            'timestart' => 1
        ]);

        mod_hippotrack_core_calendar_event_timestart_updated($event, $hippotrack);

        $hippotrack = $DB->get_record('hippotrack', ['id' => $hippotrack->id]);
        $this->assertEquals($timeopen, $hippotrack->timeopen);
        $this->assertEquals($timeclose, $hippotrack->timeclose);
    }

    /**
     * A HIPPOTRACK_EVENT_TYPE_OPEN event should update the timeopen property of
     * the hippotrack activity.
     */
    public function test_mod_hippotrack_core_calendar_event_timestart_updated_open_event() {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $timeopen = time();
        $timeclose = $timeopen + DAYSECS;
        $timemodified = 1;
        $newtimeopen = $timeopen - DAYSECS;
        $hippotrack = $this->create_hippotrack_instance([
            'timeopen' => $timeopen,
            'timeclose' => $timeclose,
            'timemodified' => $timemodified
        ]);
        $event = $this->create_hippotrack_calendar_event($hippotrack, [
            'eventtype' => HIPPOTRACK_EVENT_TYPE_OPEN,
            'timestart' => $newtimeopen
        ]);

        mod_hippotrack_core_calendar_event_timestart_updated($event, $hippotrack);

        $hippotrack = $DB->get_record('hippotrack', ['id' => $hippotrack->id]);
        // Ensure the timeopen property matches the event timestart.
        $this->assertEquals($newtimeopen, $hippotrack->timeopen);
        // Ensure the timeclose isn't changed.
        $this->assertEquals($timeclose, $hippotrack->timeclose);
        // Ensure the timemodified property has been changed.
        $this->assertNotEquals($timemodified, $hippotrack->timemodified);
    }

    /**
     * A HIPPOTRACK_EVENT_TYPE_CLOSE event should update the timeclose property of
     * the hippotrack activity.
     */
    public function test_mod_hippotrack_core_calendar_event_timestart_updated_close_event() {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $timeopen = time();
        $timeclose = $timeopen + DAYSECS;
        $timemodified = 1;
        $newtimeclose = $timeclose + DAYSECS;
        $hippotrack = $this->create_hippotrack_instance([
            'timeopen' => $timeopen,
            'timeclose' => $timeclose,
            'timemodified' => $timemodified
        ]);
        $event = $this->create_hippotrack_calendar_event($hippotrack, [
            'eventtype' => HIPPOTRACK_EVENT_TYPE_CLOSE,
            'timestart' => $newtimeclose
        ]);

        mod_hippotrack_core_calendar_event_timestart_updated($event, $hippotrack);

        $hippotrack = $DB->get_record('hippotrack', ['id' => $hippotrack->id]);
        // Ensure the timeclose property matches the event timestart.
        $this->assertEquals($newtimeclose, $hippotrack->timeclose);
        // Ensure the timeopen isn't changed.
        $this->assertEquals($timeopen, $hippotrack->timeopen);
        // Ensure the timemodified property has been changed.
        $this->assertNotEquals($timemodified, $hippotrack->timemodified);
    }

    /**
     * A HIPPOTRACK_EVENT_TYPE_OPEN event should not update the timeopen property of
     * the hippotrack activity if it's an override.
     */
    public function test_mod_hippotrack_core_calendar_event_timestart_updated_open_event_override() {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $user = $this->getDataGenerator()->create_user();
        $timeopen = time();
        $timeclose = $timeopen + DAYSECS;
        $timemodified = 1;
        $newtimeopen = $timeopen - DAYSECS;
        $hippotrack = $this->create_hippotrack_instance([
            'timeopen' => $timeopen,
            'timeclose' => $timeclose,
            'timemodified' => $timemodified
        ]);
        $event = $this->create_hippotrack_calendar_event($hippotrack, [
            'userid' => $user->id,
            'eventtype' => HIPPOTRACK_EVENT_TYPE_OPEN,
            'timestart' => $newtimeopen
        ]);
        $record = (object) [
            'hippotrack' => $hippotrack->id,
            'userid' => $user->id
        ];

        $DB->insert_record('hippotrack_overrides', $record);

        mod_hippotrack_core_calendar_event_timestart_updated($event, $hippotrack);

        $hippotrack = $DB->get_record('hippotrack', ['id' => $hippotrack->id]);
        // Ensure the timeopen property doesn't change.
        $this->assertEquals($timeopen, $hippotrack->timeopen);
        // Ensure the timeclose isn't changed.
        $this->assertEquals($timeclose, $hippotrack->timeclose);
        // Ensure the timemodified property has not been changed.
        $this->assertEquals($timemodified, $hippotrack->timemodified);
    }

    /**
     * If a student somehow finds a way to update the hippotrack calendar event
     * then the callback should not update the hippotrack activity otherwise that
     * would be a security issue.
     */
    public function test_student_role_cant_update_hippotrack_activity() {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $user = $generator->create_user();
        $course = $generator->create_course();
        $context = \context_course::instance($course->id);
        $roleid = $generator->create_role();
        $now = time();
        $timeopen = (new \DateTime())->setTimestamp($now);
        $newtimeopen = (new \DateTime())->setTimestamp($now)->modify('+1 day');
        $hippotrack = $this->create_hippotrack_instance([
            'course' => $course->id,
            'timeopen' => $timeopen->getTimestamp()
        ]);

        $generator->enrol_user($user->id, $course->id, 'student');
        $generator->role_assign($roleid, $user->id, $context->id);

        $event = $this->create_hippotrack_calendar_event($hippotrack, [
            'eventtype' => HIPPOTRACK_EVENT_TYPE_OPEN,
            'timestart' => $timeopen->getTimestamp()
        ]);

        assign_capability('moodle/course:manageactivities', CAP_PROHIBIT, $roleid, $context, true);

        $this->setUser($user);

        mod_hippotrack_core_calendar_event_timestart_updated($event, $hippotrack);

        $newhippotrack = $DB->get_record('hippotrack', ['id' => $hippotrack->id]);
        // The time open shouldn't have changed even though we updated the calendar
        // event.
        $this->assertEquals($timeopen->getTimestamp(), $newhippotrack->timeopen);
    }

    /**
     * A teacher with the capability to modify a hippotrack module should be
     * able to update the hippotrack activity dates by changing the calendar
     * event.
     */
    public function test_teacher_role_can_update_hippotrack_activity() {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $user = $generator->create_user();
        $course = $generator->create_course();
        $context = \context_course::instance($course->id);
        $roleid = $generator->create_role();
        $now = time();
        $timeopen = (new \DateTime())->setTimestamp($now);
        $newtimeopen = (new \DateTime())->setTimestamp($now)->modify('+1 day');
        $hippotrack = $this->create_hippotrack_instance([
            'course' => $course->id,
            'timeopen' => $timeopen->getTimestamp()
        ]);

        $generator->enrol_user($user->id, $course->id, 'teacher');
        $generator->role_assign($roleid, $user->id, $context->id);

        $event = $this->create_hippotrack_calendar_event($hippotrack, [
            'eventtype' => HIPPOTRACK_EVENT_TYPE_OPEN,
            'timestart' => $newtimeopen->getTimestamp()
        ]);

        assign_capability('moodle/course:manageactivities', CAP_ALLOW, $roleid, $context, true);

        $this->setUser($user);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();

        mod_hippotrack_core_calendar_event_timestart_updated($event, $hippotrack);

        $triggeredevents = $sink->get_events();
        $moduleupdatedevents = array_filter($triggeredevents, function($e) {
            return is_a($e, 'core\event\course_module_updated');
        });

        $newhippotrack = $DB->get_record('hippotrack', ['id' => $hippotrack->id]);
        // The should be updated along with the event because the user has sufficient
        // capabilities.
        $this->assertEquals($newtimeopen->getTimestamp(), $newhippotrack->timeopen);
        // Confirm that a module updated event is fired when the module
        // is changed.
        $this->assertNotEmpty($moduleupdatedevents);
    }


    /**
     * An unkown event type should not have any limits
     */
    public function test_mod_hippotrack_core_calendar_get_valid_event_timestart_range_unknown_event() {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $timeopen = time();
        $timeclose = $timeopen + DAYSECS;
        $hippotrack = $this->create_hippotrack_instance([
            'timeopen' => $timeopen,
            'timeclose' => $timeclose
        ]);
        $event = $this->create_hippotrack_calendar_event($hippotrack, [
            'eventtype' => HIPPOTRACK_EVENT_TYPE_OPEN . "SOMETHING ELSE",
            'timestart' => 1
        ]);

        list ($min, $max) = mod_hippotrack_core_calendar_get_valid_event_timestart_range($event, $hippotrack);
        $this->assertNull($min);
        $this->assertNull($max);
    }

    /**
     * The open event should be limited by the hippotrack's timeclose property, if it's set.
     */
    public function test_mod_hippotrack_core_calendar_get_valid_event_timestart_range_open_event() {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $timeopen = time();
        $timeclose = $timeopen + DAYSECS;
        $hippotrack = $this->create_hippotrack_instance([
            'timeopen' => $timeopen,
            'timeclose' => $timeclose
        ]);
        $event = $this->create_hippotrack_calendar_event($hippotrack, [
            'eventtype' => HIPPOTRACK_EVENT_TYPE_OPEN,
            'timestart' => 1
        ]);

        // The max limit should be bounded by the timeclose value.
        list ($min, $max) = mod_hippotrack_core_calendar_get_valid_event_timestart_range($event, $hippotrack);

        $this->assertNull($min);
        $this->assertEquals($timeclose, $max[0]);

        // No timeclose value should result in no upper limit.
        $hippotrack->timeclose = 0;
        list ($min, $max) = mod_hippotrack_core_calendar_get_valid_event_timestart_range($event, $hippotrack);

        $this->assertNull($min);
        $this->assertNull($max);
    }

    /**
     * An override event should not have any limits.
     */
    public function test_mod_hippotrack_core_calendar_get_valid_event_timestart_range_override_event() {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $user = $generator->create_user();
        $course = $generator->create_course();
        $timeopen = time();
        $timeclose = $timeopen + DAYSECS;
        $hippotrack = $this->create_hippotrack_instance([
            'course' => $course->id,
            'timeopen' => $timeopen,
            'timeclose' => $timeclose
        ]);
        $event = $this->create_hippotrack_calendar_event($hippotrack, [
            'userid' => $user->id,
            'eventtype' => HIPPOTRACK_EVENT_TYPE_OPEN,
            'timestart' => 1
        ]);
        $record = (object) [
            'hippotrack' => $hippotrack->id,
            'userid' => $user->id
        ];

        $DB->insert_record('hippotrack_overrides', $record);

        list ($min, $max) = mod_hippotrack_core_calendar_get_valid_event_timestart_range($event, $hippotrack);

        $this->assertFalse($min);
        $this->assertFalse($max);
    }

    /**
     * The close event should be limited by the hippotrack's timeopen property, if it's set.
     */
    public function test_mod_hippotrack_core_calendar_get_valid_event_timestart_range_close_event() {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $timeopen = time();
        $timeclose = $timeopen + DAYSECS;
        $hippotrack = $this->create_hippotrack_instance([
            'timeopen' => $timeopen,
            'timeclose' => $timeclose
        ]);
        $event = $this->create_hippotrack_calendar_event($hippotrack, [
            'eventtype' => HIPPOTRACK_EVENT_TYPE_CLOSE,
            'timestart' => 1,
        ]);

        // The max limit should be bounded by the timeclose value.
        list ($min, $max) = mod_hippotrack_core_calendar_get_valid_event_timestart_range($event, $hippotrack);

        $this->assertEquals($timeopen, $min[0]);
        $this->assertNull($max);

        // No timeclose value should result in no upper limit.
        $hippotrack->timeopen = 0;
        list ($min, $max) = mod_hippotrack_core_calendar_get_valid_event_timestart_range($event, $hippotrack);

        $this->assertNull($min);
        $this->assertNull($max);
    }

    /**
     * When the close date event is changed and it results in the time close value of
     * the hippotrack being updated then the open hippotrack attempts should also be updated.
     */
    public function test_core_calendar_event_timestart_updated_update_hippotrack_attempt() {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $teacher = $generator->create_user();
        $student = $generator->create_user();
        $course = $generator->create_course();
        $context = \context_course::instance($course->id);
        $roleid = $generator->create_role();
        $now = time();
        $timelimit = 600;
        $timeopen = (new \DateTime())->setTimestamp($now);
        $timeclose = (new \DateTime())->setTimestamp($now)->modify('+1 day');
        // The new close time being earlier than the time open + time limit should
        // result in an update to the hippotrack attempts.
        $newtimeclose = $timeopen->getTimestamp() + $timelimit - 10;
        $hippotrack = $this->create_hippotrack_instance([
            'course' => $course->id,
            'timeopen' => $timeopen->getTimestamp(),
            'timeclose' => $timeclose->getTimestamp(),
            'timelimit' => $timelimit
        ]);

        $generator->enrol_user($student->id, $course->id, 'student');
        $generator->enrol_user($teacher->id, $course->id, 'teacher');
        $generator->role_assign($roleid, $teacher->id, $context->id);

        $event = $this->create_hippotrack_calendar_event($hippotrack, [
            'eventtype' => HIPPOTRACK_EVENT_TYPE_CLOSE,
            'timestart' => $newtimeclose
        ]);

        assign_capability('moodle/course:manageactivities', CAP_ALLOW, $roleid, $context, true);

        $attemptid = $DB->insert_record(
            'hippotrack_attempts',
            [
                'hippotrack' => $hippotrack->id,
                'userid' => $student->id,
                'state' => 'inprogress',
                'timestart' => $timeopen->getTimestamp(),
                'timecheckstate' => 0,
                'layout' => '',
                'uniqueid' => 1
            ]
        );

        $this->setUser($teacher);

        mod_hippotrack_core_calendar_event_timestart_updated($event, $hippotrack);

        $hippotrack = $DB->get_record('hippotrack', ['id' => $hippotrack->id]);
        $attempt = $DB->get_record('hippotrack_attempts', ['id' => $attemptid]);
        // When the close date is changed so that it's earlier than the time open
        // plus the time limit of the hippotrack then the attempt's timecheckstate should
        // be updated to the new time close date of the hippotrack.
        $this->assertEquals($newtimeclose, $attempt->timecheckstate);
        $this->assertEquals($newtimeclose, $hippotrack->timeclose);
    }
}
