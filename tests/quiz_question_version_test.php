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

use mod_hippotrack\external\submit_question_version;
use mod_hippotrack\question\bank\qbank_helper;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/hippotrack_question_helper_test_trait.php');

/**
 * Question versions test for hippotrack.
 *
 * @package    mod_hippotrack
 * @category   test
 * @copyright  2021 Catalyst IT Australia Pty Ltd
 * @author     Safat Shahin <safatshahin@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \mod_hippotrack\question\bank\qbank_helper
 */
class hippotrack_question_version_test extends \advanced_testcase {
    use \hippotrack_question_helper_test_trait;

    /**
     * Called before every test.
     */
    public function setUp(): void {
        global $USER;
        parent::setUp();
        $this->setAdminUser();
        $this->course = $this->getDataGenerator()->create_course();
        $this->student = $this->getDataGenerator()->create_user();
        $this->user = $USER;
    }

    /**
     * Test the hippotrack question data for changed version in the slots.
     *
     * @covers ::get_version_options
     */
    public function test_hippotrack_questions_for_changed_versions() {
        $this->resetAfterTest();
        $hippotrack = $this->create_test_hippotrack($this->course);
        // Test for questions from a different context.
        $context = \context_module::instance(get_coursemodule_from_instance("hippotrack", $hippotrack->id, $this->course->id)->id);
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        // Create a couple of questions.
        $cat = $questiongenerator->create_question_category(['contextid' => $context->id]);
        $numq = $questiongenerator->create_question('essay', null,
            ['category' => $cat->id, 'name' => 'This is the first version']);
        // Create two version.
        $questiongenerator->update_question($numq, null, ['name' => 'This is the second version']);
        $questiongenerator->update_question($numq, null, ['name' => 'This is the third version']);
        hippotrack_add_hippotrack_question($numq->id, $hippotrack);
        // Create the hippotrack object.
        $hippotrackobj = \hippotrack::create($hippotrack->id);
        $structure = \mod_hippotrack\structure::create_for_hippotrack($hippotrackobj);
        $slots = $structure->get_slots();
        $slot = reset($slots);
        // Test that the version added is 'always latest'.
        $this->assertEquals(3, $slot->version);
        $hippotrackobj->preload_questions();
        $hippotrackobj->load_questions();
        $questions = $hippotrackobj->get_questions();
        $question = reset($questions);
        $this->assertEquals(3, $question->version);
        $this->assertEquals('This is the third version', $question->name);
        // Create another version.
        $questiongenerator->update_question($numq, null, ['name' => 'This is the latest version']);
        // Check that 'Always latest is working'.
        $hippotrackobj->preload_questions();
        $hippotrackobj->load_questions();
        $questions = $hippotrackobj->get_questions();
        $question = reset($questions);
        $this->assertEquals(4, $question->version);
        $this->assertEquals('This is the latest version', $question->name);
        $structure = \mod_hippotrack\structure::create_for_hippotrack($hippotrackobj);
        $slots = $structure->get_slots();
        $slot = reset($slots);
        $this->assertEquals(4, $slot->version);
        // Now change the version using the external service.
        $versions = qbank_helper::get_version_options($slot->questionid);
        // We don't want the current version.
        $selectversions = [];
        foreach ($versions as $version) {
            if ($version->version === $slot->version) {
                continue;
            }
            $selectversions [$version->version] = $version;
        }
        // Change to version 1.
        submit_question_version::execute($slot->id, (int)$selectversions[1]->version);
        $hippotrackobj->preload_questions();
        $hippotrackobj->load_questions();
        $questions = $hippotrackobj->get_questions();
        $question = reset($questions);
        $this->assertEquals(1, $question->version);
        $this->assertEquals('This is the first version', $question->name);
        $structure = \mod_hippotrack\structure::create_for_hippotrack($hippotrackobj);
        $slots = $structure->get_slots();
        $slot = reset($slots);
        $this->assertEquals(1, $slot->version);
        // Change to version 2.
        submit_question_version::execute($slot->id, $selectversions[2]->version);
        $hippotrackobj->preload_questions();
        $hippotrackobj->load_questions();
        $questions = $hippotrackobj->get_questions();
        $question = reset($questions);
        $this->assertEquals(2, $question->version);
        $this->assertEquals('This is the second version', $question->name);
        $structure = \mod_hippotrack\structure::create_for_hippotrack($hippotrackobj);
        $slots = $structure->get_slots();
        $slot = reset($slots);
        $this->assertEquals(2, $slot->version);
    }

    /**
     * Test if changing the version of the slot changes the attempts.
     *
     * @covers ::get_version_options
     */
    public function test_hippotrack_question_attempts_with_changed_version() {
        $this->resetAfterTest();
        $hippotrack = $this->create_test_hippotrack($this->course);
        // Test for questions from a different context.
        $context = \context_module::instance(get_coursemodule_from_instance("hippotrack", $hippotrack->id, $this->course->id)->id);
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        // Create a couple of questions.
        $cat = $questiongenerator->create_question_category(['contextid' => $context->id]);
        $numq = $questiongenerator->create_question('numerical', null,
            ['category' => $cat->id, 'name' => 'This is the first version']);
        // Create two version.
        $questiongenerator->update_question($numq, null, ['name' => 'This is the second version']);
        $questiongenerator->update_question($numq, null, ['name' => 'This is the third version']);
        hippotrack_add_hippotrack_question($numq->id, $hippotrack);
        list($hippotrackobj, $quba, $attemptobj) = $this->attempt_hippotrack($hippotrack, $this->student);
        $this->assertEquals('This is the third version', $attemptobj->get_question_attempt(1)->get_question()->name);
        // Create the hippotrack object.
        $hippotrackobj = \hippotrack::create($hippotrack->id);
        $structure = \mod_hippotrack\structure::create_for_hippotrack($hippotrackobj);
        $slots = $structure->get_slots();
        $slot = reset($slots);
        // Now change the version using the external service.
        $versions = qbank_helper::get_version_options($slot->questionid);
        // We dont want the current version.
        $selectversions = [];
        foreach ($versions as $version) {
            if ($version->version === $slot->version) {
                continue;
            }
            $selectversions [$version->version] = $version;
        }
        // Change to version 1.
        $this->expectException('moodle_exception');
        submit_question_version::execute($slot->id, (int)$selectversions[1]->version);
        list($hippotrackobj, $quba, $attemptobj) = $this->attempt_hippotrack($hippotrack, $this->student, 2);
        $this->assertEquals('This is the first version', $attemptobj->get_question_attempt(1)->get_question()->name);
        // Change to version 2.
        submit_question_version::execute($slot->id, (int)$selectversions[2]->version);
        list($hippotrackobj, $quba, $attemptobj) = $this->attempt_hippotrack($hippotrack, $this->student, 3);
        $this->assertEquals('This is the second version', $attemptobj->get_question_attempt(1)->get_question()->name);
        // Create another version.
        $questiongenerator->update_question($numq, null, ['name' => 'This is the latest version']);
        // Change to always latest.
        submit_question_version::execute($slot->id, 0);
        list($hippotrackobj, $quba, $attemptobj) = $this->attempt_hippotrack($hippotrack, $this->student, 4);
        $this->assertEquals('This is the latest version', $attemptobj->get_question_attempt(1)->get_question()->name);
    }
}
