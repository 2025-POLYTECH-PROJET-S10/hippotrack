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
namespace hippotrack_statistics\event\observer;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/hippotrack/tests/hippotrack_question_helper_test_trait.php');

use core\task\manager;
use hippotrack_statistics\task\recalculate;
use hippotrack_statistics\tests\statistics_helper;
use hippotrack_statistics\tests\statistics_test_trait;

/**
 * Unit tests for attempt_submitted observer
 *
 * @package   hippotrack_statistics
 * @copyright 2023 onwards Catalyst IT EU {@link https://catalyst-eu.net}
 * @author    Mark Johnson <mark.johnson@catalyst-eu.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \hippotrack_statistics\event\observer\attempt_submitted
 */
class attempt_submitted_test extends \advanced_testcase {
    use \hippotrack_question_helper_test_trait;
    use statistics_test_trait;


    /**
     * Attempting a hippotrack should queue the recalculation task for that hippotrack in 1 hour's time.
     *
     * @return void
     */
    public function test_queue_task_on_submission(): void {
        [$user, $hippotrack] = $this->create_test_data();

        $tasks = manager::get_adhoc_tasks(recalculate::class);
        $this->assertEmpty($tasks);

        $this->attempt_hippotrack($hippotrack, $user);

        $tasks = manager::get_adhoc_tasks(recalculate::class);
        $this->assertCount(1, $tasks);
        $task = reset($tasks);
        $this->assert_task_is_queued_for_hippotrack($task, $hippotrack);
    }

    /**
     * Attempting a hippotrack multiple times should only queue one instance of the task.
     *
     * @return void
     */
    public function test_queue_single_task_for_multiple_submissions(): void {
        [$user1, $hippotrack] = $this->create_test_data();
        $user2 = $this->getDataGenerator()->create_user();

        $tasks = manager::get_adhoc_tasks(recalculate::class);
        $this->assertEmpty($tasks);

        $this->attempt_hippotrack($hippotrack, $user1);
        $this->attempt_hippotrack($hippotrack, $user2);

        $tasks = manager::get_adhoc_tasks(recalculate::class);
        $this->assertCount(1, $tasks);
        $task = reset($tasks);
        $this->assert_task_is_queued_for_hippotrack($task, $hippotrack);
    }

    /**
     * Attempting the hippotrack again after processing the task should queue a new task.
     *
     * @return void
     */
    public function test_queue_new_task_after_processing(): void {
        [$user1, $hippotrack, $course] = $this->create_test_data();
        $user2 = $this->getDataGenerator()->create_user();

        $tasks = manager::get_adhoc_tasks(recalculate::class);
        $this->assertEmpty($tasks);

        $this->attempt_hippotrack($hippotrack, $user1);

        $tasks = manager::get_adhoc_tasks(recalculate::class);
        $this->assertCount(1, $tasks);

        $this->expectOutputRegex("~Re-calculating statistics for hippotrack {$hippotrack->name} \({$hippotrack->id}\) " .
            "from course {$course->shortname} \({$course->id}\) with 1 attempts~");
        statistics_helper::run_pending_recalculation_tasks();

        $tasks = manager::get_adhoc_tasks(recalculate::class);
        $this->assertEmpty($tasks);

        $this->attempt_hippotrack($hippotrack, $user2);

        $tasks = manager::get_adhoc_tasks(recalculate::class);
        $this->assertCount(1, $tasks);

        $task = reset($tasks);
        $this->assert_task_is_queued_for_hippotrack($task, $hippotrack);
    }

    /**
     * Attempting different hippotrackzes will queue a task for each.
     *
     * @return void
     */
    public function test_queue_separate_tasks_for_multiple_hippotrackzes(): void {
        [$user1, $hippotrack1] = $this->create_test_data();
        [$user2, $hippotrack2] = $this->create_test_data();

        $tasks = manager::get_adhoc_tasks(recalculate::class);
        $this->assertEmpty($tasks);

        $this->attempt_hippotrack($hippotrack1, $user1);
        $this->attempt_hippotrack($hippotrack2, $user2);

        $tasks = manager::get_adhoc_tasks(recalculate::class);
        $this->assertCount(2, $tasks);
        $task1 = array_shift($tasks);
        $this->assert_task_is_queued_for_hippotrack($task1, $hippotrack1);
        $task2 = array_shift($tasks);
        $this->assert_task_is_queued_for_hippotrack($task2, $hippotrack2);
    }
}
