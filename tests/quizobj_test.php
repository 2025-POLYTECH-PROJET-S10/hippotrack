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

use mod_hippotrack_display_options;
use hippotrack;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/hippotrack/locallib.php');

/**
 * Unit tests for the hippotrack class
 *
 * @package    mod_hippotrack
 * @copyright  2008 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hippotrackobj_test extends \basic_testcase {
    public function test_cannot_review_message() {
        $hippotrack = new \stdClass();
        $hippotrack->reviewattempt = 0x10010;
        $hippotrack->timeclose = 0;
        $hippotrack->attempts = 0;

        $cm = new \stdClass();
        $cm->id = 123;

        $hippotrackobj = new hippotrack($hippotrack, $cm, new \stdClass(), false);

        $this->assertEquals('',
            $hippotrackobj->cannot_review_message(mod_hippotrack_display_options::DURING));
        $this->assertEquals('',
            $hippotrackobj->cannot_review_message(mod_hippotrack_display_options::IMMEDIATELY_AFTER));
        $this->assertEquals(get_string('noreview', 'hippotrack'),
            $hippotrackobj->cannot_review_message(mod_hippotrack_display_options::LATER_WHILE_OPEN));
        $this->assertEquals(get_string('noreview', 'hippotrack'),
            $hippotrackobj->cannot_review_message(mod_hippotrack_display_options::AFTER_CLOSE));

        $closetime = time() + 10000;
        $hippotrack->timeclose = $closetime;
        $hippotrackobj = new hippotrack($hippotrack, $cm, new \stdClass(), false);

        $this->assertEquals(get_string('noreviewuntil', 'hippotrack', userdate($closetime)),
            $hippotrackobj->cannot_review_message(mod_hippotrack_display_options::LATER_WHILE_OPEN));
    }
}
