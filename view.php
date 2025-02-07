<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Prints an instance of hippotrack.
 *
 * @package     mod_hippotrack
 * @copyright   2025 Lionel Di Marco <LDiMarco@chu-grenoble.fr>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

// Course module id.
$id = optional_param('id', 0, PARAM_INT);

// Activity instance id.
$h = optional_param('h', 0, PARAM_INT);

if ($id) {
    $cm = get_coursemodule_from_id('hippotrack', $id, 0, false, MUST_EXIST);
    $cmid = $cm->id;
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $moduleinstance = $DB->get_record('hippotrack', array('id' => $cm->instance), '*', MUST_EXIST);
} else {
    $moduleinstance = $DB->get_record('hippotrack', array('id' => $h), '*', MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $moduleinstance->course), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('hippotrack', $moduleinstance->id, $course->id, false, MUST_EXIST);
}



// if (!$cmid = get_coursemodule_from_id('hippotrack', $id)) {
//     throw new moodle_exception('invalidcoursemodule');
// }

// if (!$course = $DB->get_record("course", array("id" => $cmid->course))) {
//     throw new moodle_exception('coursemisconf');
// }

// if (!$module = $DB->get_record('hippotrack', ['id' => $cmid->instance])) {
//     throw new moodle_exception('DataBase for hippotrack not found');
// }


$PAGE->set_cm($cm);
$PAGE->set_context($context);
$PAGE->set_url('/mod/hippotrack/view.php', array('id' => $id));





//Debut de l'affichage
echo $OUTPUT->header();


// Title Poll
$divTitle = '<div id=divTitle>';
$divTitle .= '<h2 id=namePoll>';
$divTitle .= get_string('title', 'mod_hippotrack', $cm->name);
$divTitle .= '</h2>';
$divTitle .= '<div>';

echo $divTitle;
echo 'test';


$url_exercice = new moodle_url('/mod/hippotrack/exercise.php');

echo '<a href="' . $url_exercice .
    '?id=' . $id .
    '">' . get_string('exercisePageLink', component: 'mod_hippotrack') . '<br></a>';


$url_stats = new moodle_url('/mod/hippotrack/stats.php');

echo '<a href="' . $url_stats .
    '?id=' . $id .
    '">' . get_string('statsPageLink', component: 'mod_hippotrack') . '<br></a>';


$url_dbaction = new moodle_url('/mod/hippotrack/dbaction.php');

echo '<a href="' . $url_dbaction .
    '?id=' . $id .
    '">' . get_string('dbPageLink', component: 'mod_hippotrack') . '<br></a>';




// $PAGE->requires->js_call_amd('mod_hippotrack/view', 'init', ['cmid' => $cmid]);
// echo '<button id="addQuestionButton">' . get_string('add_question', 'mod_hippotrack') . '</button>'; //redirected by javascript






echo $OUTPUT->footer();