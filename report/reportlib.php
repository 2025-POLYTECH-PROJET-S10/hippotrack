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
 * Helper functions for the hippotrack reports.
 *
 * @package   mod_hippotrack
 * @copyright 2008 Jamie Pratt
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/hippotrack/lib.php');
require_once($CFG->dirroot . '/mod/hippotrack/attemptlib.php');
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->dirroot . '/mod/hippotrack/accessmanager.php');

/**
 * Takes an array of objects and constructs a multidimensional array keyed by
 * the keys it finds on the object.
 * @param array $datum an array of objects with properties on the object
 * including the keys passed as the next param.
 * @param array $keys Array of strings with the names of the properties on the
 * objects in datum that you want to index the multidimensional array by.
 * @param bool $keysunique If there is not only one object for each
 * combination of keys you are using you should set $keysunique to true.
 * Otherwise all the object will be added to a zero based array. So the array
 * returned will have count($keys) + 1 indexs.
 * @return array multidimensional array properly indexed.
 */
function hippotrack_report_index_by_keys($datum, $keys, $keysunique = true) {
    if (!$datum) {
        return array();
    }
    $key = array_shift($keys);
    $datumkeyed = array();
    foreach ($datum as $data) {
        if ($keys || !$keysunique) {
            $datumkeyed[$data->{$key}][]= $data;
        } else {
            $datumkeyed[$data->{$key}]= $data;
        }
    }
    if ($keys) {
        foreach ($datumkeyed as $datakey => $datakeyed) {
            $datumkeyed[$datakey] = hippotrack_report_index_by_keys($datakeyed, $keys, $keysunique);
        }
    }
    return $datumkeyed;
}

function hippotrack_report_unindex($datum) {
    if (!$datum) {
        return $datum;
    }
    $datumunkeyed = array();
    foreach ($datum as $value) {
        if (is_array($value)) {
            $datumunkeyed = array_merge($datumunkeyed, hippotrack_report_unindex($value));
        } else {
            $datumunkeyed[] = $value;
        }
    }
    return $datumunkeyed;
}

/**
 * Are there any questions in this hippotrack?
 * @param int $hippotrackid the hippotrack id.
 */
function hippotrack_has_questions($hippotrackid) {
    global $DB;
    return $DB->record_exists('hippotrack_slots', array('hippotrackid' => $hippotrackid));
}

/**
 * Get the slots of real questions (not descriptions) in this hippotrack, in order.
 * @param object $hippotrack the hippotrack.
 * @return array of slot => objects with fields
 *      ->slot, ->id, ->qtype, ->length, ->number, ->maxmark, ->category (for random questions).
 */
function hippotrack_report_get_significant_questions($hippotrack) {
    $hippotrackobj = \hippotrack::create($hippotrack->id);
    $structure = \mod_hippotrack\structure::create_for_hippotrack($hippotrackobj);
    $slots = $structure->get_slots();

    $qsbyslot = [];
    $number = 1;
    foreach ($slots as $slot) {
        // Ignore 'questions' of zero length.
        if ($slot->length == 0) {
            continue;
        }

        $slotreport = new \stdClass();
        $slotreport->slot = $slot->slot;
        $slotreport->id = $slot->questionid;
        $slotreport->qtype = $slot->qtype;
        $slotreport->length = $slot->length;
        $slotreport->number = $number;
        $number += $slot->length;
        $slotreport->maxmark = $slot->maxmark;
        $slotreport->category = $slot->category;

        $qsbyslot[$slotreport->slot] = $slotreport;
    }

    return $qsbyslot;
}

/**
 * @param object $hippotrack the hippotrack settings.
 * @return bool whether, for this hippotrack, it is possible to filter attempts to show
 *      only those that gave the final grade.
 */
function hippotrack_report_can_filter_only_graded($hippotrack) {
    return $hippotrack->attempts != 1 && $hippotrack->grademethod != HIPPOTRACK_GRADEAVERAGE;
}

