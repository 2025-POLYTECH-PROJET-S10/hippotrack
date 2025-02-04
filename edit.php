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
require_once(__DIR__ . '/classes/edit_form.php');
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

// if (!$cmid = get_coursemodule_from_id('hippotrack', $id)) {
//     throw new moodle_exception('invalidcoursemodule');
// }

// if (!$course = $DB->get_record("course", array("id" => $cmid->course))) {
//     throw new moodle_exception('coursemisconf');
// }

// if (!$module = $DB->get_record('hippotrack', ['id' => $cmid->instance])) {
//     throw new moodle_exception('DataBase for hippotrack not found');
// }



$PAGE->set_context(context_module::instance($cmid->id));
$PAGE->set_cm($cmid);
$PAGE->activityheader->set_description('');




$myURL = new moodle_url('/mod/hippotrack/edit.php');
$PAGE->set_url($myURL);


// Le formulaire n'a pas été soumis ni annulé, donc il faut l'afficher (on a chargé la page normalement)
echo $OUTPUT->header();




$form = new \mod_hippotrack\edit_form($PAGE->url, $cmid->id);
// $form = new \mod_easyvote\edit_question_form($PAGE->url);

$formdata = $form->get_data();
if ($formdata !== null) {
    // // Le formulaire a été soumis. On peut traiter les données (i.e. les ajouter à la DB)


    // if ($questionType == ('C' || 'M')) {
    //     if ($questionDB = $DB->get_record('easyvote_questions', ['id' => $questionid])) {
    //         edit_question_update_instance($formdata, $questionDB, $questionid);
    //     } else {
    //         $easyvoteid = get_fast_modinfo($COURSE->id, -1)->get_cm($cmid)->instance;
    //         $questionDB = $DB->get_records('easyvote_questions', ['easyvoteid' => $easyvoteid]);
    //         $questionNum = count($questionDB);

    //         edit_question_add_questions($formdata, $questionNum, $cmid);
    //     }
    // } 


    // On redirige vers view.php.
    redirect('/mod/hippocrate/view.php?id=' . $cmid);
} else if ($form->is_cancelled()) {
    // Le formulaire a été annulé, on redirige vers view.php.
    redirect('/mod/hippocrate/view.php?id=' . $cmid);
}


$PAGE->requires->js_call_amd('mod_hippotrack/edit', 'init', ['cmid' => $cmid]);
$form->display();

echo $OUTPUT->footer();
