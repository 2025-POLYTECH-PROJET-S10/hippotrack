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
 * Page to edit hippotrackzes
 *
 * This page generally has two columns:
 * The right column lists all available questions in a chosen category and
 * allows them to be edited or more to be added. This column is only there if
 * the hippotrack does not already have student attempts
 * The left column lists all questions that have been added to the current hippotrack.
 * The lecturer can add questions from the right hand list to the hippotrack or remove them
 *
 * The script also processes a number of actions:
 * Actions affecting a hippotrack:
 * up and down  Changes the order of questions and page breaks
 * addquestion  Adds a single question to the hippotrack
 * add          Adds several selected questions to the hippotrack
 * addrandom    Adds a certain number of random questions to the hippotrack
 * repaginate   Re-paginates the hippotrack
 * delete       Removes a question from the hippotrack
 * savechanges  Saves the order and grades for questions in the hippotrack
 *
 * @package    mod_hippotrack
 * @copyright  1999 onwards Martin Dougiamas and others {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/hippotrack/locallib.php');
require_once($CFG->dirroot . '/mod/hippotrack/addrandomform.php');
require_once($CFG->dirroot . '/question/editlib.php');

// These params are only passed from page request to request while we stay on
// this page otherwise they would go in question_edit_setup.
$scrollpos = optional_param('scrollpos', '', PARAM_INT);

list($thispageurl, $contexts, $cmid, $cm, $hippotrack, $pagevars) =
        question_edit_setup('editq', '/mod/hippotrack/edit.php', true);

$defaultcategoryobj = question_make_default_categories($contexts->all());
$defaultcategory = $defaultcategoryobj->id . ',' . $defaultcategoryobj->contextid;

$hippotrackhasattempts = hippotrack_has_attempts($hippotrack->id);

$PAGE->set_url($thispageurl);
$PAGE->set_secondary_active_tab("mod_hippotrack_edit");

// Get the course object and related bits.
$course = $DB->get_record('course', array('id' => $hippotrack->course), '*', MUST_EXIST);
$hippotrackobj = new hippotrack($hippotrack, $cm, $course);
$structure = $hippotrackobj->get_structure();

// You need mod/hippotrack:manage in addition to question capabilities to access this page.
require_capability('mod/hippotrack:manage', $contexts->lowest());

// Process commands ============================================================.

// Get the list of question ids had their check-boxes ticked.
$selectedslots = array();
$params = (array) data_submitted();
foreach ($params as $key => $value) {
    if (preg_match('!^s([0-9]+)$!', $key, $matches)) {
        $selectedslots[] = $matches[1];
    }
}

$afteractionurl = new moodle_url($thispageurl);
if ($scrollpos) {
    $afteractionurl->param('scrollpos', $scrollpos);
}

if (optional_param('repaginate', false, PARAM_BOOL) && confirm_sesskey()) {
    // Re-paginate the hippotrack.
    $structure->check_can_be_edited();
    $questionsperpage = optional_param('questionsperpage', $hippotrack->questionsperpage, PARAM_INT);
    hippotrack_repaginate_questions($hippotrack->id, $questionsperpage );
    hippotrack_delete_previews($hippotrack);
    redirect($afteractionurl);
}

if (($addquestion = optional_param('addquestion', 0, PARAM_INT)) && confirm_sesskey()) {
    // Add a single question to the current hippotrack.
    $structure->check_can_be_edited();
    hippotrack_require_question_use($addquestion);
    $addonpage = optional_param('addonpage', 0, PARAM_INT);
    hippotrack_add_hippotrack_question($addquestion, $hippotrack, $addonpage);
    hippotrack_delete_previews($hippotrack);
    hippotrack_update_sumgrades($hippotrack);
    $thispageurl->param('lastchanged', $addquestion);
    redirect($afteractionurl);
}

if (optional_param('add', false, PARAM_BOOL) && confirm_sesskey()) {
    $structure->check_can_be_edited();
    $addonpage = optional_param('addonpage', 0, PARAM_INT);
    // Add selected questions to the current hippotrack.
    $rawdata = (array) data_submitted();
    foreach ($rawdata as $key => $value) { // Parse input for question ids.
        if (preg_match('!^q([0-9]+)$!', $key, $matches)) {
            $key = $matches[1];
            hippotrack_require_question_use($key);
            hippotrack_add_hippotrack_question($key, $hippotrack, $addonpage);
        }
    }
    hippotrack_delete_previews($hippotrack);
    hippotrack_update_sumgrades($hippotrack);
    redirect($afteractionurl);
}

if ($addsectionatpage = optional_param('addsectionatpage', false, PARAM_INT)) {
    // Add a section to the hippotrack.
    $structure->check_can_be_edited();
    $structure->add_section_heading($addsectionatpage);
    hippotrack_delete_previews($hippotrack);
    redirect($afteractionurl);
}

if ((optional_param('addrandom', false, PARAM_BOOL)) && confirm_sesskey()) {
    // Add random questions to the hippotrack.
    $structure->check_can_be_edited();
    $recurse = optional_param('recurse', 0, PARAM_BOOL);
    $addonpage = optional_param('addonpage', 0, PARAM_INT);
    $categoryid = required_param('categoryid', PARAM_INT);
    $randomcount = required_param('randomcount', PARAM_INT);
    hippotrack_add_random_questions($hippotrack, $addonpage, $categoryid, $randomcount, $recurse);

    hippotrack_delete_previews($hippotrack);
    hippotrack_update_sumgrades($hippotrack);
    redirect($afteractionurl);
}

if (optional_param('savechanges', false, PARAM_BOOL) && confirm_sesskey()) {

    // If rescaling is required save the new maximum.
    $maxgrade = unformat_float(optional_param('maxgrade', '', PARAM_RAW_TRIMMED), true);
    if (is_float($maxgrade) && $maxgrade >= 0) {
        hippotrack_set_grade($maxgrade, $hippotrack);
        hippotrack_update_all_final_grades($hippotrack);
        hippotrack_update_grades($hippotrack, 0, true);
    }

    redirect($afteractionurl);
}

// Log this visit.
$event = \mod_hippotrack\event\edit_page_viewed::create([
    'courseid' => $course->id,
    'context' => $contexts->lowest(),
    'other' => [
        'hippotrackid' => $hippotrack->id
    ]
]);
$event->trigger();

// Get the question bank view.
$questionbank = new mod_hippotrack\question\bank\custom_view($contexts, $thispageurl, $course, $cm, $hippotrack);
$questionbank->set_hippotrack_has_attempts($hippotrackhasattempts);

// End of process commands =====================================================.

$PAGE->set_pagelayout('incourse');
$PAGE->set_pagetype('mod-hippotrack-edit');

$output = $PAGE->get_renderer('mod_hippotrack', 'edit');

$PAGE->set_title(get_string('editinghippotrackx', 'hippotrack', format_string($hippotrack->name)));
$PAGE->set_heading($course->fullname);
$PAGE->activityheader->disable();
$node = $PAGE->settingsnav->find('mod_hippotrack_edit', navigation_node::TYPE_SETTING);
if ($node) {
    $node->make_active();
}
echo $OUTPUT->header();

// Initialise the JavaScript.
$hippotrackeditconfig = new stdClass();
$hippotrackeditconfig->url = $thispageurl->out(true, array('qbanktool' => '0'));
$hippotrackeditconfig->dialoglisteners = array();
$numberoflisteners = $DB->get_field_sql("
    SELECT COALESCE(MAX(page), 1)
      FROM {hippotrack_slots}
     WHERE hippotrackid = ?", array($hippotrack->id));

for ($pageiter = 1; $pageiter <= $numberoflisteners; $pageiter++) {
    $hippotrackeditconfig->dialoglisteners[] = 'addrandomdialoglaunch_' . $pageiter;
}

$PAGE->requires->data_for_js('hippotrack_edit_config', $hippotrackeditconfig);
$PAGE->requires->js('/question/qengine.js');

// Questions wrapper start.
echo html_writer::start_tag('div', array('class' => 'mod-hippotrack-edit-content'));

echo $output->edit_page($hippotrackobj, $structure, $contexts, $thispageurl, $pagevars);

// Questions wrapper end.
echo html_writer::end_tag('div');

echo $OUTPUT->footer();