/**
 * This is a wrapper for {@link hippotrack_report_grade_method_sql} that takes the whole hippotrack object instead of just the grading method
 * as a param. See definition for {@link hippotrack_report_grade_method_sql} below.
 *
 * @param object $hippotrack
 * @param string $hippotrackattemptsalias sql alias for 'hippotrack_attempts' table
 * @return string sql to test if this is an attempt that will contribute towards the grade of the user
 */
function hippotrack_report_qm_filter_select($hippotrack, $hippotrackattemptsalias = 'hippotracka') {
    if ($hippotrack->attempts == 1) {
        // This hippotrack only allows one attempt.
        return '';
    }
    return hippotrack_report_grade_method_sql($hippotrack->grademethod, $hippotrackattemptsalias);
}

/**
 * Given a hippotrack grading method return sql to test if this is an
 * attempt that will be contribute towards the grade of the user. Or return an
 * empty string if the grading method is HIPPOTRACK_GRADEAVERAGE and thus all attempts
 * contribute to final grade.
 *
 * @param string $grademethod hippotrack grading method.
 * @param string $hippotrackattemptsalias sql alias for 'hippotrack_attempts' table
 * @return string sql to test if this is an attempt that will contribute towards the graded of the user
 */
function hippotrack_report_grade_method_sql($grademethod, $hippotrackattemptsalias = 'hippotracka') {
    switch ($grademethod) {
        case HIPPOTRACK_GRADEHIGHEST :
            return "($hippotrackattemptsalias.state = 'finished' AND NOT EXISTS (
                           SELECT 1 FROM {hippotrack_attempts} qa2
                            WHERE qa2.hippotrack = $hippotrackattemptsalias.hippotrack AND
                                qa2.userid = $hippotrackattemptsalias.userid AND
                                 qa2.state = 'finished' AND (
                COALESCE(qa2.sumgrades, 0) > COALESCE($hippotrackattemptsalias.sumgrades, 0) OR
               (COALESCE(qa2.sumgrades, 0) = COALESCE($hippotrackattemptsalias.sumgrades, 0) AND qa2.attempt < $hippotrackattemptsalias.attempt)
                                )))";

        case HIPPOTRACK_GRADEAVERAGE :
            return '';

        case HIPPOTRACK_ATTEMPTFIRST :
            return "($hippotrackattemptsalias.state = 'finished' AND NOT EXISTS (
                           SELECT 1 FROM {hippotrack_attempts} qa2
                            WHERE qa2.hippotrack = $hippotrackattemptsalias.hippotrack AND
                                qa2.userid = $hippotrackattemptsalias.userid AND
                                 qa2.state = 'finished' AND
                               qa2.attempt < $hippotrackattemptsalias.attempt))";

        case HIPPOTRACK_ATTEMPTLAST :
            return "($hippotrackattemptsalias.state = 'finished' AND NOT EXISTS (
                           SELECT 1 FROM {hippotrack_attempts} qa2
                            WHERE qa2.hippotrack = $hippotrackattemptsalias.hippotrack AND
                                qa2.userid = $hippotrackattemptsalias.userid AND
                                 qa2.state = 'finished' AND
                               qa2.attempt > $hippotrackattemptsalias.attempt))";
    }
}

/**
 * Get the number of students whose score was in a particular band for this hippotrack.
 * @param number $bandwidth the width of each band.
 * @param int $bands the number of bands
 * @param int $hippotrackid the hippotrack id.
 * @param \core\dml\sql_join $usersjoins (joins, wheres, params) to get enrolled users
 * @return array band number => number of users with scores in that band.
 */
