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
 * This page is the entry page into the hippotrack UI. Displays information about the
 * hippotrack to students and teachers, and lets students see their previous attempts.
 *
 * @package   mod_hippotrack
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir.'/gradelib.php');
require_once($CFG->dirroot.'/mod/hippotrack/locallib.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->dirroot . '/course/format/lib.php');

$id = optional_param('id', 0, PARAM_INT); // Course Module ID, or ...
$q = optional_param('q',  0, PARAM_INT);  // HippoTrack ID.

if ($id) {
    if (!$cm = get_coursemodule_from_id('hippotrack', $id)) {
        throw new \moodle_exception('invalidcoursemodule');
    }
    if (!$course = $DB->get_record('course', array('id' => $cm->course))) {
        throw new \moodle_exception('coursemisconf');
    }
} else {
    if (!$hippotrack = $DB->get_record('hippotrack', array('id' => $q))) {
        throw new \moodle_exception('invalidhippotrackid', 'hippotrack');
    }
    if (!$course = $DB->get_record('course', array('id' => $hippotrack->course))) {
        throw new \moodle_exception('invalidcourseid');
    }
    if (!$cm = get_coursemodule_from_instance("hippotrack", $hippotrack->id, $course->id)) {
        throw new \moodle_exception('invalidcoursemodule');
    }
}

// Check login and get context.
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/hippotrack:view', $context);

// Cache some other capabilities we use several times.
$canattempt = has_capability('mod/hippotrack:attempt', $context);
$canreviewmine = has_capability('mod/hippotrack:reviewmyattempts', $context);
$canpreview = has_capability('mod/hippotrack:preview', $context);

// Create an object to manage all the other (non-roles) access rules.
$timenow = time();
$hippotrackobj = hippotrack::create($cm->instance, $USER->id);
$accessmanager = new hippotrack_access_manager($hippotrackobj, $timenow,
        has_capability('mod/hippotrack:ignoretimelimits', $context, null, false));
$hippotrack = $hippotrackobj->get_hippotrack();

// Trigger course_module_viewed event and completion.
hippotrack_view($hippotrack, $course, $cm, $context);

// Initialize $PAGE, compute blocks.
$PAGE->set_url('/mod/hippotrack/view.php', array('id' => $cm->id));

// Create view object which collects all the information the renderer will need.
$viewobj = new mod_hippotrack_view_object();
$viewobj->accessmanager = $accessmanager;
$viewobj->canreviewmine = $canreviewmine || $canpreview;

// Get this user's attempts.
$attempts = hippotrack_get_user_attempts($hippotrack->id, $USER->id, 'finished', true);
$lastfinishedattempt = end($attempts);
$unfinished = false;
$unfinishedattemptid = null;
if ($unfinishedattempt = hippotrack_get_user_attempt_unfinished($hippotrack->id, $USER->id)) {
    $attempts[] = $unfinishedattempt;

    // If the attempt is now overdue, deal with that - and pass isonline = false.
    // We want the student notified in this case.
    $hippotrackobj->create_attempt_object($unfinishedattempt)->handle_if_time_expired(time(), false);

    $unfinished = $unfinishedattempt->state == hippotrack_attempt::IN_PROGRESS ||
            $unfinishedattempt->state == hippotrack_attempt::OVERDUE;
    if (!$unfinished) {
        $lastfinishedattempt = $unfinishedattempt;
    }
    $unfinishedattemptid = $unfinishedattempt->id;
    $unfinishedattempt = null; // To make it clear we do not use this again.
}
$numattempts = count($attempts);

$viewobj->attempts = $attempts;
$viewobj->attemptobjs = array();
foreach ($attempts as $attempt) {
    $viewobj->attemptobjs[] = new hippotrack_attempt($attempt, $hippotrack, $cm, $course, false);
}

// Work out the final grade, checking whether it was overridden in the gradebook.
if (!$canpreview) {
    $mygrade = hippotrack_get_best_grade($hippotrack, $USER->id);
} else if ($lastfinishedattempt) {
    // Users who can preview the hippotrack don't get a proper grade, so work out a
    // plausible value to display instead, so the page looks right.
    $mygrade = hippotrack_rescale_grade($lastfinishedattempt->sumgrades, $hippotrack, false);
} else {
    $mygrade = null;
}

$mygradeoverridden = false;
$gradebookfeedback = '';

$item = null;

$grading_info = grade_get_grades($course->id, 'mod', 'hippotrack', $hippotrack->id, $USER->id);
if (!empty($grading_info->items)) {
    $item = $grading_info->items[0];
    if (isset($item->grades[$USER->id])) {
        $grade = $item->grades[$USER->id];

        if ($grade->overridden) {
            $mygrade = $grade->grade + 0; // Convert to number.
            $mygradeoverridden = true;
        }
        if (!empty($grade->str_feedback)) {
            $gradebookfeedback = $grade->str_feedback;
        }
    }
}

$title = $course->shortname . ': ' . format_string($hippotrack->name);
$PAGE->set_title($title);
$PAGE->set_heading($course->fullname);
if (html_is_blank($hippotrack->intro)) {
    $PAGE->activityheader->set_description('');
}
$PAGE->add_body_class('limitedwidth');
/** @var mod_hippotrack_renderer $output */
$output = $PAGE->get_renderer('mod_hippotrack');

// Print table with existing attempts.
if ($attempts) {
    // Work out which columns we need, taking account what data is available in each attempt.
    list($someoptions, $alloptions) = hippotrack_get_combined_reviewoptions($hippotrack, $attempts);

    $viewobj->attemptcolumn  = $hippotrack->attempts != 1;

    $viewobj->gradecolumn    = $someoptions->marks >= question_display_options::MARK_AND_MAX &&
            hippotrack_has_grades($hippotrack);
    $viewobj->markcolumn     = $viewobj->gradecolumn && ($hippotrack->grade != $hippotrack->sumgrades);
    $viewobj->overallstats   = $lastfinishedattempt && $alloptions->marks >= question_display_options::MARK_AND_MAX;

    $viewobj->feedbackcolumn = hippotrack_has_feedback($hippotrack) && $alloptions->overallfeedback;
}

$viewobj->timenow = $timenow;
$viewobj->numattempts = $numattempts;
$viewobj->mygrade = $mygrade;
$viewobj->moreattempts = $unfinished ||
        !$accessmanager->is_finished($numattempts, $lastfinishedattempt);
$viewobj->mygradeoverridden = $mygradeoverridden;
$viewobj->gradebookfeedback = $gradebookfeedback;
$viewobj->lastfinishedattempt = $lastfinishedattempt;
$viewobj->canedit = has_capability('mod/hippotrack:manage', $context);
$viewobj->editurl = new moodle_url('/mod/hippotrack/edit.php', array('cmid' => $cm->id));
$viewobj->backtocourseurl = new moodle_url('/course/view.php', array('id' => $course->id));
$viewobj->startattempturl = $hippotrackobj->start_attempt_url();

if ($accessmanager->is_preflight_check_required($unfinishedattemptid)) {
    $viewobj->preflightcheckform = $accessmanager->get_preflight_check_form(
            $viewobj->startattempturl, $unfinishedattemptid);
}
$viewobj->popuprequired = $accessmanager->attempt_must_be_in_popup();
$viewobj->popupoptions = $accessmanager->get_popup_options();

// Display information about this hippotrack.
$viewobj->infomessages = $viewobj->accessmanager->describe_rules();
if ($hippotrack->attempts != 1) {
    $viewobj->infomessages[] = get_string('gradingmethod', 'hippotrack',
            hippotrack_get_grading_option_name($hippotrack->grademethod));
}

// Inform user of the grade to pass if non-zero.
if ($item && grade_floats_different($item->gradepass, 0)) {
    $a = new stdClass();
    $a->grade = hippotrack_format_grade($hippotrack, $item->gradepass);
    $a->maxgrade = hippotrack_format_grade($hippotrack, $hippotrack->grade);
    $viewobj->infomessages[] = get_string('gradetopassoutof', 'hippotrack', $a);
}

// Determine wheter a start attempt button should be displayed.
$viewobj->hippotrackhasquestions = $hippotrackobj->has_questions();
$viewobj->preventmessages = array();
if (!$viewobj->hippotrackhasquestions) {
    $viewobj->buttontext = '';

} else {
    if ($unfinished) {
        if ($canpreview) {
            $viewobj->buttontext = get_string('continuepreview', 'hippotrack');
        } else if ($canattempt) {
            $viewobj->buttontext = get_string('continueattempthippotrack', 'hippotrack');
        }
    } else {
        if ($canpreview) {
            $viewobj->buttontext = get_string('previewhippotrackstart', 'hippotrack');
        } else if ($canattempt) {
            $viewobj->preventmessages = $viewobj->accessmanager->prevent_new_attempt(
                    $viewobj->numattempts, $viewobj->lastfinishedattempt);
            if ($viewobj->preventmessages) {
                $viewobj->buttontext = '';
            } else if ($viewobj->numattempts == 0) {
                $viewobj->buttontext = get_string('attempthippotrack', 'hippotrack');
            } else {
                $viewobj->buttontext = get_string('reattempthippotrack', 'hippotrack');
            }
        }
    }

    // Users who can preview the hippotrack should be able to see all messages for not being able to access the hippotrack.
    if ($canpreview) {
        $viewobj->preventmessages = $viewobj->accessmanager->prevent_access();
    } else if ($viewobj->buttontext) {
        // If, so far, we think a button should be printed, so check if they will be allowed to access it.
        if (!$viewobj->moreattempts) {
            $viewobj->buttontext = '';
        } else if ($canattempt) {
            $viewobj->preventmessages = $viewobj->accessmanager->prevent_access();
            if ($viewobj->preventmessages) {
                $viewobj->buttontext = '';
            }
        }
    }
}

$viewobj->showbacktocourse = ($viewobj->buttontext === '' &&
        course_get_format($course)->has_view_page());

echo $OUTPUT->header();

if (isguestuser()) {
    // Guests can't do a hippotrack, so offer them a choice of logging in or going back.
    echo $output->view_page_guest($course, $hippotrack, $cm, $context, $viewobj->infomessages, $viewobj);
} else if (!isguestuser() && !($canattempt || $canpreview
          || $viewobj->canreviewmine)) {
    // If they are not enrolled in this course in a good enough role, tell them to enrol.
    echo $output->view_page_notenrolled($course, $hippotrack, $cm, $context, $viewobj->infomessages, $viewobj);
} else {
    echo $output->view_page($course, $hippotrack, $cm, $context, $viewobj);
}

echo $OUTPUT->footer();
