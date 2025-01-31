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
 * Version information
 *
 * @package    mod_easyvote
 * @copyright  2016 Cyberlearn
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../config.php");
// require_once(__DIR__ . '/classes/edit_question_form.php');
global $PAGE, $OUTPUT;

$id = required_param('id', PARAM_INT);

if ($id) {
    $cm = get_coursemodule_from_id('hippotrack', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $moduleinstance = $DB->get_record('hippotrack', array('id' => $cm->instance), '*', MUST_EXIST);
} else {
    $moduleinstance = $DB->get_record('hippotrack', array('id' => $h), '*', MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $moduleinstance->course), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('hippotrack', $moduleinstance->id, $course->id, false, MUST_EXIST);
}

// if (!$cm = get_coursemodule_from_id('hippotrack', $id)) {
//     throw new moodle_exception('invalidcoursemodule');
// }

// if (!$course = $DB->get_record("course", array("id" => $cm->course))) {
//     throw new moodle_exception('coursemisconf');
// }

// if (!$module = $DB->get_record('hippotrack', ['id' => $cm->instance])) {
//     throw new moodle_exception('DataBase for hippotrack not found');
// }



$PAGE->set_context(context_module::instance($cm->id));
$PAGE->set_cm($cm);
$PAGE->activityheader->set_description('');




$myURL = new moodle_url('/mod/easyvote/exercise.php');
$PAGE->set_url($myURL);





// Le formulaire n'a pas été soumis ni annulé, donc il faut l'afficher (on a chargé la page normalement)
echo $OUTPUT->header();

echo 'ceci est la page exercise';
echo $OUTPUT->footer();




