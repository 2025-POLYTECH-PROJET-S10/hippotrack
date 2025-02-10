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

require_once("../../config.php");
// require_once(__DIR__ . '/classes/edit_question_form.php');
global $PAGE, $OUTPUT;

$id = required_param('id', PARAM_INT);

if ($id) {
    $cmid = get_coursemodule_from_id('hippotrack', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $cmid->course), '*', MUST_EXIST);
    $moduleinstance = $DB->get_record('hippotrack', array('id' => $cmid->instance), '*', MUST_EXIST);
} else {
    $moduleinstance = $DB->get_record('hippotrack', array('id' => $h), '*', MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $moduleinstance->course), '*', MUST_EXIST);
    $cmid = get_coursemodule_from_instance('hippotrack', $moduleinstance->id, $course->id, false, MUST_EXIST);
}

$PAGE->set_context(context_module::instance($cmid->id));
$PAGE->set_cm($cmid);
$PAGE->set_url(new moodle_url('/mod/hippotrack/exercise.php'));


$PAGE->requires->js_call_amd('mod_hippotrack/exercise', 'init');

echo $OUTPUT->header();

echo '<div id="container" class="container">';

echo '    <img id="bassin" class="bassin" src="' . new moodle_url('/mod/hippotrack/pix/bassin.png') . '">';

echo '    <img id="partogramme_contour2" class="partogramme_contour2" src="' . new moodle_url('/mod/hippotrack/pix/partogramme_contour2.png') . '">';

echo '    <img id="partogramme_interieur" class="partogramme_interieur" src="' . new moodle_url('/mod/hippotrack/pix/partogramme_interieur.png') . '">';

echo '    <img id="partogramme_contour" class="partogramme_contour" src="' . new moodle_url('/mod/hippotrack/pix/partogramme_contour.png') . '">';

echo '</div>';


// echo '<div id="bassin" class="bassin">';

// echo '    <img id="partogramme_contour2" class="partogramme_contour2" src="' . new moodle_url('/mod/hippotrack/pix/partogramme_contour2.png') . '">';

// echo '    <img id="partogramme_interieur" class="partogramme_interieur" src="' . new moodle_url('/mod/hippotrack/pix/partogramme_interieur.png') . '">';

// echo '    <img id="partogramme_contour" class="partogramme_contour" src="' . new moodle_url('/mod/hippotrack/pix/partogramme_contour.png') . '">';

// echo '</div>';


//              echo '    <img id="partogramme_contour2" src="' . new moodle_url('/mod/hippotrack/pix/partogramme_contour2.png') . '" 
//              style="position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); width: 300px; cursor: grab; z-index: 3;">';

// echo '    <img id="partogramme_interieur" src="' . new moodle_url('/mod/hippotrack/pix/partogramme_interieur.png') . '" 
//              style="position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); width: 100px; cursor: grab; z-index: 2;">';

// echo '    <img id="partogramme_contour" src="' . new moodle_url('/mod/hippotrack/pix/partogramme_contour.png') . '" 
//              style="position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); width: 100px; cursor: grab; z-index: 1;">';



echo '</div>';


echo '<label for="rotate-slider">Rotation:</label>';
echo '<input type="range" id="rotate-slider" min="0" max="360" value="0"><br>';

echo '<label for="move-axis-slider">Move Up/Down:</label>';
echo '<input type="range" id="move-axis-slider" min="-50" max="50" value="0">';











echo $OUTPUT->footer();