function hippotrack_report_grade_bands($bandwidth, $bands, $hippotrackid, \core\dml\sql_join $usersjoins = null) {
    global $DB;
    if (!is_int($bands)) {
        debugging('$bands passed to hippotrack_report_grade_bands must be an integer. (' .
                gettype($bands) . ' passed.)', DEBUG_DEVELOPER);
        $bands = (int) $bands;
    }

    if ($usersjoins && !empty($usersjoins->joins)) {
        $userjoin = "JOIN {user} u ON u.id = qg.userid
                {$usersjoins->joins}";
        $usertest = $usersjoins->wheres;
        $params = $usersjoins->params;
    } else {
        $userjoin = '';
        $usertest = '1=1';
        $params = array();
    }
    $sql = "
SELECT band, COUNT(1)

FROM (
    SELECT FLOOR(qg.grade / :bandwidth) AS band
      FROM {hippotrack_grades} qg
    $userjoin
    WHERE $usertest AND qg.hippotrack = :hippotrackid
) subquery

GROUP BY
    band

ORDER BY
    band";

    $params['hippotrackid'] = $hippotrackid;
    $params['bandwidth'] = $bandwidth;

    $data = $DB->get_records_sql_menu($sql, $params);

    // We need to create array elements with values 0 at indexes where there is no element.
    $data = $data + array_fill(0, $bands + 1, 0);
    ksort($data);

    // Place the maximum (perfect grade) into the last band i.e. make last
    // band for example 9 <= g <=10 (where 10 is the perfect grade) rather than
    // just 9 <= g <10.
    $data[$bands - 1] += $data[$bands];
    unset($data[$bands]);

    // See MDL-60632. When a hippotrack participant achieves an overall negative grade the chart fails to render.
    foreach ($data as $databand => $datanum) {
        if ($databand < 0) {
            $data["0"] += $datanum; // Add to band 0.
            unset($data[$databand]); // Remove entry below 0.
        }
    }

    return $data;
}

function hippotrack_report_highlighting_grading_method($hippotrack, $qmsubselect, $qmfilter) {
    if ($hippotrack->attempts == 1) {
        return '<p>' . get_string('onlyoneattemptallowed', 'hippotrack_overview') . '</p>';

    } else if (!$qmsubselect) {
        return '<p>' . get_string('allattemptscontributetograde', 'hippotrack_overview') . '</p>';

    } else if ($qmfilter) {
        return '<p>' . get_string('showinggraded', 'hippotrack_overview') . '</p>';

    } else {
        return '<p>' . get_string('showinggradedandungraded', 'hippotrack_overview',
                '<span class="gradedattempt">' . hippotrack_get_grading_option_name($hippotrack->grademethod) .
                '</span>') . '</p>';
    }
}

/**
 * Get the feedback text for a grade on this hippotrack. The feedback is
 * processed ready for display.
 *
 * @param float $grade a grade on this hippotrack.
 * @param int $hippotrackid the id of the hippotrack object.
 * @return string the comment that corresponds to this grade (empty string if there is not one.
 */
function hippotrack_report_feedback_for_grade($grade, $hippotrackid, $context) {
    global $DB;

    static $feedbackcache = array();

    if (!isset($feedbackcache[$hippotrackid])) {
        $feedbackcache[$hippotrackid] = $DB->get_records('hippotrack_feedback', array('hippotrackid' => $hippotrackid));
    }

    // With CBM etc, it is possible to get -ve grades, which would then not match
    // any feedback. Therefore, we replace -ve grades with 0.
    $grade = max($grade, 0);

    $feedbacks = $feedbackcache[$hippotrackid];
    $feedbackid = 0;
    $feedbacktext = '';
    $feedbacktextformat = FORMAT_MOODLE;
    foreach ($feedbacks as $feedback) {
        if ($feedback->mingrade <= $grade && $grade < $feedback->maxgrade) {
            $feedbackid = $feedback->id;
            $feedbacktext = $feedback->feedbacktext;
            $feedbacktextformat = $feedback->feedbacktextformat;
            break;
        }
    }

    // Clean the text, ready for display.
    $formatoptions = new stdClass();
    $formatoptions->noclean = true;
    $feedbacktext = file_rewrite_pluginfile_urls($feedbacktext, 'pluginfile.php',
            $context->id, 'mod_hippotrack', 'feedback', $feedbackid);
    $feedbacktext = format_text($feedbacktext, $feedbacktextformat, $formatoptions);

    return $feedbacktext;
}

/**
 * Format a number as a percentage out of $hippotrack->sumgrades
 * @param number $rawgrade the mark to format.
 * @param object $hippotrack the hippotrack settings
 * @param bool $round whether to round the results ot $hippotrack->decimalpoints.
 */
