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
 * This script lists all the instances of hippotrack in a particular course
 *
 * @package    mod_hippotrack
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once("../../config.php");
require_once("locallib.php");

$id = required_param('id', PARAM_INT);
$PAGE->set_url('/mod/hippotrack/index.php', array('id'=>$id));
if (!$course = $DB->get_record('course', array('id' => $id))) {
    throw new \moodle_exception('invalidcourseid');
}
$coursecontext = context_course::instance($id);
require_login($course);
$PAGE->set_pagelayout('incourse');

$params = array(
    'context' => $coursecontext
);
$event = \mod_hippotrack\event\course_module_instance_list_viewed::create($params);
$event->trigger();

// Print the header.
$strhippotrackzes = get_string("modulenameplural", "hippotrack");
$PAGE->navbar->add($strhippotrackzes);
$PAGE->set_title($strhippotrackzes);
$PAGE->set_heading($course->fullname);
echo $OUTPUT->header();
echo $OUTPUT->heading($strhippotrackzes, 2);

// Get all the appropriate data.
if (!$hippotrackzes = get_all_instances_in_course("hippotrack", $course)) {
    notice(get_string('thereareno', 'moodle', $strhippotrackzes), "../../course/view.php?id=$course->id");
    die;
}

// Check if we need the feedback header.
$showfeedback = false;
foreach ($hippotrackzes as $hippotrack) {
    if (hippotrack_has_feedback($hippotrack)) {
        $showfeedback=true;
    }
    if ($showfeedback) {
        break;
    }
}

// Configure table for displaying the list of instances.
$headings = array(get_string('name'));
$align = array('left');

array_push($headings, get_string('hippotrackcloses', 'hippotrack'));
array_push($align, 'left');

if (course_format_uses_sections($course->format)) {
    array_unshift($headings, get_string('sectionname', 'format_'.$course->format));
} else {
    array_unshift($headings, '');
}
array_unshift($align, 'center');

$showing = '';

if (has_capability('mod/hippotrack:viewreports', $coursecontext)) {
    array_push($headings, get_string('attempts', 'hippotrack'));
    array_push($align, 'left');
    $showing = 'stats';

} else if (has_any_capability(array('mod/hippotrack:reviewmyattempts', 'mod/hippotrack:attempt'),
        $coursecontext)) {
    array_push($headings, get_string('grade', 'hippotrack'));
    array_push($align, 'left');
    if ($showfeedback) {
        array_push($headings, get_string('feedback', 'hippotrack'));
        array_push($align, 'left');
    }
    $showing = 'grades';

    $grades = $DB->get_records_sql_menu('
            SELECT qg.hippotrack, qg.grade
            FROM {hippotrack_grades} qg
            JOIN {hippotrack} q ON q.id = qg.hippotrack
            WHERE q.course = ? AND qg.userid = ?',
            array($course->id, $USER->id));
}

$table = new html_table();
$table->head = $headings;
$table->align = $align;

// Populate the table with the list of instances.
$currentsection = '';
// Get all closing dates.
$timeclosedates = hippotrack_get_user_timeclose($course->id);
foreach ($hippotrackzes as $hippotrack) {
    $cm = get_coursemodule_from_instance('hippotrack', $hippotrack->id);
    $context = context_module::instance($cm->id);
    $data = array();

    // Section number if necessary.
    $strsection = '';
    if ($hippotrack->section != $currentsection) {
        if ($hippotrack->section) {
            $strsection = $hippotrack->section;
            $strsection = get_section_name($course, $hippotrack->section);
        }
        if ($currentsection !== "") {
            $table->data[] = 'hr';
        }
        $currentsection = $hippotrack->section;
    }
    $data[] = $strsection;

    // Link to the instance.
    $class = '';
    if (!$hippotrack->visible) {
        $class = ' class="dimmed"';
    }
    $data[] = "<a$class href=\"view.php?id=$hippotrack->coursemodule\">" .
            format_string($hippotrack->name, true) . '</a>';

    // Close date.
    if (($timeclosedates[$hippotrack->id]->usertimeclose != 0)) {
        $data[] = userdate($timeclosedates[$hippotrack->id]->usertimeclose);
    } else {
        $data[] = get_string('noclose', 'hippotrack');
    }

    if ($showing == 'stats') {
        // The $hippotrack objects returned by get_all_instances_in_course have the necessary $cm
        // fields set to make the following call work.
        $data[] = hippotrack_attempt_summary_link_to_reports($hippotrack, $cm, $context);

    } else if ($showing == 'grades') {
        // Grade and feedback.
        $attempts = hippotrack_get_user_attempts($hippotrack->id, $USER->id, 'all');
        list($someoptions, $alloptions) = hippotrack_get_combined_reviewoptions(
                $hippotrack, $attempts);

        $grade = '';
        $feedback = '';
        if ($hippotrack->grade && array_key_exists($hippotrack->id, $grades)) {
            if ($alloptions->marks >= question_display_options::MARK_AND_MAX) {
                $a = new stdClass();
                $a->grade = hippotrack_format_grade($hippotrack, $grades[$hippotrack->id]);
                $a->maxgrade = hippotrack_format_grade($hippotrack, $hippotrack->grade);
                $grade = get_string('outofshort', 'hippotrack', $a);
            }
            if ($alloptions->overallfeedback) {
                $feedback = hippotrack_feedback_for_grade($grades[$hippotrack->id], $hippotrack, $context);
            }
        }
        $data[] = $grade;
        if ($showfeedback) {
            $data[] = $feedback;
        }
    }

    $table->data[] = $data;
} // End of loop over hippotrack instances.

// Display the table.
echo html_writer::table($table);

// Finish the page.
echo $OUTPUT->footer();
