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
namespace hippotrack_statistics\tests;

use hippotrack_statistics\task\recalculate;

/**
 * Test methods for statistics recalculations
 *
 * @package   hippotrack_statistics
 * @copyright 2023 onwards Catalyst IT EU {@link https://catalyst-eu.net}
 * @author    Mark Johnson <mark.johnson@catalyst-eu.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait statistics_test_trait {
    /**
     * Return a user, and a hippotrack with 2 questions.
     *
     * @return array [$user, $hippotrack, $course]
     */
    protected function create_test_data(): array {
        $this->resetAfterTest(true);
        $generator = $this->getDataGenerator();
        $user = $generator->create_user();
        $course = $generator->create_course();
        $hippotrack = $this->create_test_hippotrack($course);
        $this->add_two_regular_questions($generator->get_plugin_generator('core_question'), $hippotrack);
        return [$user, $hippotrack, $course];
    }

    /**
     * Assert that a task is queued for a hippotrack.
     *
     * Check that the hippotrackid stored in the task's custom data matches the provided hippotrack,
     * and that the run time is in one hour from when the test is being run (within a small margin of error).
     *
     * @param recalculate $task
     * @param \stdClass $hippotrack
     * @return void
     */
    protected function assert_task_is_queued_for_hippotrack(recalculate $task, \stdClass $hippotrack): void {
        $data = $task->get_custom_data();
        $this->assertEquals($hippotrack->id, $data->hippotrackid);
        $this->assertEqualsWithDelta(time() + HOURSECS, $task->get_next_run_time(), 1);
    }
}