function hippotrack_report_scale_summarks_as_percentage($rawmark, $hippotrack, $round = true) {
    if ($hippotrack->sumgrades == 0) {
        return '';
    }
    if (!is_numeric($rawmark)) {
        return $rawmark;
    }

    $mark = $rawmark * 100 / $hippotrack->sumgrades;
    if ($round) {
        $mark = hippotrack_format_grade($hippotrack, $mark);
    }

    return get_string('percents', 'moodle', $mark);
}

/**
 * Returns an array of reports to which the current user has access to.
 * @return array reports are ordered as they should be for display in tabs.
 */
function hippotrack_report_list($context) {
    global $DB;
    static $reportlist = null;
    if (!is_null($reportlist)) {
        return $reportlist;
    }

    $reports = $DB->get_records('hippotrack_reports', null, 'displayorder DESC', 'name, capability');
    $reportdirs = core_component::get_plugin_list('hippotrack');

    // Order the reports tab in descending order of displayorder.
    $reportcaps = array();
    foreach ($reports as $key => $report) {
        if (array_key_exists($report->name, $reportdirs)) {
            $reportcaps[$report->name] = $report->capability;
        }
    }

    // Add any other reports, which are on disc but not in the DB, on the end.
    foreach ($reportdirs as $reportname => $notused) {
        if (!isset($reportcaps[$reportname])) {
            $reportcaps[$reportname] = null;
        }
    }
    $reportlist = array();
    foreach ($reportcaps as $name => $capability) {
        if (empty($capability)) {
            $capability = 'mod/hippotrack:viewreports';
        }
        if (has_capability($capability, $context)) {
            $reportlist[] = $name;
        }
    }
    return $reportlist;
}

/**
 * Create a filename for use when downloading data from a hippotrack report. It is
 * expected that this will be passed to flexible_table::is_downloading, which
 * cleans the filename of bad characters and adds the file extension.
 * @param string $report the type of report.
 * @param string $courseshortname the course shortname.
 * @param string $hippotrackname the hippotrack name.
 * @return string the filename.
 */
function hippotrack_report_download_filename($report, $courseshortname, $hippotrackname) {
    return $courseshortname . '-' . format_string($hippotrackname, true) . '-' . $report;
}

/**
 * Get the default report for the current user.
 * @param object $context the hippotrack context.
 */
function hippotrack_report_default_report($context) {
    $reports = hippotrack_report_list($context);
    return reset($reports);
}

/**
 * Generate a message saying that this hippotrack has no questions, with a button to
 * go to the edit page, if the user has the right capability.
 * @param object $hippotrack the hippotrack settings.
 * @param object $cm the course_module object.
 * @param object $context the hippotrack context.
 * @return string HTML to output.
 */
function hippotrack_no_questions_message($hippotrack, $cm, $context) {
    global $OUTPUT;

    $output = '';
    $output .= $OUTPUT->notification(get_string('noquestions', 'hippotrack'));
    if (has_capability('mod/hippotrack:manage', $context)) {
        $output .= $OUTPUT->single_button(new moodle_url('/mod/hippotrack/edit.php',
        array('cmid' => $cm->id)), get_string('edithippotrack', 'hippotrack'), 'get');
    }

    return $output;
}

/**
 * Should the grades be displayed in this report. That depends on the hippotrack
 * display options, and whether the hippotrack is graded.
 * @param object $hippotrack the hippotrack settings.
 * @param context $context the hippotrack context.
 * @return bool
 */
function hippotrack_report_should_show_grades($hippotrack, context $context) {
    if ($hippotrack->timeclose && time() > $hippotrack->timeclose) {
        $when = mod_hippotrack_display_options::AFTER_CLOSE;
    } else {
        $when = mod_hippotrack_display_options::LATER_WHILE_OPEN;
    }
    $reviewoptions = mod_hippotrack_display_options::make_from_hippotrack($hippotrack, $when);

    return hippotrack_has_grades($hippotrack) &&
            ($reviewoptions->marks >= question_display_options::MARK_AND_MAX ||
            has_capability('moodle/grade:viewhidden', $context));
}
