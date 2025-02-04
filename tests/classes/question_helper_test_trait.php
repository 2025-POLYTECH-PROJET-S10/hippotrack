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

namespace mod_hippotrack\tests;

use backup;
use backup_controller;
use component_generator_base;
use mod_hippotrack_generator;
use hippotrack;
use hippotrack_attempt;
use restore_controller;
use stdClass;
use question_engine;

/**
 * Helper trait for hippotrack question unit tests.
 *
 * This trait helps to execute different tests for hippotrack, for example if it needs to create a hippotrack, add question
 * to the question, add random quetion to the hippotrack, do a backup or restore.
 *
 * @package    mod_hippotrack
 * @category   test
 * @copyright  2021 Catalyst IT Australia Pty Ltd
 * @author     Safat Shahin <safatshahin@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait question_helper_test_trait {
    /** @var stdClass $course Test course to contain hippotrack. */
    protected $course;

    /** @var stdClass $hippotrack A test hippotrack. */
    protected $hippotrack;

    /** @var stdClass $user A test logged-in user. */
    protected $user;

    /**
     * Create a test hippotrack for the specified course.
     *
     * @param stdClass $course
     * @return  stdClass
     */
    protected function create_test_hippotrack(stdClass $course): stdClass {

        /** @var mod_hippotrack_generator $hippotrackgenerator */
        $hippotrackgenerator = $this->getDataGenerator()->get_plugin_generator('mod_hippotrack');

        return $hippotrackgenerator->create_instance([
            'course' => $course->id,
            'questionsperpage' => 0,
            'grade' => 100.0,
            'sumgrades' => 2,
        ]);
    }

    /**
     * Helper method to add regular questions in hippotrack.
     *
     * @param component_generator_base $questiongenerator
     * @param stdClass $hippotrack
     * @param array $override
     */
    protected function add_two_regular_questions($questiongenerator, stdClass $hippotrack, $override = null): void {
        // Create a couple of questions.
        $cat = $questiongenerator->create_question_category($override);

        $saq = $questiongenerator->create_question('shortanswer', null, array('category' => $cat->id));
        // Create another version.
        $questiongenerator->update_question($saq);
        hippotrack_add_hippotrack_question($saq->id, $hippotrack);
        $numq = $questiongenerator->create_question('numerical', null, array('category' => $cat->id));
        // Create two version.
        $questiongenerator->update_question($numq);
        $questiongenerator->update_question($numq);
        hippotrack_add_hippotrack_question($numq->id, $hippotrack);
    }

    /**
     * Helper method to add random question to hippotrack.
     *
     * @param component_generator_base $questiongenerator
     * @param stdClass $hippotrack
     * @param array $override
     */
    protected function add_one_random_question($questiongenerator, stdClass $hippotrack, $override = []): void {
        // Create a random question.
        $cat = $questiongenerator->create_question_category($override);
        $questiongenerator->create_question('truefalse', null, array('category' => $cat->id));
        $questiongenerator->create_question('essay', null, array('category' => $cat->id));
        hippotrack_add_random_questions($hippotrack, 0, $cat->id, 1, false);
    }

    /**
     * Attempt questions for a hippotrack and user.
     *
     * @param stdClass $hippotrack HippoTrack to attempt.
     * @param stdClass $user A user to attempt the hippotrack.
     * @param int $attemptnumber
     * @return array
     */
    protected function attempt_hippotrack(stdClass $hippotrack, stdClass $user, $attemptnumber = 1): array {
        $this->setUser($user);

        $starttime = time();
        $hippotrackobj = hippotrack::create($hippotrack->id, $user->id);

        $quba = question_engine::make_questions_usage_by_activity('mod_hippotrack', $hippotrackobj->get_context());
        $quba->set_preferred_behaviour($hippotrackobj->get_hippotrack()->preferredbehaviour);

        // Start the attempt.
        $attempt = hippotrack_create_attempt($hippotrackobj, $attemptnumber, null, $starttime, false, $user->id);
        hippotrack_start_new_attempt($hippotrackobj, $quba, $attempt, $attemptnumber, $starttime);
        hippotrack_attempt_save_started($hippotrackobj, $quba, $attempt);

        // Finish the attempt.
        $attemptobj = hippotrack_attempt::create($attempt->id);
        $attemptobj->process_finish($starttime, false);

        $this->setUser();
        return [$hippotrackobj, $quba, $attemptobj];
    }

    /**
     * A helper method to backup test hippotrack.
     *
     * @param stdClass $hippotrack HippoTrack to attempt.
     * @param stdClass $user A user to attempt the hippotrack.
     * @return string A backup ID ready to be restored.
     */
    protected function backup_hippotrack(stdClass $hippotrack, stdClass $user): string {
        global $CFG;

        // Get the necessary files to perform backup and restore.
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

        $backupid = 'test-question-backup-restore';

        $bc = new backup_controller(backup::TYPE_1ACTIVITY, $hippotrack->cmid, backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO, backup::MODE_GENERAL, $user->id);
        $bc->execute_plan();

        $results = $bc->get_results();
        $file = $results['backup_destination'];
        $fp = get_file_packer('application/vnd.moodle.backup');
        $filepath = $CFG->dataroot . '/temp/backup/' . $backupid;
        $file->extract_to_pathname($fp, $filepath);
        $bc->destroy();

        return $backupid;
    }

    /**
     * A helper method to restore provided backup.
     *
     * @param string $backupid Backup ID to restore.
     * @param stdClass $course
     * @param stdClass $user
     */
    protected function restore_hippotrack(string $backupid, stdClass $course, stdClass $user): void {
        $rc = new restore_controller($backupid, $course->id,
            backup::INTERACTIVE_NO, backup::MODE_GENERAL, $user->id, backup::TARGET_CURRENT_ADDING);
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();
    }

    /**
     * A helper method to emulate duplication of the hippotrack.
     *
     * @param stdClass $course
     * @param stdClass $hippotrack
     * @return \cm_info|null
     */
    protected function duplicate_hippotrack($course, $hippotrack): ?\cm_info {
        return duplicate_module($course, get_fast_modinfo($course)->get_cm($hippotrack->cmid));
    }
}
