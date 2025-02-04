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

use mod_hippotrack\question\bank\qbank_helper;
use hippotrack;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/hippotrack/attemptlib.php');

/**
 * Unit tests for hippotrack events.
 *
 * @package   mod_hippotrack
 * @category  test
 * @copyright 2013 Adrian Greeve
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class structure_test extends \advanced_testcase {

    /**
     * Create a course with an empty hippotrack.
     * @return array with three elements hippotrack, cm and course.
     */
    protected function prepare_hippotrack_data() {

        $this->resetAfterTest(true);

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Make a hippotrack.
        $hippotrackgenerator = $this->getDataGenerator()->get_plugin_generator('mod_hippotrack');

        $hippotrack = $hippotrackgenerator->create_instance(array('course' => $course->id, 'questionsperpage' => 0,
            'grade' => 100.0, 'sumgrades' => 2, 'preferredbehaviour' => 'immediatefeedback'));

        $cm = get_coursemodule_from_instance('hippotrack', $hippotrack->id, $course->id);

        return array($hippotrack, $cm, $course);
    }

    /**
     * Creat a test hippotrack.
     *
     * $layout looks like this:
     * $layout = array(
     *     'Heading 1'
     *     array('TF1', 1, 'truefalse'),
     *     'Heading 2*'
     *     array('TF2', 2, 'truefalse'),
     * );
     * That is, either a string, which represents a section heading,
     * or an array that represents a question.
     *
     * If the section heading ends with *, that section is shuffled.
     *
     * The elements in the question array are name, page number, and question type.
     *
     * @param array $layout as above.
     * @return hippotrack the created hippotrack.
     */
    protected function create_test_hippotrack($layout) {
        list($hippotrack, $cm, $course) = $this->prepare_hippotrack_data();
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();

        $headings = array();
        $slot = 1;
        $lastpage = 0;
        foreach ($layout as $item) {
            if (is_string($item)) {
                if (isset($headings[$lastpage + 1])) {
                    throw new \coding_exception('Sections cannot be empty.');
                }
                $headings[$lastpage + 1] = $item;

            } else {
                list($name, $page, $qtype) = $item;
                if ($page < 1 || !($page == $lastpage + 1 ||
                        (!isset($headings[$lastpage + 1]) && $page == $lastpage))) {
                    throw new \coding_exception('Page numbers wrong.');
                }
                $q = $questiongenerator->create_question($qtype, null,
                        array('name' => $name, 'category' => $cat->id));

                hippotrack_add_hippotrack_question($q->id, $hippotrack, $page);
                $lastpage = $page;
            }
        }

        $hippotrackobj = new hippotrack($hippotrack, $cm, $course);
        $structure = structure::create_for_hippotrack($hippotrackobj);
        if (isset($headings[1])) {
            list($heading, $shuffle) = $this->parse_section_name($headings[1]);
            $sections = $structure->get_sections();
            $firstsection = reset($sections);
            $structure->set_section_heading($firstsection->id, $heading);
            $structure->set_section_shuffle($firstsection->id, $shuffle);
            unset($headings[1]);
        }

        foreach ($headings as $startpage => $heading) {
            list($heading, $shuffle) = $this->parse_section_name($heading);
            $id = $structure->add_section_heading($startpage, $heading);
            $structure->set_section_shuffle($id, $shuffle);
        }

        return $hippotrackobj;
    }

    /**
     * Verify that the given layout matches that expected.
     * @param array $expectedlayout as for $layout in {@link create_test_hippotrack()}.
     * @param structure $structure the structure to test.
     */
    protected function assert_hippotrack_layout($expectedlayout, structure $structure) {
        $sections = $structure->get_sections();

        $slot = 1;
        foreach ($expectedlayout as $item) {
            if (is_string($item)) {
                list($heading, $shuffle) = $this->parse_section_name($item);
                $section = array_shift($sections);

                if ($slot > 1 && $section->heading == '' && $section->firstslot == 1) {
                    // The array $expectedlayout did not contain default first hippotrack section, so skip over it.
                    $section = array_shift($sections);
                }

                $this->assertEquals($slot, $section->firstslot);
                $this->assertEquals($heading, $section->heading);
                $this->assertEquals($shuffle, $section->shufflequestions);

            } else {
                list($name, $page, $qtype) = $item;
                $question = $structure->get_question_in_slot($slot);
                $this->assertEquals($name,  $question->name);
                $this->assertEquals($slot,  $question->slot,  'Slot number wrong for question ' . $name);
                $this->assertEquals($qtype, $question->qtype, 'Question type wrong for question ' . $name);
                $this->assertEquals($page,  $question->page,  'Page number wrong for question ' . $name);

                $slot += 1;
            }
        }

        if ($slot - 1 != count($structure->get_slots())) {
            $this->fail('The hippotrack contains more slots than expected.');
        }

        if (!empty($sections)) {
            $section = array_shift($sections);
            if ($section->heading != '' || $section->firstslot != 1) {
                $this->fail('Unexpected section (' . $section->heading .') found in the hippotrack.');
            }
        }
    }

    /**
     * Parse the section name, optionally followed by a * to mean shuffle, as
     * used by create_test_hippotrack as assert_hippotrack_layout.
     * @param string $heading the heading.
     * @return array with two elements, the heading and the shuffle setting.
     */
    protected function parse_section_name($heading) {
        if (substr($heading, -1) == '*') {
            return array(substr($heading, 0, -1), 1);
        } else {
            return array($heading, 0);
        }
    }

    public function test_get_hippotrack_slots() {
        $hippotrackobj = $this->create_test_hippotrack(array(
                array('TF1', 1, 'truefalse'),
                array('TF2', 1, 'truefalse'),
            ));
        $structure = structure::create_for_hippotrack($hippotrackobj);

        // Are the correct slots returned?
        $slots = $structure->get_slots();
        $this->assertCount(2, $structure->get_slots());
    }

    public function test_hippotrack_has_one_section_by_default() {
        $hippotrackobj = $this->create_test_hippotrack(array(
                array('TF1', 1, 'truefalse'),
            ));
        $structure = structure::create_for_hippotrack($hippotrackobj);

        $sections = $structure->get_sections();
        $this->assertCount(1, $sections);

        $section = array_shift($sections);
        $this->assertEquals(1, $section->firstslot);
        $this->assertEquals('', $section->heading);
        $this->assertEquals(0, $section->shufflequestions);
    }

    public function test_get_sections() {
        $hippotrackobj = $this->create_test_hippotrack(array(
                'Heading 1*',
                array('TF1', 1, 'truefalse'),
                'Heading 2*',
                array('TF2', 2, 'truefalse'),
        ));
        $structure = structure::create_for_hippotrack($hippotrackobj);

        $sections = $structure->get_sections();
        $this->assertCount(2, $sections);

        $section = array_shift($sections);
        $this->assertEquals(1, $section->firstslot);
        $this->assertEquals('Heading 1', $section->heading);
        $this->assertEquals(1, $section->shufflequestions);

        $section = array_shift($sections);
        $this->assertEquals(2, $section->firstslot);
        $this->assertEquals('Heading 2', $section->heading);
        $this->assertEquals(1, $section->shufflequestions);
    }

    public function test_remove_section_heading() {
        $hippotrackobj = $this->create_test_hippotrack(array(
                'Heading 1',
                array('TF1', 1, 'truefalse'),
                'Heading 2',
                array('TF2', 2, 'truefalse'),
            ));
        $structure = structure::create_for_hippotrack($hippotrackobj);

        $sections = $structure->get_sections();
        $section = end($sections);
        $structure->remove_section_heading($section->id);

        $structure = structure::create_for_hippotrack($hippotrackobj);
        $this->assert_hippotrack_layout(array(
                'Heading 1',
                array('TF1', 1, 'truefalse'),
                array('TF2', 2, 'truefalse'),
            ), $structure);
    }

    public function test_cannot_remove_first_section() {
        $hippotrackobj = $this->create_test_hippotrack(array(
                'Heading 1',
                array('TF1', 1, 'truefalse'),
        ));
        $structure = structure::create_for_hippotrack($hippotrackobj);

        $sections = $structure->get_sections();
        $section = reset($sections);

        $this->expectException(\coding_exception::class);
        $structure->remove_section_heading($section->id);
    }

    public function test_move_slot_to_the_same_place_does_nothing() {
        $hippotrackobj = $this->create_test_hippotrack(array(
                array('TF1', 1, 'truefalse'),
                array('TF2', 1, 'truefalse'),
                array('TF3', 2, 'truefalse'),
            ));
        $structure = structure::create_for_hippotrack($hippotrackobj);

        $idtomove = $structure->get_question_in_slot(2)->slotid;
        $idmoveafter = $structure->get_question_in_slot(1)->slotid;
        $structure->move_slot($idtomove, $idmoveafter, '1');

        $structure = structure::create_for_hippotrack($hippotrackobj);
        $this->assert_hippotrack_layout(array(
                array('TF1', 1, 'truefalse'),
                array('TF2', 1, 'truefalse'),
                array('TF3', 2, 'truefalse'),
            ), $structure);
    }

    public function test_move_slot_end_of_one_page_to_start_of_next() {
        $hippotrackobj = $this->create_test_hippotrack(array(
                array('TF1', 1, 'truefalse'),
                array('TF2', 1, 'truefalse'),
                array('TF3', 2, 'truefalse'),
            ));
        $structure = structure::create_for_hippotrack($hippotrackobj);

        $idtomove = $structure->get_question_in_slot(2)->slotid;
        $idmoveafter = $structure->get_question_in_slot(2)->slotid;
        $structure->move_slot($idtomove, $idmoveafter, '2');

        $structure = structure::create_for_hippotrack($hippotrackobj);
        $this->assert_hippotrack_layout(array(
                array('TF1', 1, 'truefalse'),
                array('TF2', 2, 'truefalse'),
                array('TF3', 2, 'truefalse'),
            ), $structure);
    }

    public function test_move_last_slot_to_previous_page_emptying_the_last_page() {
        $hippotrackobj = $this->create_test_hippotrack(array(
                array('TF1', 1, 'truefalse'),
                array('TF2', 2, 'truefalse'),
            ));
        $structure = structure::create_for_hippotrack($hippotrackobj);

        $idtomove = $structure->get_question_in_slot(2)->slotid;
        $idmoveafter = $structure->get_question_in_slot(1)->slotid;
        $structure->move_slot($idtomove, $idmoveafter, '1');

        $structure = structure::create_for_hippotrack($hippotrackobj);
        $this->assert_hippotrack_layout(array(
                array('TF1', 1, 'truefalse'),
                array('TF2', 1, 'truefalse'),
            ), $structure);
    }

    public function test_end_of_one_section_to_start_of_next() {
        $hippotrackobj = $this->create_test_hippotrack(array(
                array('TF1', 1, 'truefalse'),
                array('TF2', 1, 'truefalse'),
                'Heading',
                array('TF3', 2, 'truefalse'),
            ));
        $structure = structure::create_for_hippotrack($hippotrackobj);

        $idtomove = $structure->get_question_in_slot(2)->slotid;
        $idmoveafter = $structure->get_question_in_slot(2)->slotid;
        $structure->move_slot($idtomove, $idmoveafter, '2');

        $structure = structure::create_for_hippotrack($hippotrackobj);
        $this->assert_hippotrack_layout(array(
                array('TF1', 1, 'truefalse'),
                'Heading',
                array('TF2', 2, 'truefalse'),
                array('TF3', 2, 'truefalse'),
            ), $structure);
    }

    public function test_start_of_one_section_to_end_of_previous() {
        $hippotrackobj = $this->create_test_hippotrack(array(
                array('TF1', 1, 'truefalse'),
                'Heading',
                array('TF2', 2, 'truefalse'),
                array('TF3', 2, 'truefalse'),
            ));
        $structure = structure::create_for_hippotrack($hippotrackobj);

        $idtomove = $structure->get_question_in_slot(2)->slotid;
        $idmoveafter = $structure->get_question_in_slot(1)->slotid;
        $structure->move_slot($idtomove, $idmoveafter, '1');

        $structure = structure::create_for_hippotrack($hippotrackobj);
        $this->assert_hippotrack_layout(array(
                array('TF1', 1, 'truefalse'),
                array('TF2', 1, 'truefalse'),
                'Heading',
                array('TF3', 2, 'truefalse'),
            ), $structure);
    }
    public function test_move_slot_on_same_page() {
        $hippotrackobj = $this->create_test_hippotrack(array(
                array('TF1', 1, 'truefalse'),
                array('TF2', 1, 'truefalse'),
                array('TF3', 1, 'truefalse'),
            ));
        $structure = structure::create_for_hippotrack($hippotrackobj);

        $idtomove = $structure->get_question_in_slot(2)->slotid;
        $idmoveafter = $structure->get_question_in_slot(3)->slotid;
        $structure->move_slot($idtomove, $idmoveafter, '1');

        $structure = structure::create_for_hippotrack($hippotrackobj);
        $this->assert_hippotrack_layout(array(
                array('TF1', 1, 'truefalse'),
                array('TF3', 1, 'truefalse'),
                array('TF2', 1, 'truefalse'),
        ), $structure);
    }

    public function test_move_slot_up_onto_previous_page() {
        $hippotrackobj = $this->create_test_hippotrack(array(
                array('TF1', 1, 'truefalse'),
                array('TF2', 2, 'truefalse'),
                array('TF3', 2, 'truefalse'),
        ));
        $structure = structure::create_for_hippotrack($hippotrackobj);

        $idtomove = $structure->get_question_in_slot(3)->slotid;
        $idmoveafter = $structure->get_question_in_slot(1)->slotid;
        $structure->move_slot($idtomove, $idmoveafter, '1');

        $structure = structure::create_for_hippotrack($hippotrackobj);
        $this->assert_hippotrack_layout(array(
                array('TF1', 1, 'truefalse'),
                array('TF3', 1, 'truefalse'),
                array('TF2', 2, 'truefalse'),
        ), $structure);
    }

    public function test_move_slot_emptying_a_page_renumbers_pages() {
        $hippotrackobj = $this->create_test_hippotrack(array(
                array('TF1', 1, 'truefalse'),
                array('TF2', 2, 'truefalse'),
                array('TF3', 3, 'truefalse'),
        ));
        $structure = structure::create_for_hippotrack($hippotrackobj);

        $idtomove = $structure->get_question_in_slot(2)->slotid;
        $idmoveafter = $structure->get_question_in_slot(3)->slotid;
        $structure->move_slot($idtomove, $idmoveafter, '3');

        $structure = structure::create_for_hippotrack($hippotrackobj);
        $this->assert_hippotrack_layout(array(
                array('TF1', 1, 'truefalse'),
                array('TF3', 2, 'truefalse'),
                array('TF2', 2, 'truefalse'),
        ), $structure);
    }

    public function test_move_slot_too_small_page_number_detected() {
        $hippotrackobj = $this->create_test_hippotrack(array(
                array('TF1', 1, 'truefalse'),
                array('TF2', 2, 'truefalse'),
                array('TF3', 3, 'truefalse'),
        ));
        $structure = structure::create_for_hippotrack($hippotrackobj);

        $idtomove = $structure->get_question_in_slot(3)->slotid;
        $idmoveafter = $structure->get_question_in_slot(2)->slotid;
        $this->expectException(\coding_exception::class);
        $structure->move_slot($idtomove, $idmoveafter, '1');
    }

    public function test_move_slot_too_large_page_number_detected() {
        $hippotrackobj = $this->create_test_hippotrack(array(
                array('TF1', 1, 'truefalse'),
                array('TF2', 2, 'truefalse'),
                array('TF3', 3, 'truefalse'),
        ));
        $structure = structure::create_for_hippotrack($hippotrackobj);

        $idtomove = $structure->get_question_in_slot(1)->slotid;
        $idmoveafter = $structure->get_question_in_slot(2)->slotid;
        $this->expectException(\coding_exception::class);
        $structure->move_slot($idtomove, $idmoveafter, '4');
    }

    public function test_move_slot_within_section() {
        $hippotrackobj = $this->create_test_hippotrack(array(
                'Heading 1',
                array('TF1', 1, 'truefalse'),
                array('TF2', 1, 'truefalse'),
                'Heading 2',
                array('TF3', 2, 'truefalse'),
            ));
        $structure = structure::create_for_hippotrack($hippotrackobj);

        $idtomove = $structure->get_question_in_slot(1)->slotid;
        $idmoveafter = $structure->get_question_in_slot(2)->slotid;
        $structure->move_slot($idtomove, $idmoveafter, '1');

        $structure = structure::create_for_hippotrack($hippotrackobj);
        $this->assert_hippotrack_layout(array(
                'Heading 1',
                array('TF2', 1, 'truefalse'),
                array('TF1', 1, 'truefalse'),
                'Heading 2',
                array('TF3', 2, 'truefalse'),
            ), $structure);
    }

    public function test_move_slot_to_new_section() {
        $hippotrackobj = $this->create_test_hippotrack(array(
                'Heading 1',
                array('TF1', 1, 'truefalse'),
                array('TF2', 1, 'truefalse'),
                'Heading 2',
                array('TF3', 2, 'truefalse'),
            ));
        $structure = structure::create_for_hippotrack($hippotrackobj);

        $idtomove = $structure->get_question_in_slot(2)->slotid;
        $idmoveafter = $structure->get_question_in_slot(3)->slotid;
        $structure->move_slot($idtomove, $idmoveafter, '2');

        $structure = structure::create_for_hippotrack($hippotrackobj);
        $this->assert_hippotrack_layout(array(
                'Heading 1',
                array('TF1', 1, 'truefalse'),
                'Heading 2',
                array('TF3', 2, 'truefalse'),
                array('TF2', 2, 'truefalse'),
            ), $structure);
    }

    public function test_move_slot_to_start() {
        $hippotrackobj = $this->create_test_hippotrack(array(
                'Heading 1',
                array('TF1', 1, 'truefalse'),
                'Heading 2',
                array('TF2', 2, 'truefalse'),
                array('TF3', 2, 'truefalse'),
            ));
        $structure = structure::create_for_hippotrack($hippotrackobj);

        $idtomove = $structure->get_question_in_slot(3)->slotid;
        $structure->move_slot($idtomove, 0, '1');

        $structure = structure::create_for_hippotrack($hippotrackobj);
        $this->assert_hippotrack_layout(array(
                'Heading 1',
                array('TF3', 1, 'truefalse'),
                array('TF1', 1, 'truefalse'),
                'Heading 2',
                array('TF2', 2, 'truefalse'),
            ), $structure);
    }

    public function test_move_slot_down_to_start_of_second_section() {
        $hippotrackobj = $this->create_test_hippotrack(array(
                'Heading 1',
                array('TF1', 1, 'truefalse'),
                array('TF2', 1, 'truefalse'),
                'Heading 2',
                array('TF3', 2, 'truefalse'),
            ));
        $structure = structure::create_for_hippotrack($hippotrackobj);

        $idtomove = $structure->get_question_in_slot(2)->slotid;
        $idmoveafter = $structure->get_question_in_slot(2)->slotid;
        $structure->move_slot($idtomove, $idmoveafter, '2');

        $structure = structure::create_for_hippotrack($hippotrackobj);
        $this->assert_hippotrack_layout(array(
                'Heading 1',
                array('TF1', 1, 'truefalse'),
                'Heading 2',
                array('TF2', 2, 'truefalse'),
                array('TF3', 2, 'truefalse'),
            ), $structure);
    }

    public function test_move_first_slot_down_to_start_of_page_2() {
        $hippotrackobj = $this->create_test_hippotrack(array(
                'Heading 1',
                array('TF1', 1, 'truefalse'),
                array('TF2', 2, 'truefalse'),
            ));
        $structure = structure::create_for_hippotrack($hippotrackobj);

        $idtomove = $structure->get_question_in_slot(1)->slotid;
        $structure->move_slot($idtomove, 0, '2');

        $structure = structure::create_for_hippotrack($hippotrackobj);
        $this->assert_hippotrack_layout(array(
                'Heading 1',
                array('TF1', 1, 'truefalse'),
                array('TF2', 1, 'truefalse'),
            ), $structure);
    }

    public function test_move_first_slot_to_same_place_on_page_1() {
        $hippotrackobj = $this->create_test_hippotrack(array(
                'Heading 1',
                array('TF1', 1, 'truefalse'),
                array('TF2', 2, 'truefalse'),
            ));
        $structure = structure::create_for_hippotrack($hippotrackobj);

        $idtomove = $structure->get_question_in_slot(1)->slotid;
        $structure->move_slot($idtomove, 0, '1');

        $structure = structure::create_for_hippotrack($hippotrackobj);
        $this->assert_hippotrack_layout(array(
                'Heading 1',
                array('TF1', 1, 'truefalse'),
                array('TF2', 2, 'truefalse'),
            ), $structure);
    }

    public function test_move_first_slot_to_before_page_1() {
        $hippotrackobj = $this->create_test_hippotrack(array(
                'Heading 1',
                array('TF1', 1, 'truefalse'),
                array('TF2', 2, 'truefalse'),
            ));
        $structure = structure::create_for_hippotrack($hippotrackobj);

        $idtomove = $structure->get_question_in_slot(1)->slotid;
        $structure->move_slot($idtomove, 0, '');

        $structure = structure::create_for_hippotrack($hippotrackobj);
        $this->assert_hippotrack_layout(array(
                'Heading 1',
                array('TF1', 1, 'truefalse'),
                array('TF2', 2, 'truefalse'),
            ), $structure);
    }

    public function test_move_slot_up_to_start_of_second_section() {
        $hippotrackobj = $this->create_test_hippotrack(array(
                'Heading 1',
                array('TF1', 1, 'truefalse'),
                'Heading 2',
                array('TF2', 2, 'truefalse'),
                'Heading 3',
                array('TF3', 3, 'truefalse'),
                array('TF4', 3, 'truefalse'),
            ));
        $structure = structure::create_for_hippotrack($hippotrackobj);

        $idtomove = $structure->get_question_in_slot(3)->slotid;
        $idmoveafter = $structure->get_question_in_slot(1)->slotid;
        $structure->move_slot($idtomove, $idmoveafter, '2');

        $structure = structure::create_for_hippotrack($hippotrackobj);
        $this->assert_hippotrack_layout(array(
                'Heading 1',
                array('TF1', 1, 'truefalse'),
                'Heading 2',
                array('TF3', 2, 'truefalse'),
                array('TF2', 2, 'truefalse'),
                'Heading 3',
                array('TF4', 3, 'truefalse'),
            ), $structure);
    }

    public function test_move_slot_does_not_violate_heading_unique_key() {
        $hippotrackobj = $this->create_test_hippotrack(array(
                'Heading 1',
                array('TF1', 1, 'truefalse'),
                'Heading 2',
                array('TF2', 2, 'truefalse'),
                'Heading 3',
                array('TF3', 3, 'truefalse'),
                array('TF4', 3, 'truefalse'),
        ));
        $structure = structure::create_for_hippotrack($hippotrackobj);

        $idtomove = $structure->get_question_in_slot(4)->slotid;
        $idmoveafter = $structure->get_question_in_slot(1)->slotid;
        $structure->move_slot($idtomove, $idmoveafter, 1);

        $structure = structure::create_for_hippotrack($hippotrackobj);
        $this->assert_hippotrack_layout(array(
                'Heading 1',
                array('TF1', 1, 'truefalse'),
                array('TF4', 1, 'truefalse'),
                'Heading 2',
                array('TF2', 2, 'truefalse'),
                'Heading 3',
                array('TF3', 3, 'truefalse'),
        ), $structure);
    }

    public function test_hippotrack_remove_slot() {
        $hippotrackobj = $this->create_test_hippotrack(array(
                array('TF1', 1, 'truefalse'),
                array('TF2', 1, 'truefalse'),
                'Heading 2',
                array('TF3', 2, 'truefalse'),
            ));
        $structure = structure::create_for_hippotrack($hippotrackobj);

        $structure->remove_slot(2);

        $structure = structure::create_for_hippotrack($hippotrackobj);
        $this->assert_hippotrack_layout(array(
                array('TF1', 1, 'truefalse'),
                'Heading 2',
                array('TF3', 2, 'truefalse'),
            ), $structure);
    }

    public function test_hippotrack_removing_a_random_question_deletes_the_question() {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();

        $hippotrackobj = $this->create_test_hippotrack(array(
                array('TF1', 1, 'truefalse'),
            ));

        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        hippotrack_add_random_questions($hippotrackobj->get_hippotrack(), 1, $cat->id, 1, false);
        $structure = structure::create_for_hippotrack($hippotrackobj);
        $sql = 'SELECT qsr.*
                 FROM {question_set_references} qsr
                 JOIN {hippotrack_slots} qs ON qs.id = qsr.itemid
                 WHERE qs.hippotrackid = ?
                   AND qsr.component = ?
                   AND qsr.questionarea = ?';
        $randomq = $DB->get_record_sql($sql, [$hippotrackobj->get_hippotrackid(), 'mod_hippotrack', 'slot']);

        $structure->remove_slot(2);

        $structure = structure::create_for_hippotrack($hippotrackobj);
        $this->assert_hippotrack_layout(array(
                array('TF1', 1, 'truefalse'),
            ), $structure);
        $this->assertFalse($DB->record_exists('question_set_references',
            array('id' => $randomq->id, 'component' => 'mod_hippotrack', 'questionarea' => 'slot')));
    }

    /**
     * Unit test to make sue it is not possible to remove all slots in a section at once.
     */
    public function test_cannot_remove_all_slots_in_a_section() {
        $hippotrackobj = $this->create_test_hippotrack(array(
            array('TF1', 1, 'truefalse'),
            array('TF2', 1, 'truefalse'),
            'Heading 2',
            array('TF3', 2, 'truefalse'),
        ));
        $structure = structure::create_for_hippotrack($hippotrackobj);

        $structure->remove_slot(1);
        $this->expectException(\coding_exception::class);
        $structure->remove_slot(2);
    }

    public function test_cannot_remove_last_slot_in_a_section() {
        $hippotrackobj = $this->create_test_hippotrack(array(
                array('TF1', 1, 'truefalse'),
                array('TF2', 1, 'truefalse'),
                'Heading 2',
                array('TF3', 2, 'truefalse'),
            ));
        $structure = structure::create_for_hippotrack($hippotrackobj);

        $this->expectException(\coding_exception::class);
        $structure->remove_slot(3);
    }

    public function test_can_remove_last_question_in_a_hippotrack() {
        $hippotrackobj = $this->create_test_hippotrack(array(
                'Heading 1',
                array('TF1', 1, 'truefalse'),
            ));
        $structure = structure::create_for_hippotrack($hippotrackobj);

        $structure->remove_slot(1);

        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $q = $questiongenerator->create_question('truefalse', null,
                array('name' => 'TF2', 'category' => $cat->id));

        hippotrack_add_hippotrack_question($q->id, $hippotrackobj->get_hippotrack(), 0);
        $structure = structure::create_for_hippotrack($hippotrackobj);

        $this->assert_hippotrack_layout(array(
                'Heading 1',
                array('TF2', 1, 'truefalse'),
        ), $structure);
    }

    public function test_add_question_updates_headings() {
        $hippotrackobj = $this->create_test_hippotrack(array(
                array('TF1', 1, 'truefalse'),
                'Heading 2',
                array('TF2', 2, 'truefalse'),
        ));

        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $q = $questiongenerator->create_question('truefalse', null,
                array('name' => 'TF3', 'category' => $cat->id));

        hippotrack_add_hippotrack_question($q->id, $hippotrackobj->get_hippotrack(), 1);

        $structure = structure::create_for_hippotrack($hippotrackobj);
        $this->assert_hippotrack_layout(array(
                array('TF1', 1, 'truefalse'),
                array('TF3', 1, 'truefalse'),
                'Heading 2',
                array('TF2', 2, 'truefalse'),
        ), $structure);
    }

    public function test_add_question_updates_headings_even_with_one_question_sections() {
        $hippotrackobj = $this->create_test_hippotrack(array(
                'Heading 1',
                array('TF1', 1, 'truefalse'),
                'Heading 2',
                array('TF2', 2, 'truefalse'),
                'Heading 3',
                array('TF3', 3, 'truefalse'),
        ));

        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $q = $questiongenerator->create_question('truefalse', null,
                array('name' => 'TF4', 'category' => $cat->id));

        hippotrack_add_hippotrack_question($q->id, $hippotrackobj->get_hippotrack(), 1);

        $structure = structure::create_for_hippotrack($hippotrackobj);
        $this->assert_hippotrack_layout(array(
                'Heading 1',
                array('TF1', 1, 'truefalse'),
                array('TF4', 1, 'truefalse'),
                'Heading 2',
                array('TF2', 2, 'truefalse'),
                'Heading 3',
                array('TF3', 3, 'truefalse'),
        ), $structure);
    }

    public function test_add_question_at_end_does_not_update_headings() {
        $hippotrackobj = $this->create_test_hippotrack(array(
                array('TF1', 1, 'truefalse'),
                'Heading 2',
                array('TF2', 2, 'truefalse'),
        ));

        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $q = $questiongenerator->create_question('truefalse', null,
                array('name' => 'TF3', 'category' => $cat->id));

        hippotrack_add_hippotrack_question($q->id, $hippotrackobj->get_hippotrack(), 0);

        $structure = structure::create_for_hippotrack($hippotrackobj);
        $this->assert_hippotrack_layout(array(
                array('TF1', 1, 'truefalse'),
                'Heading 2',
                array('TF2', 2, 'truefalse'),
                array('TF3', 2, 'truefalse'),
        ), $structure);
    }

    public function test_remove_page_break() {
        $hippotrackobj = $this->create_test_hippotrack(array(
                array('TF1', 1, 'truefalse'),
                array('TF2', 2, 'truefalse'),
            ));
        $structure = structure::create_for_hippotrack($hippotrackobj);

        $slotid = $structure->get_question_in_slot(2)->slotid;
        $slots = $structure->update_page_break($slotid, repaginate::LINK);

        $structure = structure::create_for_hippotrack($hippotrackobj);
        $this->assert_hippotrack_layout(array(
                array('TF1', 1, 'truefalse'),
                array('TF2', 1, 'truefalse'),
            ), $structure);
    }

    public function test_add_page_break() {
        $hippotrackobj = $this->create_test_hippotrack(array(
                array('TF1', 1, 'truefalse'),
                array('TF2', 1, 'truefalse'),
        ));
        $structure = structure::create_for_hippotrack($hippotrackobj);

        $slotid = $structure->get_question_in_slot(2)->slotid;
        $slots = $structure->update_page_break($slotid, repaginate::UNLINK);

        $structure = structure::create_for_hippotrack($hippotrackobj);
        $this->assert_hippotrack_layout(array(
                array('TF1', 1, 'truefalse'),
                array('TF2', 2, 'truefalse'),
        ), $structure);
    }

    public function test_update_question_dependency() {
        $hippotrackobj = $this->create_test_hippotrack(array(
                array('TF1', 1, 'truefalse'),
                array('TF2', 1, 'truefalse'),
        ));
        $structure = structure::create_for_hippotrack($hippotrackobj);

        // Test adding a dependency.
        $slotid = $structure->get_slot_id_for_slot(2);
        $structure->update_question_dependency($slotid, true);

        // Having called update page break, we need to reload $structure.
        $structure = structure::create_for_hippotrack($hippotrackobj);
        $this->assertEquals(1, $structure->is_question_dependent_on_previous_slot(2));

        // Test removing a dependency.
        $structure->update_question_dependency($slotid, false);

        // Having called update page break, we need to reload $structure.
        $structure = structure::create_for_hippotrack($hippotrackobj);
        $this->assertEquals(0, $structure->is_question_dependent_on_previous_slot(2));
    }

    /**
     * Test for can_add_random_questions.
     */
    public function test_can_add_random_questions() {
        $this->resetAfterTest();

        $hippotrack = $this->create_test_hippotrack([]);
        $course = $hippotrack->get_course();

        $generator = $this->getDataGenerator();
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $noneditingteacher = $generator->create_and_enrol($course, 'teacher');

        $this->setUser($teacher);
        $structure = structure::create_for_hippotrack($hippotrack);
        $this->assertTrue($structure->can_add_random_questions());

        $this->setUser($noneditingteacher);
        $structure = structure::create_for_hippotrack($hippotrack);
        $this->assertFalse($structure->can_add_random_questions());
    }

    /**
     * Test to get the version information for a question to show in the version selection dropdown.
     *
     * @covers ::get_question_version_info
     */
    public function test_get_version_choices_for_slot() {
        $this->resetAfterTest();

        $hippotrackobj = $this->create_test_hippotrack([]);

        // Create a question with two versions.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category(['contextid' => $hippotrackobj->get_context()->id]);
        $q = $questiongenerator->create_question('essay', null,
                ['category' => $cat->id, 'name' => 'This is the first version']);
        $questiongenerator->update_question($q, null, ['name' => 'This is the second version']);
        $questiongenerator->update_question($q, null, ['name' => 'This is the third version']);
        hippotrack_add_hippotrack_question($q->id, $hippotrackobj->get_hippotrack());

        // Create the hippotrack object.
        $structure = structure::create_for_hippotrack($hippotrackobj);
        $versiondata = $structure->get_version_choices_for_slot(1);
        $this->assertEquals(4, count($versiondata));
        $this->assertEquals('Always latest', $versiondata[0]->versionvalue);
        $this->assertEquals('v3 (latest)', $versiondata[1]->versionvalue);
        $this->assertEquals('v2', $versiondata[2]->versionvalue);
        $this->assertEquals('v1', $versiondata[3]->versionvalue);
        $this->assertTrue($versiondata[0]->selected);
        $this->assertFalse($versiondata[1]->selected);
        $this->assertFalse($versiondata[2]->selected);
        $this->assertFalse($versiondata[3]->selected);
    }

    /**
     * Test the current user have '...use' capability over the question(s) in a given slot.
     *
     * @covers ::has_use_capability
     */
    public function test_has_use_capability() {
        $this->resetAfterTest();

        // Create a hippotrack with question.
        $hippotrackobj = $this->create_test_hippotrack([]);
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category(['contextid' => $hippotrackobj->get_context()->id]);
        $q = $questiongenerator->create_question('essay', null,
            ['category' => $cat->id, 'name' => 'This is essay question']);
        hippotrack_add_hippotrack_question($q->id, $hippotrackobj->get_hippotrack());

        // Create the hippotrack object.
        $structure = structure::create_for_hippotrack($hippotrackobj);
        $slots = $structure->get_slots();

        // Get slot.
        $slotid = array_pop($slots)->slot;

        $course = $hippotrackobj->get_course();
        $generator = $this->getDataGenerator();
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $student = $generator->create_and_enrol($course);

        $this->setUser($teacher);
        $this->assertTrue($structure->has_use_capability($slotid));

        $this->setUser($student);
        $this->assertFalse($structure->has_use_capability($slotid));
    }
}
