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

global $PAGE, $OUTPUT, $DB;

$cmid = required_param('id', PARAM_INT);
$cmid = required_param('cmid', PARAM_INT);

$cm = get_coursemodule_from_id('hippotrack', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);

$PAGE->set_context(context_module::instance($cmid));
$PAGE->set_cm($cm);
$PAGE->set_url(new moodle_url('/mod/hippotrack/edit.php', ['cmid' => $cmid]));

// Load JavaScript module
$PAGE->requires->js_call_amd('mod_hippotrack/edit', 'init');

echo $OUTPUT->header();

// Display form
$form = new \mod_hippotrack\edit_form($PAGE->url, $cmid);

if ($form->is_cancelled()) {
    redirect('/mod/hippotrack/view.php?id=' . $cmid);
} elseif ($formdata = $form->get_data()) {
    redirect('/mod/hippotrack/view.php?id=' . $cmid);
}

$form->display();
echo $OUTPUT->footer();
