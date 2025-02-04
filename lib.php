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
 * Library of functions for the hippotrack module.
 *
 * This contains functions that are called also from outside the hippotrack module
 * Functions that are only called by the hippotrack module itself are in {@link locallib.php}
 *
 * @package    mod_hippotrack
 * @copyright  1999 onwards Martin Dougiamas {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

use mod_hippotrack\question\bank\custom_view;
use core_question\statistics\questions\all_calculated_for_qubaid_condition;

require_once($CFG->dirroot . '/calendar/lib.php');


/**#@+
 * Option controlling what options are offered on the hippotrack settings form.
 */
define('HIPPOTRACK_MAX_ATTEMPT_OPTION', 10);
define('HIPPOTRACK_MAX_QPP_OPTION', 50);
define('HIPPOTRACK_MAX_DECIMAL_OPTION', 5);
define('HIPPOTRACK_MAX_Q_DECIMAL_OPTION', 7);
/**#@-*/

/**#@+
 * Options determining how the grades from individual attempts are combined to give
 * the overall grade for a user
 */
define('HIPPOTRACK_GRADEHIGHEST', '1');
define('HIPPOTRACK_GRADEAVERAGE', '2');
define('HIPPOTRACK_ATTEMPTFIRST', '3');
define('HIPPOTRACK_ATTEMPTLAST',  '4');
/**#@-*/

/**
 * @var int If start and end date for the hippotrack are more than this many seconds apart
 * they will be represented by two separate events in the calendar
 */
define('HIPPOTRACK_MAX_EVENT_LENGTH', 5*24*60*60); // 5 days.

/**#@+
 * Options for navigation method within hippotrackzes.
 */
define('HIPPOTRACK_NAVMETHOD_FREE', 'free');
define('HIPPOTRACK_NAVMETHOD_SEQ',  'sequential');
/**#@-*/

/**
 * Event types.
 */
define('HIPPOTRACK_EVENT_TYPE_OPEN', 'open');
define('HIPPOTRACK_EVENT_TYPE_CLOSE', 'close');

require_once(__DIR__ . '/deprecatedlib.php');

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @param object $hippotrack the data that came from the form.
 * @return mixed the id of the new instance on success,
 *          false or a string error message on failure.
 */
function hippotrack_add_instance($hippotrack) {
    global $DB;
    $cmid = $hippotrack->coursemodule;

    // Process the options from the form.
    $hippotrack->timecreated = time();
    $result = hippotrack_process_options($hippotrack);
    if ($result && is_string($result)) {
        return $result;
    }

    // Try to store it in the database.
    $hippotrack->id = $DB->insert_record('hippotrack', $hippotrack);

    // Create the first section for this hippotrack.
    $DB->insert_record('hippotrack_sections', array('hippotrackid' => $hippotrack->id,
            'firstslot' => 1, 'heading' => '', 'shufflequestions' => 0));

    // Do the processing required after an add or an update.
    hippotrack_after_add_or_update($hippotrack);

    return $hippotrack->id;
}

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @param object $hippotrack the data that came from the form.
 * @return mixed true on success, false or a string error message on failure.
 */
function hippotrack_update_instance($hippotrack, $mform) {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/mod/hippotrack/locallib.php');

    // Process the options from the form.
    $result = hippotrack_process_options($hippotrack);
    if ($result && is_string($result)) {
        return $result;
    }

    // Get the current value, so we can see what changed.
    $oldhippotrack = $DB->get_record('hippotrack', array('id' => $hippotrack->instance));

    // We need two values from the existing DB record that are not in the form,
    // in some of the function calls below.
    $hippotrack->sumgrades = $oldhippotrack->sumgrades;
    $hippotrack->grade     = $oldhippotrack->grade;

    // Update the database.
    $hippotrack->id = $hippotrack->instance;
    $DB->update_record('hippotrack', $hippotrack);

    // Do the processing required after an add or an update.
    hippotrack_after_add_or_update($hippotrack);

    if ($oldhippotrack->grademethod != $hippotrack->grademethod) {
        hippotrack_update_all_final_grades($hippotrack);
        hippotrack_update_grades($hippotrack);
    }

    $hippotrackdateschanged = $oldhippotrack->timelimit   != $hippotrack->timelimit
                     || $oldhippotrack->timeclose   != $hippotrack->timeclose
                     || $oldhippotrack->graceperiod != $hippotrack->graceperiod;
    if ($hippotrackdateschanged) {
        hippotrack_update_open_attempts(array('hippotrackid' => $hippotrack->id));
    }

    // Delete any previous preview attempts.
    hippotrack_delete_previews($hippotrack);

    // Repaginate, if asked to.
    if (!empty($hippotrack->repaginatenow) && !hippotrack_has_attempts($hippotrack->id)) {
        hippotrack_repaginate_questions($hippotrack->id, $hippotrack->questionsperpage);
    }

    return true;
}

/**
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id the id of the hippotrack to delete.
 * @return bool success or failure.
 */
function hippotrack_delete_instance($id) {
    global $DB;

    $hippotrack = $DB->get_record('hippotrack', array('id' => $id), '*', MUST_EXIST);

    hippotrack_delete_all_attempts($hippotrack);
    hippotrack_delete_all_overrides($hippotrack);
    hippotrack_delete_references($hippotrack->id);

    // We need to do the following deletes before we try and delete randoms, otherwise they would still be 'in use'.
    $DB->delete_records('hippotrack_slots', array('hippotrackid' => $hippotrack->id));
    $DB->delete_records('hippotrack_sections', array('hippotrackid' => $hippotrack->id));

    $DB->delete_records('hippotrack_feedback', array('hippotrackid' => $hippotrack->id));

    hippotrack_access_manager::delete_settings($hippotrack);

    $events = $DB->get_records('event', array('modulename' => 'hippotrack', 'instance' => $hippotrack->id));
    foreach ($events as $event) {
        $event = calendar_event::load($event);
        $event->delete();
    }

    hippotrack_grade_item_delete($hippotrack);
    // We must delete the module record after we delete the grade item.
    $DB->delete_records('hippotrack', array('id' => $hippotrack->id));

    return true;
}

/**
 * Deletes a hippotrack override from the database and clears any corresponding calendar events
 *
 * @param object $hippotrack The hippotrack object.
 * @param int $overrideid The id of the override being deleted
 * @param bool $log Whether to trigger logs.
 * @return bool true on success
 */
function hippotrack_delete_override($hippotrack, $overrideid, $log = true) {
    global $DB;

    if (!isset($hippotrack->cmid)) {
        $cm = get_coursemodule_from_instance('hippotrack', $hippotrack->id, $hippotrack->course);
        $hippotrack->cmid = $cm->id;
    }

    $override = $DB->get_record('hippotrack_overrides', array('id' => $overrideid), '*', MUST_EXIST);

    // Delete the events.
    if (isset($override->groupid)) {
        // Create the search array for a group override.
        $eventsearcharray = array('modulename' => 'hippotrack',
            'instance' => $hippotrack->id, 'groupid' => (int)$override->groupid);
        $cachekey = "{$hippotrack->id}_g_{$override->groupid}";
    } else {
        // Create the search array for a user override.
        $eventsearcharray = array('modulename' => 'hippotrack',
            'instance' => $hippotrack->id, 'userid' => (int)$override->userid);
        $cachekey = "{$hippotrack->id}_u_{$override->userid}";
    }
    $events = $DB->get_records('event', $eventsearcharray);
    foreach ($events as $event) {
        $eventold = calendar_event::load($event);
        $eventold->delete();
    }

    $DB->delete_records('hippotrack_overrides', array('id' => $overrideid));
    cache::make('mod_hippotrack', 'overrides')->delete($cachekey);

    if ($log) {
        // Set the common parameters for one of the events we will be triggering.
        $params = array(
            'objectid' => $override->id,
            'context' => context_module::instance($hippotrack->cmid),
            'other' => array(
                'hippotrackid' => $override->hippotrack
            )
        );
        // Determine which override deleted event to fire.
        if (!empty($override->userid)) {
            $params['relateduserid'] = $override->userid;
            $event = \mod_hippotrack\event\user_override_deleted::create($params);
        } else {
            $params['other']['groupid'] = $override->groupid;
            $event = \mod_hippotrack\event\group_override_deleted::create($params);
        }

        // Trigger the override deleted event.
        $event->add_record_snapshot('hippotrack_overrides', $override);
        $event->trigger();
    }

    return true;
}

/**
 * Deletes all hippotrack overrides from the database and clears any corresponding calendar events
 *
 * @param object $hippotrack The hippotrack object.
 * @param bool $log Whether to trigger logs.
 */
function hippotrack_delete_all_overrides($hippotrack, $log = true) {
    global $DB;

    $overrides = $DB->get_records('hippotrack_overrides', array('hippotrack' => $hippotrack->id), 'id');
    foreach ($overrides as $override) {
        hippotrack_delete_override($hippotrack, $override->id, $log);
    }
}

/**
 * Updates a hippotrack object with override information for a user.
 *
 * Algorithm:  For each hippotrack setting, if there is a matching user-specific override,
 *   then use that otherwise, if there are group-specific overrides, return the most
 *   lenient combination of them.  If neither applies, leave the hippotrack setting unchanged.
 *
 *   Special case: if there is more than one password that applies to the user, then
 *   hippotrack->extrapasswords will contain an array of strings giving the remaining
 *   passwords.
 *
 * @param object $hippotrack The hippotrack object.
 * @param int $userid The userid.
 * @return object $hippotrack The updated hippotrack object.
 */
function hippotrack_update_effective_access($hippotrack, $userid) {
    global $DB;

    // Check for user override.
    $override = $DB->get_record('hippotrack_overrides', array('hippotrack' => $hippotrack->id, 'userid' => $userid));

    if (!$override) {
        $override = new stdClass();
        $override->timeopen = null;
        $override->timeclose = null;
        $override->timelimit = null;
        $override->attempts = null;
        $override->password = null;
    }

    // Check for group overrides.
    $groupings = groups_get_user_groups($hippotrack->course, $userid);

    if (!empty($groupings[0])) {
        // Select all overrides that apply to the User's groups.
        list($extra, $params) = $DB->get_in_or_equal(array_values($groupings[0]));
        $sql = "SELECT * FROM {hippotrack_overrides}
                WHERE groupid $extra AND hippotrack = ?";
        $params[] = $hippotrack->id;
        $records = $DB->get_records_sql($sql, $params);

        // Combine the overrides.
        $opens = array();
        $closes = array();
        $limits = array();
        $attempts = array();
        $passwords = array();

        foreach ($records as $gpoverride) {
            if (isset($gpoverride->timeopen)) {
                $opens[] = $gpoverride->timeopen;
            }
            if (isset($gpoverride->timeclose)) {
                $closes[] = $gpoverride->timeclose;
            }
            if (isset($gpoverride->timelimit)) {
                $limits[] = $gpoverride->timelimit;
            }
            if (isset($gpoverride->attempts)) {
                $attempts[] = $gpoverride->attempts;
            }
            if (isset($gpoverride->password)) {
                $passwords[] = $gpoverride->password;
            }
        }
        // If there is a user override for a setting, ignore the group override.
        if (is_null($override->timeopen) && count($opens)) {
            $override->timeopen = min($opens);
        }
        if (is_null($override->timeclose) && count($closes)) {
            if (in_array(0, $closes)) {
                $override->timeclose = 0;
            } else {
                $override->timeclose = max($closes);
            }
        }
        if (is_null($override->timelimit) && count($limits)) {
            if (in_array(0, $limits)) {
                $override->timelimit = 0;
            } else {
                $override->timelimit = max($limits);
            }
        }
        if (is_null($override->attempts) && count($attempts)) {
            if (in_array(0, $attempts)) {
                $override->attempts = 0;
            } else {
                $override->attempts = max($attempts);
            }
        }
        if (is_null($override->password) && count($passwords)) {
            $override->password = array_shift($passwords);
            if (count($passwords)) {
                $override->extrapasswords = $passwords;
            }
        }

    }

    // Merge with hippotrack defaults.
    $keys = array('timeopen', 'timeclose', 'timelimit', 'attempts', 'password', 'extrapasswords');
    foreach ($keys as $key) {
        if (isset($override->{$key})) {
            $hippotrack->{$key} = $override->{$key};
        }
    }

    return $hippotrack;
}

/**
 * Delete all the attempts belonging to a hippotrack.
 *
 * @param object $hippotrack The hippotrack object.
 */
function hippotrack_delete_all_attempts($hippotrack) {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/mod/hippotrack/locallib.php');
    question_engine::delete_questions_usage_by_activities(new qubaids_for_hippotrack($hippotrack->id));
    $DB->delete_records('hippotrack_attempts', array('hippotrack' => $hippotrack->id));
    $DB->delete_records('hippotrack_grades', array('hippotrack' => $hippotrack->id));
}

/**
 * Delete all the attempts belonging to a user in a particular hippotrack.
 *
 * @param object $hippotrack The hippotrack object.
 * @param object $user The user object.
 */
function hippotrack_delete_user_attempts($hippotrack, $user) {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/mod/hippotrack/locallib.php');
    question_engine::delete_questions_usage_by_activities(new qubaids_for_hippotrack_user($hippotrack->get_hippotrackid(), $user->id));
    $params = [
        'hippotrack' => $hippotrack->get_hippotrackid(),
        'userid' => $user->id,
    ];
    $DB->delete_records('hippotrack_attempts', $params);
    $DB->delete_records('hippotrack_grades', $params);
}

/**
 * Get the best current grade for a particular user in a hippotrack.
 *
 * @param object $hippotrack the hippotrack settings.
 * @param int $userid the id of the user.
 * @return float the user's current grade for this hippotrack, or null if this user does
 * not have a grade on this hippotrack.
 */
function hippotrack_get_best_grade($hippotrack, $userid) {
    global $DB;
    $grade = $DB->get_field('hippotrack_grades', 'grade',
            array('hippotrack' => $hippotrack->id, 'userid' => $userid));

    // Need to detect errors/no result, without catching 0 grades.
    if ($grade === false) {
        return null;
    }

    return $grade + 0; // Convert to number.
}

/**
 * Is this a graded hippotrack? If this method returns true, you can assume that
 * $hippotrack->grade and $hippotrack->sumgrades are non-zero (for example, if you want to
 * divide by them).
 *
 * @param object $hippotrack a row from the hippotrack table.
 * @return bool whether this is a graded hippotrack.
 */
function hippotrack_has_grades($hippotrack) {
    return $hippotrack->grade >= 0.000005 && $hippotrack->sumgrades >= 0.000005;
}

/**
 * Does this hippotrack allow multiple tries?
 *
 * @return bool
 */
function hippotrack_allows_multiple_tries($hippotrack) {
    $bt = question_engine::get_behaviour_type($hippotrack->preferredbehaviour);
    return $bt->allows_multiple_submitted_responses();
}

/**
 * Return a small object with summary information about what a
 * user has done with a given particular instance of this module
 * Used for user activity reports.
 * $return->time = the time they did it
 * $return->info = a short text description
 *
 * @param object $course
 * @param object $user
 * @param object $mod
 * @param object $hippotrack
 * @return object|null
 */
function hippotrack_user_outline($course, $user, $mod, $hippotrack) {
    global $DB, $CFG;
    require_once($CFG->libdir . '/gradelib.php');
    $grades = grade_get_grades($course->id, 'mod', 'hippotrack', $hippotrack->id, $user->id);

    if (empty($grades->items[0]->grades)) {
        return null;
    } else {
        $grade = reset($grades->items[0]->grades);
    }

    $result = new stdClass();
    // If the user can't see hidden grades, don't return that information.
    $gitem = grade_item::fetch(array('id' => $grades->items[0]->id));
    if (!$gitem->hidden || has_capability('moodle/grade:viewhidden', context_course::instance($course->id))) {
        $result->info = get_string('gradenoun') . ': ' . $grade->str_long_grade;
    } else {
        $result->info = get_string('gradenoun') . ': ' . get_string('hidden', 'grades');
    }

    $result->time = grade_get_date_for_user_grade($grade, $user);

    return $result;
}

/**
 * Print a detailed representation of what a  user has done with
 * a given particular instance of this module, for user activity reports.
 *
 * @param object $course
 * @param object $user
 * @param object $mod
 * @param object $hippotrack
 * @return bool
 */
function hippotrack_user_complete($course, $user, $mod, $hippotrack) {
    global $DB, $CFG, $OUTPUT;
    require_once($CFG->libdir . '/gradelib.php');
    require_once($CFG->dirroot . '/mod/hippotrack/locallib.php');

    $grades = grade_get_grades($course->id, 'mod', 'hippotrack', $hippotrack->id, $user->id);
    if (!empty($grades->items[0]->grades)) {
        $grade = reset($grades->items[0]->grades);
        // If the user can't see hidden grades, don't return that information.
        $gitem = grade_item::fetch(array('id' => $grades->items[0]->id));
        if (!$gitem->hidden || has_capability('moodle/grade:viewhidden', context_course::instance($course->id))) {
            echo $OUTPUT->container(get_string('gradenoun').': '.$grade->str_long_grade);
            if ($grade->str_feedback) {
                echo $OUTPUT->container(get_string('feedback').': '.$grade->str_feedback);
            }
        } else {
            echo $OUTPUT->container(get_string('gradenoun') . ': ' . get_string('hidden', 'grades'));
            if ($grade->str_feedback) {
                echo $OUTPUT->container(get_string('feedback').': '.get_string('hidden', 'grades'));
            }
        }
    }

    if ($attempts = $DB->get_records('hippotrack_attempts',
            array('userid' => $user->id, 'hippotrack' => $hippotrack->id), 'attempt')) {
        foreach ($attempts as $attempt) {
            echo get_string('attempt', 'hippotrack', $attempt->attempt) . ': ';
            if ($attempt->state != hippotrack_attempt::FINISHED) {
                echo hippotrack_attempt_state_name($attempt->state);
            } else {
                if (!isset($gitem)) {
                    if (!empty($grades->items[0]->grades)) {
                        $gitem = grade_item::fetch(array('id' => $grades->items[0]->id));
                    } else {
                        $gitem = new stdClass();
                        $gitem->hidden = true;
                    }
                }
                if (!$gitem->hidden || has_capability('moodle/grade:viewhidden', context_course::instance($course->id))) {
                    echo hippotrack_format_grade($hippotrack, $attempt->sumgrades) . '/' . hippotrack_format_grade($hippotrack, $hippotrack->sumgrades);
                } else {
                    echo get_string('hidden', 'grades');
                }
                echo ' - '.userdate($attempt->timefinish).'<br />';
            }
        }
    } else {
        print_string('noattempts', 'hippotrack');
    }

    return true;
}


/**
 * @param int|array $hippotrackids A hippotrack ID, or an array of hippotrack IDs.
 * @param int $userid the userid.
 * @param string $status 'all', 'finished' or 'unfinished' to control
 * @param bool $includepreviews
 * @return array of all the user's attempts at this hippotrack. Returns an empty
 *      array if there are none.
 */
function hippotrack_get_user_attempts($hippotrackids, $userid, $status = 'finished', $includepreviews = false) {
    global $DB, $CFG;
    // TODO MDL-33071 it is very annoying to have to included all of locallib.php
    // just to get the hippotrack_attempt::FINISHED constants, but I will try to sort
    // that out properly for Moodle 2.4. For now, I will just do a quick fix for
    // MDL-33048.
    require_once($CFG->dirroot . '/mod/hippotrack/locallib.php');

    $params = array();
    switch ($status) {
        case 'all':
            $statuscondition = '';
            break;

        case 'finished':
            $statuscondition = ' AND state IN (:state1, :state2)';
            $params['state1'] = hippotrack_attempt::FINISHED;
            $params['state2'] = hippotrack_attempt::ABANDONED;
            break;

        case 'unfinished':
            $statuscondition = ' AND state IN (:state1, :state2)';
            $params['state1'] = hippotrack_attempt::IN_PROGRESS;
            $params['state2'] = hippotrack_attempt::OVERDUE;
            break;
    }

    $hippotrackids = (array) $hippotrackids;
    list($insql, $inparams) = $DB->get_in_or_equal($hippotrackids, SQL_PARAMS_NAMED);
    $params += $inparams;
    $params['userid'] = $userid;

    $previewclause = '';
    if (!$includepreviews) {
        $previewclause = ' AND preview = 0';
    }

    return $DB->get_records_select('hippotrack_attempts',
            "hippotrack $insql AND userid = :userid" . $previewclause . $statuscondition,
            $params, 'hippotrack, attempt ASC');
}

/**
 * Return grade for given user or all users.
 *
 * @param int $hippotrackid id of hippotrack
 * @param int $userid optional user id, 0 means all users
 * @return array array of grades, false if none. These are raw grades. They should
 * be processed with hippotrack_format_grade for display.
 */
function hippotrack_get_user_grades($hippotrack, $userid = 0) {
    global $CFG, $DB;

    $params = array($hippotrack->id);
    $usertest = '';
    if ($userid) {
        $params[] = $userid;
        $usertest = 'AND u.id = ?';
    }
    return $DB->get_records_sql("
            SELECT
                u.id,
                u.id AS userid,
                qg.grade AS rawgrade,
                qg.timemodified AS dategraded,
                MAX(qa.timefinish) AS datesubmitted

            FROM {user} u
            JOIN {hippotrack_grades} qg ON u.id = qg.userid
            JOIN {hippotrack_attempts} qa ON qa.hippotrack = qg.hippotrack AND qa.userid = u.id

            WHERE qg.hippotrack = ?
            $usertest
            GROUP BY u.id, qg.grade, qg.timemodified", $params);
}

/**
 * Round a grade to to the correct number of decimal places, and format it for display.
 *
 * @param object $hippotrack The hippotrack table row, only $hippotrack->decimalpoints is used.
 * @param float $grade The grade to round.
 * @return float
 */
function hippotrack_format_grade($hippotrack, $grade) {
    if (is_null($grade)) {
        return get_string('notyetgraded', 'hippotrack');
    }
    return format_float($grade, $hippotrack->decimalpoints);
}

/**
 * Determine the correct number of decimal places required to format a grade.
 *
 * @param object $hippotrack The hippotrack table row, only $hippotrack->decimalpoints is used.
 * @return integer
 */
function hippotrack_get_grade_format($hippotrack) {
    if (empty($hippotrack->questiondecimalpoints)) {
        $hippotrack->questiondecimalpoints = -1;
    }

    if ($hippotrack->questiondecimalpoints == -1) {
        return $hippotrack->decimalpoints;
    }

    return $hippotrack->questiondecimalpoints;
}

/**
 * Round a grade to the correct number of decimal places, and format it for display.
 *
 * @param object $hippotrack The hippotrack table row, only $hippotrack->decimalpoints is used.
 * @param float $grade The grade to round.
 * @return float
 */
function hippotrack_format_question_grade($hippotrack, $grade) {
    return format_float($grade, hippotrack_get_grade_format($hippotrack));
}

/**
 * Update grades in central gradebook
 *
 * @category grade
 * @param object $hippotrack the hippotrack settings.
 * @param int $userid specific user only, 0 means all users.
 * @param bool $nullifnone If a single user is specified and $nullifnone is true a grade item with a null rawgrade will be inserted
 */
function hippotrack_update_grades($hippotrack, $userid = 0, $nullifnone = true) {
    global $CFG, $DB;
    require_once($CFG->libdir . '/gradelib.php');

    if ($hippotrack->grade == 0) {
        hippotrack_grade_item_update($hippotrack);

    } else if ($grades = hippotrack_get_user_grades($hippotrack, $userid)) {
        hippotrack_grade_item_update($hippotrack, $grades);

    } else if ($userid && $nullifnone) {
        $grade = new stdClass();
        $grade->userid = $userid;
        $grade->rawgrade = null;
        hippotrack_grade_item_update($hippotrack, $grade);

    } else {
        hippotrack_grade_item_update($hippotrack);
    }
}

/**
 * Create or update the grade item for given hippotrack
 *
 * @category grade
 * @param object $hippotrack object with extra cmidnumber
 * @param mixed $grades optional array/object of grade(s); 'reset' means reset grades in gradebook
 * @return int 0 if ok, error code otherwise
 */
function hippotrack_grade_item_update($hippotrack, $grades = null) {
    global $CFG, $OUTPUT;
    require_once($CFG->dirroot . '/mod/hippotrack/locallib.php');
    require_once($CFG->libdir . '/gradelib.php');

    if (property_exists($hippotrack, 'cmidnumber')) { // May not be always present.
        $params = array('itemname' => $hippotrack->name, 'idnumber' => $hippotrack->cmidnumber);
    } else {
        $params = array('itemname' => $hippotrack->name);
    }

    if ($hippotrack->grade > 0) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax']  = $hippotrack->grade;
        $params['grademin']  = 0;

    } else {
        $params['gradetype'] = GRADE_TYPE_NONE;
    }

    // What this is trying to do:
    // 1. If the hippotrack is set to not show grades while the hippotrack is still open,
    //    and is set to show grades after the hippotrack is closed, then create the
    //    grade_item with a show-after date that is the hippotrack close date.
    // 2. If the hippotrack is set to not show grades at either of those times,
    //    create the grade_item as hidden.
    // 3. If the hippotrack is set to show grades, create the grade_item visible.
    $openreviewoptions = mod_hippotrack_display_options::make_from_hippotrack($hippotrack,
            mod_hippotrack_display_options::LATER_WHILE_OPEN);
    $closedreviewoptions = mod_hippotrack_display_options::make_from_hippotrack($hippotrack,
            mod_hippotrack_display_options::AFTER_CLOSE);
    if ($openreviewoptions->marks < question_display_options::MARK_AND_MAX &&
            $closedreviewoptions->marks < question_display_options::MARK_AND_MAX) {
        $params['hidden'] = 1;

    } else if ($openreviewoptions->marks < question_display_options::MARK_AND_MAX &&
            $closedreviewoptions->marks >= question_display_options::MARK_AND_MAX) {
        if ($hippotrack->timeclose) {
            $params['hidden'] = $hippotrack->timeclose;
        } else {
            $params['hidden'] = 1;
        }

    } else {
        // Either
        // a) both open and closed enabled
        // b) open enabled, closed disabled - we can not "hide after",
        //    grades are kept visible even after closing.
        $params['hidden'] = 0;
    }

    if (!$params['hidden']) {
        // If the grade item is not hidden by the hippotrack logic, then we need to
        // hide it if the hippotrack is hidden from students.
        if (property_exists($hippotrack, 'visible')) {
            // Saving the hippotrack form, and cm not yet updated in the database.
            $params['hidden'] = !$hippotrack->visible;
        } else {
            $cm = get_coursemodule_from_instance('hippotrack', $hippotrack->id);
            $params['hidden'] = !$cm->visible;
        }
    }

    if ($grades  === 'reset') {
        $params['reset'] = true;
        $grades = null;
    }

    $gradebook_grades = grade_get_grades($hippotrack->course, 'mod', 'hippotrack', $hippotrack->id);
    if (!empty($gradebook_grades->items)) {
        $grade_item = $gradebook_grades->items[0];
        if ($grade_item->locked) {
            // NOTE: this is an extremely nasty hack! It is not a bug if this confirmation fails badly. --skodak.
            $confirm_regrade = optional_param('confirm_regrade', 0, PARAM_INT);
            if (!$confirm_regrade) {
                if (!AJAX_SCRIPT) {
                    $message = get_string('gradeitemislocked', 'grades');
                    $back_link = $CFG->wwwroot . '/mod/hippotrack/report.php?q=' . $hippotrack->id .
                            '&amp;mode=overview';
                    $regrade_link = qualified_me() . '&amp;confirm_regrade=1';
                    echo $OUTPUT->box_start('generalbox', 'notice');
                    echo '<p>'. $message .'</p>';
                    echo $OUTPUT->container_start('buttons');
                    echo $OUTPUT->single_button($regrade_link, get_string('regradeanyway', 'grades'));
                    echo $OUTPUT->single_button($back_link,  get_string('cancel'));
                    echo $OUTPUT->container_end();
                    echo $OUTPUT->box_end();
                }
                return GRADE_UPDATE_ITEM_LOCKED;
            }
        }
    }

    return grade_update('mod/hippotrack', $hippotrack->course, 'mod', 'hippotrack', $hippotrack->id, 0, $grades, $params);
}

/**
 * Delete grade item for given hippotrack
 *
 * @category grade
 * @param object $hippotrack object
 * @return object hippotrack
 */
function hippotrack_grade_item_delete($hippotrack) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    return grade_update('mod/hippotrack', $hippotrack->course, 'mod', 'hippotrack', $hippotrack->id, 0,
            null, array('deleted' => 1));
}

/**
 * This standard function will check all instances of this module
 * and make sure there are up-to-date events created for each of them.
 * If courseid = 0, then every hippotrack event in the site is checked, else
 * only hippotrack events belonging to the course specified are checked.
 * This function is used, in its new format, by restore_refresh_events()
 *
 * @param int $courseid
 * @param int|stdClass $instance HippoTrack module instance or ID.
 * @param int|stdClass $cm Course module object or ID (not used in this module).
 * @return bool
 */
function hippotrack_refresh_events($courseid = 0, $instance = null, $cm = null) {
    global $DB;

    // If we have instance information then we can just update the one event instead of updating all events.
    if (isset($instance)) {
        if (!is_object($instance)) {
            $instance = $DB->get_record('hippotrack', array('id' => $instance), '*', MUST_EXIST);
        }
        hippotrack_update_events($instance);
        return true;
    }

    if ($courseid == 0) {
        if (!$hippotrackzes = $DB->get_records('hippotrack')) {
            return true;
        }
    } else {
        if (!$hippotrackzes = $DB->get_records('hippotrack', array('course' => $courseid))) {
            return true;
        }
    }

    foreach ($hippotrackzes as $hippotrack) {
        hippotrack_update_events($hippotrack);
    }

    return true;
}

/**
 * Returns all hippotrack graded users since a given time for specified hippotrack
 */
function hippotrack_get_recent_mod_activity(&$activities, &$index, $timestart,
        $courseid, $cmid, $userid = 0, $groupid = 0) {
    global $CFG, $USER, $DB;
    require_once($CFG->dirroot . '/mod/hippotrack/locallib.php');

    $course = get_course($courseid);
    $modinfo = get_fast_modinfo($course);

    $cm = $modinfo->cms[$cmid];
    $hippotrack = $DB->get_record('hippotrack', array('id' => $cm->instance));

    if ($userid) {
        $userselect = "AND u.id = :userid";
        $params['userid'] = $userid;
    } else {
        $userselect = '';
    }

    if ($groupid) {
        $groupselect = 'AND gm.groupid = :groupid';
        $groupjoin   = 'JOIN {groups_members} gm ON  gm.userid=u.id';
        $params['groupid'] = $groupid;
    } else {
        $groupselect = '';
        $groupjoin   = '';
    }

    $params['timestart'] = $timestart;
    $params['hippotrackid'] = $hippotrack->id;

    $userfieldsapi = \core_user\fields::for_userpic();
    $ufields = $userfieldsapi->get_sql('u', false, '', 'useridagain', false)->selects;
    if (!$attempts = $DB->get_records_sql("
              SELECT qa.*,
                     {$ufields}
                FROM {hippotrack_attempts} qa
                     JOIN {user} u ON u.id = qa.userid
                     $groupjoin
               WHERE qa.timefinish > :timestart
                 AND qa.hippotrack = :hippotrackid
                 AND qa.preview = 0
                     $userselect
                     $groupselect
            ORDER BY qa.timefinish ASC", $params)) {
        return;
    }

    $context         = context_module::instance($cm->id);
    $accessallgroups = has_capability('moodle/site:accessallgroups', $context);
    $viewfullnames   = has_capability('moodle/site:viewfullnames', $context);
    $grader          = has_capability('mod/hippotrack:viewreports', $context);
    $groupmode       = groups_get_activity_groupmode($cm, $course);

    $usersgroups = null;
    $aname = format_string($cm->name, true);
    foreach ($attempts as $attempt) {
        if ($attempt->userid != $USER->id) {
            if (!$grader) {
                // Grade permission required.
                continue;
            }

            if ($groupmode == SEPARATEGROUPS and !$accessallgroups) {
                $usersgroups = groups_get_all_groups($course->id,
                        $attempt->userid, $cm->groupingid);
                $usersgroups = array_keys($usersgroups);
                if (!array_intersect($usersgroups, $modinfo->get_groups($cm->groupingid))) {
                    continue;
                }
            }
        }

        $options = hippotrack_get_review_options($hippotrack, $attempt, $context);

        $tmpactivity = new stdClass();

        $tmpactivity->type       = 'hippotrack';
        $tmpactivity->cmid       = $cm->id;
        $tmpactivity->name       = $aname;
        $tmpactivity->sectionnum = $cm->sectionnum;
        $tmpactivity->timestamp  = $attempt->timefinish;

        $tmpactivity->content = new stdClass();
        $tmpactivity->content->attemptid = $attempt->id;
        $tmpactivity->content->attempt   = $attempt->attempt;
        if (hippotrack_has_grades($hippotrack) && $options->marks >= question_display_options::MARK_AND_MAX) {
            $tmpactivity->content->sumgrades = hippotrack_format_grade($hippotrack, $attempt->sumgrades);
            $tmpactivity->content->maxgrade  = hippotrack_format_grade($hippotrack, $hippotrack->sumgrades);
        } else {
            $tmpactivity->content->sumgrades = null;
            $tmpactivity->content->maxgrade  = null;
        }

        $tmpactivity->user = user_picture::unalias($attempt, null, 'useridagain');
        $tmpactivity->user->fullname  = fullname($tmpactivity->user, $viewfullnames);

        $activities[$index++] = $tmpactivity;
    }
}

function hippotrack_print_recent_mod_activity($activity, $courseid, $detail, $modnames) {
    global $CFG, $OUTPUT;

    echo '<table border="0" cellpadding="3" cellspacing="0" class="forum-recent">';

    echo '<tr><td class="userpicture" valign="top">';
    echo $OUTPUT->user_picture($activity->user, array('courseid' => $courseid));
    echo '</td><td>';

    if ($detail) {
        $modname = $modnames[$activity->type];
        echo '<div class="title">';
        echo $OUTPUT->image_icon('monologo', $modname, $activity->type);
        echo '<a href="' . $CFG->wwwroot . '/mod/hippotrack/view.php?id=' .
                $activity->cmid . '">' . $activity->name . '</a>';
        echo '</div>';
    }

    echo '<div class="grade">';
    echo  get_string('attempt', 'hippotrack', $activity->content->attempt);
    if (isset($activity->content->maxgrade)) {
        $grades = $activity->content->sumgrades . ' / ' . $activity->content->maxgrade;
        echo ': (<a href="' . $CFG->wwwroot . '/mod/hippotrack/review.php?attempt=' .
                $activity->content->attemptid . '">' . $grades . '</a>)';
    }
    echo '</div>';

    echo '<div class="user">';
    echo '<a href="' . $CFG->wwwroot . '/user/view.php?id=' . $activity->user->id .
            '&amp;course=' . $courseid . '">' . $activity->user->fullname .
            '</a> - ' . userdate($activity->timestamp);
    echo '</div>';

    echo '</td></tr></table>';

    return;
}

/**
 * Pre-process the hippotrack options form data, making any necessary adjustments.
 * Called by add/update instance in this file.
 *
 * @param object $hippotrack The variables set on the form.
 */
function hippotrack_process_options($hippotrack) {
    global $CFG;
    require_once($CFG->dirroot . '/mod/hippotrack/locallib.php');
    require_once($CFG->libdir . '/questionlib.php');

    $hippotrack->timemodified = time();

    // HippoTrack name.
    if (!empty($hippotrack->name)) {
        $hippotrack->name = trim($hippotrack->name);
    }

    // Password field - different in form to stop browsers that remember passwords
    // getting confused.
    $hippotrack->password = $hippotrack->hippotrackpassword;
    unset($hippotrack->hippotrackpassword);

    // HippoTrack feedback.
    if (isset($hippotrack->feedbacktext)) {
        // Clean up the boundary text.
        for ($i = 0; $i < count($hippotrack->feedbacktext); $i += 1) {
            if (empty($hippotrack->feedbacktext[$i]['text'])) {
                $hippotrack->feedbacktext[$i]['text'] = '';
            } else {
                $hippotrack->feedbacktext[$i]['text'] = trim($hippotrack->feedbacktext[$i]['text']);
            }
        }

        // Check the boundary value is a number or a percentage, and in range.
        $i = 0;
        while (!empty($hippotrack->feedbackboundaries[$i])) {
            $boundary = trim($hippotrack->feedbackboundaries[$i]);
            if (!is_numeric($boundary)) {
                if (strlen($boundary) > 0 && $boundary[strlen($boundary) - 1] == '%') {
                    $boundary = trim(substr($boundary, 0, -1));
                    if (is_numeric($boundary)) {
                        $boundary = $boundary * $hippotrack->grade / 100.0;
                    } else {
                        return get_string('feedbackerrorboundaryformat', 'hippotrack', $i + 1);
                    }
                }
            }
            if ($boundary <= 0 || $boundary >= $hippotrack->grade) {
                return get_string('feedbackerrorboundaryoutofrange', 'hippotrack', $i + 1);
            }
            if ($i > 0 && $boundary >= $hippotrack->feedbackboundaries[$i - 1]) {
                return get_string('feedbackerrororder', 'hippotrack', $i + 1);
            }
            $hippotrack->feedbackboundaries[$i] = $boundary;
            $i += 1;
        }
        $numboundaries = $i;

        // Check there is nothing in the remaining unused fields.
        if (!empty($hippotrack->feedbackboundaries)) {
            for ($i = $numboundaries; $i < count($hippotrack->feedbackboundaries); $i += 1) {
                if (!empty($hippotrack->feedbackboundaries[$i]) &&
                        trim($hippotrack->feedbackboundaries[$i]) != '') {
                    return get_string('feedbackerrorjunkinboundary', 'hippotrack', $i + 1);
                }
            }
        }
        for ($i = $numboundaries + 1; $i < count($hippotrack->feedbacktext); $i += 1) {
            if (!empty($hippotrack->feedbacktext[$i]['text']) &&
                    trim($hippotrack->feedbacktext[$i]['text']) != '') {
                return get_string('feedbackerrorjunkinfeedback', 'hippotrack', $i + 1);
            }
        }
        // Needs to be bigger than $hippotrack->grade because of '<' test in hippotrack_feedback_for_grade().
        $hippotrack->feedbackboundaries[-1] = $hippotrack->grade + 1;
        $hippotrack->feedbackboundaries[$numboundaries] = 0;
        $hippotrack->feedbackboundarycount = $numboundaries;
    } else {
        $hippotrack->feedbackboundarycount = -1;
    }

    // Combing the individual settings into the review columns.
    $hippotrack->reviewattempt = hippotrack_review_option_form_to_db($hippotrack, 'attempt');
    $hippotrack->reviewcorrectness = hippotrack_review_option_form_to_db($hippotrack, 'correctness');
    $hippotrack->reviewmarks = hippotrack_review_option_form_to_db($hippotrack, 'marks');
    $hippotrack->reviewspecificfeedback = hippotrack_review_option_form_to_db($hippotrack, 'specificfeedback');
    $hippotrack->reviewgeneralfeedback = hippotrack_review_option_form_to_db($hippotrack, 'generalfeedback');
    $hippotrack->reviewrightanswer = hippotrack_review_option_form_to_db($hippotrack, 'rightanswer');
    $hippotrack->reviewoverallfeedback = hippotrack_review_option_form_to_db($hippotrack, 'overallfeedback');
    $hippotrack->reviewattempt |= mod_hippotrack_display_options::DURING;
    $hippotrack->reviewoverallfeedback &= ~mod_hippotrack_display_options::DURING;

    // Ensure that disabled checkboxes in completion settings are set to 0.
    // But only if the completion settinsg are unlocked.
    if (!empty($hippotrack->completionunlocked)) {
        if (empty($hippotrack->completionusegrade)) {
            $hippotrack->completionpassgrade = 0;
        }
        if (empty($hippotrack->completionpassgrade)) {
            $hippotrack->completionattemptsexhausted = 0;
        }
        if (empty($hippotrack->completionminattemptsenabled)) {
            $hippotrack->completionminattempts = 0;
        }
    }
}

/**
 * Helper function for {@link hippotrack_process_options()}.
 * @param object $fromform the sumbitted form date.
 * @param string $field one of the review option field names.
 */
function hippotrack_review_option_form_to_db($fromform, $field) {
    static $times = array(
        'during' => mod_hippotrack_display_options::DURING,
        'immediately' => mod_hippotrack_display_options::IMMEDIATELY_AFTER,
        'open' => mod_hippotrack_display_options::LATER_WHILE_OPEN,
        'closed' => mod_hippotrack_display_options::AFTER_CLOSE,
    );

    $review = 0;
    foreach ($times as $whenname => $when) {
        $fieldname = $field . $whenname;
        if (!empty($fromform->$fieldname)) {
            $review |= $when;
            unset($fromform->$fieldname);
        }
    }

    return $review;
}

/**
 * This function is called at the end of hippotrack_add_instance
 * and hippotrack_update_instance, to do the common processing.
 *
 * @param object $hippotrack the hippotrack object.
 */
function hippotrack_after_add_or_update($hippotrack) {
    global $DB;
    $cmid = $hippotrack->coursemodule;

    // We need to use context now, so we need to make sure all needed info is already in db.
    $DB->set_field('course_modules', 'instance', $hippotrack->id, array('id'=>$cmid));
    $context = context_module::instance($cmid);

    // Save the feedback.
    $DB->delete_records('hippotrack_feedback', array('hippotrackid' => $hippotrack->id));

    for ($i = 0; $i <= $hippotrack->feedbackboundarycount; $i++) {
        $feedback = new stdClass();
        $feedback->hippotrackid = $hippotrack->id;
        $feedback->feedbacktext = $hippotrack->feedbacktext[$i]['text'];
        $feedback->feedbacktextformat = $hippotrack->feedbacktext[$i]['format'];
        $feedback->mingrade = $hippotrack->feedbackboundaries[$i];
        $feedback->maxgrade = $hippotrack->feedbackboundaries[$i - 1];
        $feedback->id = $DB->insert_record('hippotrack_feedback', $feedback);
        $feedbacktext = file_save_draft_area_files((int)$hippotrack->feedbacktext[$i]['itemid'],
                $context->id, 'mod_hippotrack', 'feedback', $feedback->id,
                array('subdirs' => false, 'maxfiles' => -1, 'maxbytes' => 0),
                $hippotrack->feedbacktext[$i]['text']);
        $DB->set_field('hippotrack_feedback', 'feedbacktext', $feedbacktext,
                array('id' => $feedback->id));
    }

    // Store any settings belonging to the access rules.
    hippotrack_access_manager::save_settings($hippotrack);

    // Update the events relating to this hippotrack.
    hippotrack_update_events($hippotrack);
    $completionexpected = (!empty($hippotrack->completionexpected)) ? $hippotrack->completionexpected : null;
    \core_completion\api::update_completion_date_event($hippotrack->coursemodule, 'hippotrack', $hippotrack->id, $completionexpected);

    // Update related grade item.
    hippotrack_grade_item_update($hippotrack);
}

/**
 * This function updates the events associated to the hippotrack.
 * If $override is non-zero, then it updates only the events
 * associated with the specified override.
 *
 * @uses HIPPOTRACK_MAX_EVENT_LENGTH
 * @param object $hippotrack the hippotrack object.
 * @param object optional $override limit to a specific override
 */
function hippotrack_update_events($hippotrack, $override = null) {
    global $DB;

    // Load the old events relating to this hippotrack.
    $conds = array('modulename'=>'hippotrack',
                   'instance'=>$hippotrack->id);
    if (!empty($override)) {
        // Only load events for this override.
        if (isset($override->userid)) {
            $conds['userid'] = $override->userid;
        } else {
            $conds['groupid'] = $override->groupid;
        }
    }
    $oldevents = $DB->get_records('event', $conds, 'id ASC');

    // Now make a to-do list of all that needs to be updated.
    if (empty($override)) {
        // We are updating the primary settings for the hippotrack, so we need to add all the overrides.
        $overrides = $DB->get_records('hippotrack_overrides', array('hippotrack' => $hippotrack->id), 'id ASC');
        // It is necessary to add an empty stdClass to the beginning of the array as the $oldevents
        // list contains the original (non-override) event for the module. If this is not included
        // the logic below will end up updating the wrong row when we try to reconcile this $overrides
        // list against the $oldevents list.
        array_unshift($overrides, new stdClass());
    } else {
        // Just do the one override.
        $overrides = array($override);
    }

    // Get group override priorities.
    $grouppriorities = hippotrack_get_group_override_priorities($hippotrack->id);

    foreach ($overrides as $current) {
        $groupid   = isset($current->groupid)?  $current->groupid : 0;
        $userid    = isset($current->userid)? $current->userid : 0;
        $timeopen  = isset($current->timeopen)?  $current->timeopen : $hippotrack->timeopen;
        $timeclose = isset($current->timeclose)? $current->timeclose : $hippotrack->timeclose;

        // Only add open/close events for an override if they differ from the hippotrack default.
        $addopen  = empty($current->id) || !empty($current->timeopen);
        $addclose = empty($current->id) || !empty($current->timeclose);

        if (!empty($hippotrack->coursemodule)) {
            $cmid = $hippotrack->coursemodule;
        } else {
            $cmid = get_coursemodule_from_instance('hippotrack', $hippotrack->id, $hippotrack->course)->id;
        }

        $event = new stdClass();
        $event->type = !$timeclose ? CALENDAR_EVENT_TYPE_ACTION : CALENDAR_EVENT_TYPE_STANDARD;
        $event->description = format_module_intro('hippotrack', $hippotrack, $cmid, false);
        $event->format = FORMAT_HTML;
        // Events module won't show user events when the courseid is nonzero.
        $event->courseid    = ($userid) ? 0 : $hippotrack->course;
        $event->groupid     = $groupid;
        $event->userid      = $userid;
        $event->modulename  = 'hippotrack';
        $event->instance    = $hippotrack->id;
        $event->timestart   = $timeopen;
        $event->timeduration = max($timeclose - $timeopen, 0);
        $event->timesort    = $timeopen;
        $event->visible     = instance_is_visible('hippotrack', $hippotrack);
        $event->eventtype   = HIPPOTRACK_EVENT_TYPE_OPEN;
        $event->priority    = null;

        // Determine the event name and priority.
        if ($groupid) {
            // Group override event.
            $params = new stdClass();
            $params->hippotrack = $hippotrack->name;
            $params->group = groups_get_group_name($groupid);
            if ($params->group === false) {
                // Group doesn't exist, just skip it.
                continue;
            }
            $eventname = get_string('overridegroupeventname', 'hippotrack', $params);
            // Set group override priority.
            if ($grouppriorities !== null) {
                $openpriorities = $grouppriorities['open'];
                if (isset($openpriorities[$timeopen])) {
                    $event->priority = $openpriorities[$timeopen];
                }
            }
        } else if ($userid) {
            // User override event.
            $params = new stdClass();
            $params->hippotrack = $hippotrack->name;
            $eventname = get_string('overrideusereventname', 'hippotrack', $params);
            // Set user override priority.
            $event->priority = CALENDAR_EVENT_USER_OVERRIDE_PRIORITY;
        } else {
            // The parent event.
            $eventname = $hippotrack->name;
        }

        if ($addopen or $addclose) {
            // Separate start and end events.
            $event->timeduration  = 0;
            if ($timeopen && $addopen) {
                if ($oldevent = array_shift($oldevents)) {
                    $event->id = $oldevent->id;
                } else {
                    unset($event->id);
                }
                $event->name = get_string('hippotrackeventopens', 'hippotrack', $eventname);
                // The method calendar_event::create will reuse a db record if the id field is set.
                calendar_event::create($event, false);
            }
            if ($timeclose && $addclose) {
                if ($oldevent = array_shift($oldevents)) {
                    $event->id = $oldevent->id;
                } else {
                    unset($event->id);
                }
                $event->type      = CALENDAR_EVENT_TYPE_ACTION;
                $event->name      = get_string('hippotrackeventcloses', 'hippotrack', $eventname);
                $event->timestart = $timeclose;
                $event->timesort  = $timeclose;
                $event->eventtype = HIPPOTRACK_EVENT_TYPE_CLOSE;
                if ($groupid && $grouppriorities !== null) {
                    $closepriorities = $grouppriorities['close'];
                    if (isset($closepriorities[$timeclose])) {
                        $event->priority = $closepriorities[$timeclose];
                    }
                }
                calendar_event::create($event, false);
            }
        }
    }

    // Delete any leftover events.
    foreach ($oldevents as $badevent) {
        $badevent = calendar_event::load($badevent);
        $badevent->delete();
    }
}

/**
 * Calculates the priorities of timeopen and timeclose values for group overrides for a hippotrack.
 *
 * @param int $hippotrackid The hippotrack ID.
 * @return array|null Array of group override priorities for open and close times. Null if there are no group overrides.
 */
function hippotrack_get_group_override_priorities($hippotrackid) {
    global $DB;

    // Fetch group overrides.
    $where = 'hippotrack = :hippotrack AND groupid IS NOT NULL';
    $params = ['hippotrack' => $hippotrackid];
    $overrides = $DB->get_records_select('hippotrack_overrides', $where, $params, '', 'id, timeopen, timeclose');
    if (!$overrides) {
        return null;
    }

    $grouptimeopen = [];
    $grouptimeclose = [];
    foreach ($overrides as $override) {
        if ($override->timeopen !== null && !in_array($override->timeopen, $grouptimeopen)) {
            $grouptimeopen[] = $override->timeopen;
        }
        if ($override->timeclose !== null && !in_array($override->timeclose, $grouptimeclose)) {
            $grouptimeclose[] = $override->timeclose;
        }
    }

    // Sort open times in ascending manner. The earlier open time gets higher priority.
    sort($grouptimeopen);
    // Set priorities.
    $opengrouppriorities = [];
    $openpriority = 1;
    foreach ($grouptimeopen as $timeopen) {
        $opengrouppriorities[$timeopen] = $openpriority++;
    }

    // Sort close times in descending manner. The later close time gets higher priority.
    rsort($grouptimeclose);
    // Set priorities.
    $closegrouppriorities = [];
    $closepriority = 1;
    foreach ($grouptimeclose as $timeclose) {
        $closegrouppriorities[$timeclose] = $closepriority++;
    }

    return [
        'open' => $opengrouppriorities,
        'close' => $closegrouppriorities
    ];
}

/**
 * List the actions that correspond to a view of this module.
 * This is used by the participation report.
 *
 * Note: This is not used by new logging system. Event with
 *       crud = 'r' and edulevel = LEVEL_PARTICIPATING will
 *       be considered as view action.
 *
 * @return array
 */
function hippotrack_get_view_actions() {
    return array('view', 'view all', 'report', 'review');
}

/**
 * List the actions that correspond to a post of this module.
 * This is used by the participation report.
 *
 * Note: This is not used by new logging system. Event with
 *       crud = ('c' || 'u' || 'd') and edulevel = LEVEL_PARTICIPATING
 *       will be considered as post action.
 *
 * @return array
 */
function hippotrack_get_post_actions() {
    return array('attempt', 'close attempt', 'preview', 'editquestions',
            'delete attempt', 'manualgrade');
}

/**
 * Standard callback used by questions_in_use.
 *
 * @param array $questionids of question ids.
 * @return bool whether any of these questions are used by any instance of this module.
 */
function hippotrack_questions_in_use($questionids) {
    return question_engine::questions_in_use($questionids,
            new qubaid_join('{hippotrack_attempts} hippotracka', 'hippotracka.uniqueid',
                'hippotracka.preview = 0'));
}

/**
 * Implementation of the function for printing the form elements that control
 * whether the course reset functionality affects the hippotrack.
 *
 * @param $mform the course reset form that is being built.
 */
function hippotrack_reset_course_form_definition($mform) {
    $mform->addElement('header', 'hippotrackheader', get_string('modulenameplural', 'hippotrack'));
    $mform->addElement('advcheckbox', 'reset_hippotrack_attempts',
            get_string('removeallhippotrackattempts', 'hippotrack'));
    $mform->addElement('advcheckbox', 'reset_hippotrack_user_overrides',
            get_string('removealluseroverrides', 'hippotrack'));
    $mform->addElement('advcheckbox', 'reset_hippotrack_group_overrides',
            get_string('removeallgroupoverrides', 'hippotrack'));
}

/**
 * Course reset form defaults.
 * @return array the defaults.
 */
function hippotrack_reset_course_form_defaults($course) {
    return array('reset_hippotrack_attempts' => 1,
                 'reset_hippotrack_group_overrides' => 1,
                 'reset_hippotrack_user_overrides' => 1);
}

/**
 * Removes all grades from gradebook
 *
 * @param int $courseid
 * @param string optional type
 */
function hippotrack_reset_gradebook($courseid, $type='') {
    global $CFG, $DB;

    $hippotrackzes = $DB->get_records_sql("
            SELECT q.*, cm.idnumber as cmidnumber, q.course as courseid
            FROM {modules} m
            JOIN {course_modules} cm ON m.id = cm.module
            JOIN {hippotrack} q ON cm.instance = q.id
            WHERE m.name = 'hippotrack' AND cm.course = ?", array($courseid));

    foreach ($hippotrackzes as $hippotrack) {
        hippotrack_grade_item_update($hippotrack, 'reset');
    }
}

/**
 * Actual implementation of the reset course functionality, delete all the
 * hippotrack attempts for course $data->courseid, if $data->reset_hippotrack_attempts is
 * set and true.
 *
 * Also, move the hippotrack open and close dates, if the course start date is changing.
 *
 * @param object $data the data submitted from the reset course.
 * @return array status array
 */
function hippotrack_reset_userdata($data) {
    global $CFG, $DB;
    require_once($CFG->libdir . '/questionlib.php');

    $componentstr = get_string('modulenameplural', 'hippotrack');
    $status = array();

    // Delete attempts.
    if (!empty($data->reset_hippotrack_attempts)) {
        question_engine::delete_questions_usage_by_activities(new qubaid_join(
                '{hippotrack_attempts} hippotracka JOIN {hippotrack} hippotrack ON hippotracka.hippotrack = hippotrack.id',
                'hippotracka.uniqueid', 'hippotrack.course = :hippotrackcourseid',
                array('hippotrackcourseid' => $data->courseid)));

        $DB->delete_records_select('hippotrack_attempts',
                'hippotrack IN (SELECT id FROM {hippotrack} WHERE course = ?)', array($data->courseid));
        $status[] = array(
            'component' => $componentstr,
            'item' => get_string('attemptsdeleted', 'hippotrack'),
            'error' => false);

        // Remove all grades from gradebook.
        $DB->delete_records_select('hippotrack_grades',
                'hippotrack IN (SELECT id FROM {hippotrack} WHERE course = ?)', array($data->courseid));
        if (empty($data->reset_gradebook_grades)) {
            hippotrack_reset_gradebook($data->courseid);
        }
        $status[] = array(
            'component' => $componentstr,
            'item' => get_string('gradesdeleted', 'hippotrack'),
            'error' => false);
    }

    $purgeoverrides = false;

    // Remove user overrides.
    if (!empty($data->reset_hippotrack_user_overrides)) {
        $DB->delete_records_select('hippotrack_overrides',
                'hippotrack IN (SELECT id FROM {hippotrack} WHERE course = ?) AND userid IS NOT NULL', array($data->courseid));
        $status[] = array(
            'component' => $componentstr,
            'item' => get_string('useroverridesdeleted', 'hippotrack'),
            'error' => false);
        $purgeoverrides = true;
    }
    // Remove group overrides.
    if (!empty($data->reset_hippotrack_group_overrides)) {
        $DB->delete_records_select('hippotrack_overrides',
                'hippotrack IN (SELECT id FROM {hippotrack} WHERE course = ?) AND groupid IS NOT NULL', array($data->courseid));
        $status[] = array(
            'component' => $componentstr,
            'item' => get_string('groupoverridesdeleted', 'hippotrack'),
            'error' => false);
        $purgeoverrides = true;
    }

    // Updating dates - shift may be negative too.
    if ($data->timeshift) {
        $DB->execute("UPDATE {hippotrack_overrides}
                         SET timeopen = timeopen + ?
                       WHERE hippotrack IN (SELECT id FROM {hippotrack} WHERE course = ?)
                         AND timeopen <> 0", array($data->timeshift, $data->courseid));
        $DB->execute("UPDATE {hippotrack_overrides}
                         SET timeclose = timeclose + ?
                       WHERE hippotrack IN (SELECT id FROM {hippotrack} WHERE course = ?)
                         AND timeclose <> 0", array($data->timeshift, $data->courseid));

        $purgeoverrides = true;

        // Any changes to the list of dates that needs to be rolled should be same during course restore and course reset.
        // See MDL-9367.
        shift_course_mod_dates('hippotrack', array('timeopen', 'timeclose'),
                $data->timeshift, $data->courseid);

        $status[] = array(
            'component' => $componentstr,
            'item' => get_string('openclosedatesupdated', 'hippotrack'),
            'error' => false);
    }

    if ($purgeoverrides) {
        cache::make('mod_hippotrack', 'overrides')->purge();
    }

    return $status;
}

/**
 * @deprecated since Moodle 3.3, when the block_course_overview block was removed.
 */
function hippotrack_print_overview() {
    throw new coding_exception('hippotrack_print_overview() can not be used any more and is obsolete.');
}

/**
 * Return a textual summary of the number of attempts that have been made at a particular hippotrack,
 * returns '' if no attempts have been made yet, unless $returnzero is passed as true.
 *
 * @param object $hippotrack the hippotrack object. Only $hippotrack->id is used at the moment.
 * @param object $cm the cm object. Only $cm->course, $cm->groupmode and
 *      $cm->groupingid fields are used at the moment.
 * @param bool $returnzero if false (default), when no attempts have been
 *      made '' is returned instead of 'Attempts: 0'.
 * @param int $currentgroup if there is a concept of current group where this method is being called
 *         (e.g. a report) pass it in here. Default 0 which means no current group.
 * @return string a string like "Attempts: 123", "Attemtps 123 (45 from your groups)" or
 *          "Attemtps 123 (45 from this group)".
 */
function hippotrack_num_attempt_summary($hippotrack, $cm, $returnzero = false, $currentgroup = 0) {
    global $DB, $USER;
    $numattempts = $DB->count_records('hippotrack_attempts', array('hippotrack'=> $hippotrack->id, 'preview'=>0));
    if ($numattempts || $returnzero) {
        if (groups_get_activity_groupmode($cm)) {
            $a = new stdClass();
            $a->total = $numattempts;
            if ($currentgroup) {
                $a->group = $DB->count_records_sql('SELECT COUNT(DISTINCT qa.id) FROM ' .
                        '{hippotrack_attempts} qa JOIN ' .
                        '{groups_members} gm ON qa.userid = gm.userid ' .
                        'WHERE hippotrack = ? AND preview = 0 AND groupid = ?',
                        array($hippotrack->id, $currentgroup));
                return get_string('attemptsnumthisgroup', 'hippotrack', $a);
            } else if ($groups = groups_get_all_groups($cm->course, $USER->id, $cm->groupingid)) {
                list($usql, $params) = $DB->get_in_or_equal(array_keys($groups));
                $a->group = $DB->count_records_sql('SELECT COUNT(DISTINCT qa.id) FROM ' .
                        '{hippotrack_attempts} qa JOIN ' .
                        '{groups_members} gm ON qa.userid = gm.userid ' .
                        'WHERE hippotrack = ? AND preview = 0 AND ' .
                        "groupid $usql", array_merge(array($hippotrack->id), $params));
                return get_string('attemptsnumyourgroups', 'hippotrack', $a);
            }
        }
        return get_string('attemptsnum', 'hippotrack', $numattempts);
    }
    return '';
}

/**
 * Returns the same as {@link hippotrack_num_attempt_summary()} but wrapped in a link
 * to the hippotrack reports.
 *
 * @param object $hippotrack the hippotrack object. Only $hippotrack->id is used at the moment.
 * @param object $cm the cm object. Only $cm->course, $cm->groupmode and
 *      $cm->groupingid fields are used at the moment.
 * @param object $context the hippotrack context.
 * @param bool $returnzero if false (default), when no attempts have been made
 *      '' is returned instead of 'Attempts: 0'.
 * @param int $currentgroup if there is a concept of current group where this method is being called
 *         (e.g. a report) pass it in here. Default 0 which means no current group.
 * @return string HTML fragment for the link.
 */
function hippotrack_attempt_summary_link_to_reports($hippotrack, $cm, $context, $returnzero = false,
        $currentgroup = 0) {
    global $PAGE;

    return $PAGE->get_renderer('mod_hippotrack')->hippotrack_attempt_summary_link_to_reports(
            $hippotrack, $cm, $context, $returnzero, $currentgroup);
}

/**
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, false if not, null if doesn't know or string for the module purpose.
 */
function hippotrack_supports($feature) {
    switch($feature) {
        case FEATURE_GROUPS:                    return true;
        case FEATURE_GROUPINGS:                 return true;
        case FEATURE_MOD_INTRO:                 return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:   return true;
        case FEATURE_COMPLETION_HAS_RULES:      return true;
        case FEATURE_GRADE_HAS_GRADE:           return true;
        case FEATURE_GRADE_OUTCOMES:            return true;
        case FEATURE_BACKUP_MOODLE2:            return true;
        case FEATURE_SHOW_DESCRIPTION:          return true;
        case FEATURE_CONTROLS_GRADE_VISIBILITY: return true;
        case FEATURE_USES_QUESTIONS:            return true;
        case FEATURE_PLAGIARISM:                return true;
        case FEATURE_MOD_PURPOSE:               return MOD_PURPOSE_ASSESSMENT;

        default: return null;
    }
}

/**
 * @return array all other caps used in module
 */
function hippotrack_get_extra_capabilities() {
    global $CFG;
    require_once($CFG->libdir . '/questionlib.php');
    return question_get_all_capabilities();
}

/**
 * This function extends the settings navigation block for the site.
 *
 * It is safe to rely on PAGE here as we will only ever be within the module
 * context when this is called
 *
 * @param settings_navigation $settings
 * @param navigation_node $hippotracknode
 * @return void
 */
function hippotrack_extend_settings_navigation(settings_navigation $settings, navigation_node $hippotracknode) {
    global $CFG;

    // Require {@link questionlib.php}
    // Included here as we only ever want to include this file if we really need to.
    require_once($CFG->libdir . '/questionlib.php');

    // We want to add these new nodes after the Edit settings node, and before the
    // Locally assigned roles node. Of course, both of those are controlled by capabilities.
    $keys = $hippotracknode->get_children_key_list();
    $beforekey = null;
    $i = array_search('modedit', $keys);
    if ($i === false and array_key_exists(0, $keys)) {
        $beforekey = $keys[0];
    } else if (array_key_exists($i + 1, $keys)) {
        $beforekey = $keys[$i + 1];
    }

    if (has_any_capability(['mod/hippotrack:manageoverrides', 'mod/hippotrack:viewoverrides'], $settings->get_page()->cm->context)) {
        $url = new moodle_url('/mod/hippotrack/overrides.php', ['cmid' => $settings->get_page()->cm->id, 'mode' => 'user']);
        $node = navigation_node::create(get_string('overrides', 'hippotrack'),
                    $url, navigation_node::TYPE_SETTING, null, 'mod_hippotrack_useroverrides');
        $settingsoverride = $hippotracknode->add_node($node, $beforekey);
    }

    if (has_capability('mod/hippotrack:manage', $settings->get_page()->cm->context)) {
        $node = navigation_node::create(get_string('questions', 'hippotrack'),
            new moodle_url('/mod/hippotrack/edit.php', array('cmid' => $settings->get_page()->cm->id)),
            navigation_node::TYPE_SETTING, null, 'mod_hippotrack_edit', new pix_icon('t/edit', ''));
        $hippotracknode->add_node($node, $beforekey);
    }

    if (has_capability('mod/hippotrack:preview', $settings->get_page()->cm->context)) {
        $url = new moodle_url('/mod/hippotrack/startattempt.php',
                array('cmid' => $settings->get_page()->cm->id, 'sesskey' => sesskey()));
        $node = navigation_node::create(get_string('preview', 'hippotrack'), $url,
                navigation_node::TYPE_SETTING, null, 'mod_hippotrack_preview',
                new pix_icon('i/preview', ''));
        $previewnode = $hippotracknode->add_node($node, $beforekey);
        $previewnode->set_show_in_secondary_navigation(false);
    }

    question_extend_settings_navigation($hippotracknode, $settings->get_page()->cm->context)->trim_if_empty();

    if (has_any_capability(array('mod/hippotrack:viewreports', 'mod/hippotrack:grade'), $settings->get_page()->cm->context)) {
        require_once($CFG->dirroot . '/mod/hippotrack/report/reportlib.php');
        $reportlist = hippotrack_report_list($settings->get_page()->cm->context);

        $url = new moodle_url('/mod/hippotrack/report.php',
                array('id' => $settings->get_page()->cm->id, 'mode' => reset($reportlist)));
        $reportnode = $hippotracknode->add_node(navigation_node::create(get_string('results', 'hippotrack'), $url,
                navigation_node::TYPE_SETTING,
                null, 'hippotrack_report', new pix_icon('i/report', '')));

        foreach ($reportlist as $report) {
            $url = new moodle_url('/mod/hippotrack/report.php', ['id' => $settings->get_page()->cm->id, 'mode' => $report]);
            $reportnode->add_node(navigation_node::create(get_string($report, 'hippotrack_'.$report), $url,
                    navigation_node::TYPE_SETTING,
                    null, 'hippotrack_report_' . $report, new pix_icon('i/item', '')));
        }
    }
}

/**
 * Serves the hippotrack files.
 *
 * @package  mod_hippotrack
 * @category files
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param stdClass $context context object
 * @param string $filearea file area
 * @param array $args extra arguments
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool false if file not found, does not return if found - justsend the file
 */
function hippotrack_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options=array()) {
    global $CFG, $DB;

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    require_login($course, false, $cm);

    if (!$hippotrack = $DB->get_record('hippotrack', array('id'=>$cm->instance))) {
        return false;
    }

    // The 'intro' area is served by pluginfile.php.
    $fileareas = array('feedback');
    if (!in_array($filearea, $fileareas)) {
        return false;
    }

    $feedbackid = (int)array_shift($args);
    if (!$feedback = $DB->get_record('hippotrack_feedback', array('id'=>$feedbackid))) {
        return false;
    }

    $fs = get_file_storage();
    $relativepath = implode('/', $args);
    $fullpath = "/$context->id/mod_hippotrack/$filearea/$feedbackid/$relativepath";
    if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
        return false;
    }
    send_stored_file($file, 0, 0, true, $options);
}

/**
 * Called via pluginfile.php -> question_pluginfile to serve files belonging to
 * a question in a question_attempt when that attempt is a hippotrack attempt.
 *
 * @package  mod_hippotrack
 * @category files
 * @param stdClass $course course settings object
 * @param stdClass $context context object
 * @param string $component the name of the component we are serving files for.
 * @param string $filearea the name of the file area.
 * @param int $qubaid the attempt usage id.
 * @param int $slot the id of a question in this hippotrack attempt.
 * @param array $args the remaining bits of the file path.
 * @param bool $forcedownload whether the user must be forced to download the file.
 * @param array $options additional options affecting the file serving
 * @return bool false if file not found, does not return if found - justsend the file
 */
function hippotrack_question_pluginfile($course, $context, $component,
        $filearea, $qubaid, $slot, $args, $forcedownload, array $options=array()) {
    global $CFG;
    require_once($CFG->dirroot . '/mod/hippotrack/locallib.php');

    $attemptobj = hippotrack_attempt::create_from_usage_id($qubaid);
    require_login($attemptobj->get_course(), false, $attemptobj->get_cm());

    if ($attemptobj->is_own_attempt() && !$attemptobj->is_finished()) {
        // In the middle of an attempt.
        if (!$attemptobj->is_preview_user()) {
            $attemptobj->require_capability('mod/hippotrack:attempt');
        }
        $isreviewing = false;

    } else {
        // Reviewing an attempt.
        $attemptobj->check_review_capability();
        $isreviewing = true;
    }

    if (!$attemptobj->check_file_access($slot, $isreviewing, $context->id,
            $component, $filearea, $args, $forcedownload)) {
        send_file_not_found();
    }

    $fs = get_file_storage();
    $relativepath = implode('/', $args);
    $fullpath = "/$context->id/$component/$filearea/$relativepath";
    if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
        send_file_not_found();
    }

    send_stored_file($file, 0, 0, $forcedownload, $options);
}

/**
 * Return a list of page types
 * @param string $pagetype current page type
 * @param stdClass $parentcontext Block's parent context
 * @param stdClass $currentcontext Current context of block
 */
function hippotrack_page_type_list($pagetype, $parentcontext, $currentcontext) {
    $module_pagetype = array(
        'mod-hippotrack-*'       => get_string('page-mod-hippotrack-x', 'hippotrack'),
        'mod-hippotrack-view'    => get_string('page-mod-hippotrack-view', 'hippotrack'),
        'mod-hippotrack-attempt' => get_string('page-mod-hippotrack-attempt', 'hippotrack'),
        'mod-hippotrack-summary' => get_string('page-mod-hippotrack-summary', 'hippotrack'),
        'mod-hippotrack-review'  => get_string('page-mod-hippotrack-review', 'hippotrack'),
        'mod-hippotrack-edit'    => get_string('page-mod-hippotrack-edit', 'hippotrack'),
        'mod-hippotrack-report'  => get_string('page-mod-hippotrack-report', 'hippotrack'),
    );
    return $module_pagetype;
}

/**
 * @return the options for hippotrack navigation.
 */
function hippotrack_get_navigation_options() {
    return array(
        HIPPOTRACK_NAVMETHOD_FREE => get_string('navmethod_free', 'hippotrack'),
        HIPPOTRACK_NAVMETHOD_SEQ  => get_string('navmethod_seq', 'hippotrack')
    );
}

/**
 * Check if the module has any update that affects the current user since a given time.
 *
 * @param  cm_info $cm course module data
 * @param  int $from the time to check updates from
 * @param  array $filter  if we need to check only specific updates
 * @return stdClass an object with the different type of areas indicating if they were updated or not
 * @since Moodle 3.2
 */
function hippotrack_check_updates_since(cm_info $cm, $from, $filter = array()) {
    global $DB, $USER, $CFG;
    require_once($CFG->dirroot . '/mod/hippotrack/locallib.php');

    $updates = course_check_module_updates_since($cm, $from, array(), $filter);

    // Check if questions were updated.
    $updates->questions = (object) array('updated' => false);
    $hippotrackobj = hippotrack::create($cm->instance, $USER->id);
    $hippotrackobj->preload_questions();
    $hippotrackobj->load_questions();
    $questionids = array_keys($hippotrackobj->get_questions());
    if (!empty($questionids)) {
        list($questionsql, $params) = $DB->get_in_or_equal($questionids, SQL_PARAMS_NAMED);
        $select = 'id ' . $questionsql . ' AND (timemodified > :time1 OR timecreated > :time2)';
        $params['time1'] = $from;
        $params['time2'] = $from;
        $questions = $DB->get_records_select('question', $select, $params, '', 'id');
        if (!empty($questions)) {
            $updates->questions->updated = true;
            $updates->questions->itemids = array_keys($questions);
        }
    }

    // Check for new attempts or grades.
    $updates->attempts = (object) array('updated' => false);
    $updates->grades = (object) array('updated' => false);
    $select = 'hippotrack = ? AND userid = ? AND timemodified > ?';
    $params = array($cm->instance, $USER->id, $from);

    $attempts = $DB->get_records_select('hippotrack_attempts', $select, $params, '', 'id');
    if (!empty($attempts)) {
        $updates->attempts->updated = true;
        $updates->attempts->itemids = array_keys($attempts);
    }
    $grades = $DB->get_records_select('hippotrack_grades', $select, $params, '', 'id');
    if (!empty($grades)) {
        $updates->grades->updated = true;
        $updates->grades->itemids = array_keys($grades);
    }

    // Now, teachers should see other students updates.
    if (has_capability('mod/hippotrack:viewreports', $cm->context)) {
        $select = 'hippotrack = ? AND timemodified > ?';
        $params = array($cm->instance, $from);

        if (groups_get_activity_groupmode($cm) == SEPARATEGROUPS) {
            $groupusers = array_keys(groups_get_activity_shared_group_members($cm));
            if (empty($groupusers)) {
                return $updates;
            }
            list($insql, $inparams) = $DB->get_in_or_equal($groupusers);
            $select .= ' AND userid ' . $insql;
            $params = array_merge($params, $inparams);
        }

        $updates->userattempts = (object) array('updated' => false);
        $attempts = $DB->get_records_select('hippotrack_attempts', $select, $params, '', 'id');
        if (!empty($attempts)) {
            $updates->userattempts->updated = true;
            $updates->userattempts->itemids = array_keys($attempts);
        }

        $updates->usergrades = (object) array('updated' => false);
        $grades = $DB->get_records_select('hippotrack_grades', $select, $params, '', 'id');
        if (!empty($grades)) {
            $updates->usergrades->updated = true;
            $updates->usergrades->itemids = array_keys($grades);
        }
    }
    return $updates;
}

/**
 * Get icon mapping for font-awesome.
 */
function mod_hippotrack_get_fontawesome_icon_map() {
    return [
        'mod_hippotrack:navflagged' => 'fa-flag',
    ];
}

/**
 * This function receives a calendar event and returns the action associated with it, or null if there is none.
 *
 * This is used by block_myoverview in order to display the event appropriately. If null is returned then the event
 * is not displayed on the block.
 *
 * @param calendar_event $event
 * @param \core_calendar\action_factory $factory
 * @param int $userid User id to use for all capability checks, etc. Set to 0 for current user (default).
 * @return \core_calendar\local\event\entities\action_interface|null
 */
function mod_hippotrack_core_calendar_provide_event_action(calendar_event $event,
                                                     \core_calendar\action_factory $factory,
                                                     int $userid = 0) {
    global $CFG, $USER;

    require_once($CFG->dirroot . '/mod/hippotrack/locallib.php');

    if (empty($userid)) {
        $userid = $USER->id;
    }

    $cm = get_fast_modinfo($event->courseid, $userid)->instances['hippotrack'][$event->instance];
    $hippotrackobj = hippotrack::create($cm->instance, $userid);
    $hippotrack = $hippotrackobj->get_hippotrack();

    // Check they have capabilities allowing them to view the hippotrack.
    if (!has_any_capability(['mod/hippotrack:reviewmyattempts', 'mod/hippotrack:attempt'], $hippotrackobj->get_context(), $userid)) {
        return null;
    }

    $completion = new \completion_info($cm->get_course());

    $completiondata = $completion->get_data($cm, false, $userid);

    if ($completiondata->completionstate != COMPLETION_INCOMPLETE) {
        return null;
    }

    hippotrack_update_effective_access($hippotrack, $userid);

    // Check if hippotrack is closed, if so don't display it.
    if (!empty($hippotrack->timeclose) && $hippotrack->timeclose <= time()) {
        return null;
    }

    if (!$hippotrackobj->is_participant($userid)) {
        // If the user is not a participant then they have
        // no action to take. This will filter out the events for teachers.
        return null;
    }

    $attempts = hippotrack_get_user_attempts($hippotrackobj->get_hippotrackid(), $userid);
    if (!empty($attempts)) {
        // The student's last attempt is finished.
        return null;
    }

    $name = get_string('attempthippotracknow', 'hippotrack');
    $url = new \moodle_url('/mod/hippotrack/view.php', [
        'id' => $cm->id
    ]);
    $itemcount = 1;
    $actionable = true;

    // Check if the hippotrack is not currently actionable.
    if (!empty($hippotrack->timeopen) && $hippotrack->timeopen > time()) {
        $actionable = false;
    }

    return $factory->create_instance(
        $name,
        $url,
        $itemcount,
        $actionable
    );
}

/**
 * Add a get_coursemodule_info function in case any hippotrack type wants to add 'extra' information
 * for the course (see resource).
 *
 * Given a course_module object, this function returns any "extra" information that may be needed
 * when printing this activity in a course listing.  See get_array_of_activities() in course/lib.php.
 *
 * @param stdClass $coursemodule The coursemodule object (record).
 * @return cached_cm_info An object on information that the courses
 *                        will know about (most noticeably, an icon).
 */
function hippotrack_get_coursemodule_info($coursemodule) {
    global $DB;

    $dbparams = ['id' => $coursemodule->instance];
    $fields = 'id, name, intro, introformat, completionattemptsexhausted, completionminattempts,
        timeopen, timeclose';
    if (!$hippotrack = $DB->get_record('hippotrack', $dbparams, $fields)) {
        return false;
    }

    $result = new cached_cm_info();
    $result->name = $hippotrack->name;

    if ($coursemodule->showdescription) {
        // Convert intro to html. Do not filter cached version, filters run at display time.
        $result->content = format_module_intro('hippotrack', $hippotrack, $coursemodule->id, false);
    }

    // Populate the custom completion rules as key => value pairs, but only if the completion mode is 'automatic'.
    if ($coursemodule->completion == COMPLETION_TRACKING_AUTOMATIC) {
        if ($hippotrack->completionattemptsexhausted) {
            $result->customdata['customcompletionrules']['completionpassorattemptsexhausted'] = [
                'completionpassgrade' => $coursemodule->completionpassgrade,
                'completionattemptsexhausted' => $hippotrack->completionattemptsexhausted,
            ];
        } else {
            $result->customdata['customcompletionrules']['completionpassorattemptsexhausted'] = [];
        }

        $result->customdata['customcompletionrules']['completionminattempts'] = $hippotrack->completionminattempts;
    }

    // Populate some other values that can be used in calendar or on dashboard.
    if ($hippotrack->timeopen) {
        $result->customdata['timeopen'] = $hippotrack->timeopen;
    }
    if ($hippotrack->timeclose) {
        $result->customdata['timeclose'] = $hippotrack->timeclose;
    }

    return $result;
}

/**
 * Sets dynamic information about a course module
 *
 * This function is called from cm_info when displaying the module
 *
 * @param cm_info $cm
 */
function mod_hippotrack_cm_info_dynamic(cm_info $cm) {
    global $USER;

    $cache = cache::make('mod_hippotrack', 'overrides');
    $override = $cache->get("{$cm->instance}_u_{$USER->id}");

    if (!$override) {
        $override = (object) [
            'timeopen' => null,
            'timeclose' => null,
        ];
    }

    // No need to look for group overrides if there are user overrides for both timeopen and timeclose.
    if (is_null($override->timeopen) || is_null($override->timeclose)) {
        $opens = [];
        $closes = [];
        $groupings = groups_get_user_groups($cm->course, $USER->id);
        foreach ($groupings[0] as $groupid) {
            $groupoverride = $cache->get("{$cm->instance}_g_{$groupid}");
            if (isset($groupoverride->timeopen)) {
                $opens[] = $groupoverride->timeopen;
            }
            if (isset($groupoverride->timeclose)) {
                $closes[] = $groupoverride->timeclose;
            }
        }
        // If there is a user override for a setting, ignore the group override.
        if (is_null($override->timeopen) && count($opens)) {
            $override->timeopen = min($opens);
        }
        if (is_null($override->timeclose) && count($closes)) {
            if (in_array(0, $closes)) {
                $override->timeclose = 0;
            } else {
                $override->timeclose = max($closes);
            }
        }
    }

    // Populate some other values that can be used in calendar or on dashboard.
    if (!is_null($override->timeopen)) {
        $cm->override_customdata('timeopen', $override->timeopen);
    }
    if (!is_null($override->timeclose)) {
        $cm->override_customdata('timeclose', $override->timeclose);
    }
}

/**
 * Callback which returns human-readable strings describing the active completion custom rules for the module instance.
 *
 * @param cm_info|stdClass $cm object with fields ->completion and ->customdata['customcompletionrules']
 * @return array $descriptions the array of descriptions for the custom rules.
 */
function mod_hippotrack_get_completion_active_rule_descriptions($cm) {
    // Values will be present in cm_info, and we assume these are up to date.
    if (empty($cm->customdata['customcompletionrules'])
        || $cm->completion != COMPLETION_TRACKING_AUTOMATIC) {
        return [];
    }

    $descriptions = [];
    $rules = $cm->customdata['customcompletionrules'];

    if (!empty($rules['completionpassorattemptsexhausted'])) {
        if (!empty($rules['completionpassorattemptsexhausted']['completionattemptsexhausted'])) {
            $descriptions[] = get_string('completionpassorattemptsexhausteddesc', 'hippotrack');
        }
    } else {
        // Fallback.
        if (!empty($rules['completionattemptsexhausted'])) {
            $descriptions[] = get_string('completionpassorattemptsexhausteddesc', 'hippotrack');
        }
    }

    if (!empty($rules['completionminattempts'])) {
        $descriptions[] = get_string('completionminattemptsdesc', 'hippotrack', $rules['completionminattempts']);
    }

    return $descriptions;
}

/**
 * Returns the min and max values for the timestart property of a hippotrack
 * activity event.
 *
 * The min and max values will be the timeopen and timeclose properties
 * of the hippotrack, respectively, if they are set.
 *
 * If either value isn't set then null will be returned instead to
 * indicate that there is no cutoff for that value.
 *
 * If the vent has no valid timestart range then [false, false] will
 * be returned. This is the case for overriden events.
 *
 * A minimum and maximum cutoff return value will look like:
 * [
 *     [1505704373, 'The date must be after this date'],
 *     [1506741172, 'The date must be before this date']
 * ]
 *
 * @throws \moodle_exception
 * @param \calendar_event $event The calendar event to get the time range for
 * @param stdClass $hippotrack The module instance to get the range from
 * @return array
 */
function mod_hippotrack_core_calendar_get_valid_event_timestart_range(\calendar_event $event, \stdClass $hippotrack) {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/mod/hippotrack/locallib.php');

    // Overrides do not have a valid timestart range.
    if (hippotrack_is_overriden_calendar_event($event)) {
        return [false, false];
    }

    $mindate = null;
    $maxdate = null;

    if ($event->eventtype == HIPPOTRACK_EVENT_TYPE_OPEN) {
        if (!empty($hippotrack->timeclose)) {
            $maxdate = [
                $hippotrack->timeclose,
                get_string('openafterclose', 'hippotrack')
            ];
        }
    } else if ($event->eventtype == HIPPOTRACK_EVENT_TYPE_CLOSE) {
        if (!empty($hippotrack->timeopen)) {
            $mindate = [
                $hippotrack->timeopen,
                get_string('closebeforeopen', 'hippotrack')
            ];
        }
    }

    return [$mindate, $maxdate];
}

/**
 * This function will update the hippotrack module according to the
 * event that has been modified.
 *
 * It will set the timeopen or timeclose value of the hippotrack instance
 * according to the type of event provided.
 *
 * @throws \moodle_exception
 * @param \calendar_event $event A hippotrack activity calendar event
 * @param \stdClass $hippotrack A hippotrack activity instance
 */
function mod_hippotrack_core_calendar_event_timestart_updated(\calendar_event $event, \stdClass $hippotrack) {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/mod/hippotrack/locallib.php');

    if (!in_array($event->eventtype, [HIPPOTRACK_EVENT_TYPE_OPEN, HIPPOTRACK_EVENT_TYPE_CLOSE])) {
        // This isn't an event that we care about so we can ignore it.
        return;
    }

    $courseid = $event->courseid;
    $modulename = $event->modulename;
    $instanceid = $event->instance;
    $modified = false;
    $closedatechanged = false;

    // Something weird going on. The event is for a different module so
    // we should ignore it.
    if ($modulename != 'hippotrack') {
        return;
    }

    if ($hippotrack->id != $instanceid) {
        // The provided hippotrack instance doesn't match the event so
        // there is nothing to do here.
        return;
    }

    // We don't update the activity if it's an override event that has
    // been modified.
    if (hippotrack_is_overriden_calendar_event($event)) {
        return;
    }

    $coursemodule = get_fast_modinfo($courseid)->instances[$modulename][$instanceid];
    $context = context_module::instance($coursemodule->id);

    // The user does not have the capability to modify this activity.
    if (!has_capability('moodle/course:manageactivities', $context)) {
        return;
    }

    if ($event->eventtype == HIPPOTRACK_EVENT_TYPE_OPEN) {
        // If the event is for the hippotrack activity opening then we should
        // set the start time of the hippotrack activity to be the new start
        // time of the event.
        if ($hippotrack->timeopen != $event->timestart) {
            $hippotrack->timeopen = $event->timestart;
            $modified = true;
        }
    } else if ($event->eventtype == HIPPOTRACK_EVENT_TYPE_CLOSE) {
        // If the event is for the hippotrack activity closing then we should
        // set the end time of the hippotrack activity to be the new start
        // time of the event.
        if ($hippotrack->timeclose != $event->timestart) {
            $hippotrack->timeclose = $event->timestart;
            $modified = true;
            $closedatechanged = true;
        }
    }

    if ($modified) {
        $hippotrack->timemodified = time();
        $DB->update_record('hippotrack', $hippotrack);

        if ($closedatechanged) {
            hippotrack_update_open_attempts(array('hippotrackid' => $hippotrack->id));
        }

        // Delete any previous preview attempts.
        hippotrack_delete_previews($hippotrack);
        hippotrack_update_events($hippotrack);
        $event = \core\event\course_module_updated::create_from_cm($coursemodule, $context);
        $event->trigger();
    }
}

/**
 * Generates the question bank in a fragment output. This allows
 * the question bank to be displayed in a modal.
 *
 * The only expected argument provided in the $args array is
 * 'querystring'. The value should be the list of parameters
 * URL encoded and used to build the question bank page.
 *
 * The individual list of parameters expected can be found in
 * question_build_edit_resources.
 *
 * @param array $args The fragment arguments.
 * @return string The rendered mform fragment.
 */
function mod_hippotrack_output_fragment_hippotrack_question_bank($args) {
    global $CFG, $DB, $PAGE;
    require_once($CFG->dirroot . '/mod/hippotrack/locallib.php');
    require_once($CFG->dirroot . '/question/editlib.php');

    $querystring = preg_replace('/^\?/', '', $args['querystring']);
    $params = [];
    parse_str($querystring, $params);

    // Build the required resources. The $params are all cleaned as
    // part of this process.
    list($thispageurl, $contexts, $cmid, $cm, $hippotrack, $pagevars) =
            question_build_edit_resources('editq', '/mod/hippotrack/edit.php', $params, custom_view::DEFAULT_PAGE_SIZE);

    // Get the course object and related bits.
    $course = $DB->get_record('course', array('id' => $hippotrack->course), '*', MUST_EXIST);
    require_capability('mod/hippotrack:manage', $contexts->lowest());

    // Create hippotrack question bank view.
    $questionbank = new custom_view($contexts, $thispageurl, $course, $cm, $hippotrack);
    $questionbank->set_hippotrack_has_attempts(hippotrack_has_attempts($hippotrack->id));

    // Output.
    $renderer = $PAGE->get_renderer('mod_hippotrack', 'edit');
    return $renderer->question_bank_contents($questionbank, $pagevars);
}

/**
 * Generates the add random question in a fragment output. This allows the
 * form to be rendered in javascript, for example inside a modal.
 *
 * The required arguments as keys in the $args array are:
 *      cat {string} The category and category context ids comma separated.
 *      addonpage {int} The page id to add this question to.
 *      returnurl {string} URL to return to after form submission.
 *      cmid {int} The course module id the questions are being added to.
 *
 * @param array $args The fragment arguments.
 * @return string The rendered mform fragment.
 */
function mod_hippotrack_output_fragment_add_random_question_form($args) {
    global $CFG;
    require_once($CFG->dirroot . '/mod/hippotrack/addrandomform.php');

    $contexts = new \core_question\local\bank\question_edit_contexts($args['context']);
    $formoptions = [
        'contexts' => $contexts,
        'cat' => $args['cat']
    ];
    $formdata = [
        'category' => $args['cat'],
        'addonpage' => $args['addonpage'],
        'returnurl' => $args['returnurl'],
        'cmid' => $args['cmid']
    ];

    $form = new hippotrack_add_random_form(
        new \moodle_url('/mod/hippotrack/addrandom.php'),
        $formoptions,
        'post',
        '',
        null,
        true,
        $formdata
    );
    $form->set_data($formdata);

    return $form->render();
}

/**
 * Callback to fetch the activity event type lang string.
 *
 * @param string $eventtype The event type.
 * @return lang_string The event type lang string.
 */
function mod_hippotrack_core_calendar_get_event_action_string(string $eventtype): string {
    $modulename = get_string('modulename', 'hippotrack');

    switch ($eventtype) {
        case HIPPOTRACK_EVENT_TYPE_OPEN:
            $identifier = 'hippotrackeventopens';
            break;
        case HIPPOTRACK_EVENT_TYPE_CLOSE:
            $identifier = 'hippotrackeventcloses';
            break;
        default:
            return get_string('requiresaction', 'calendar', $modulename);
    }

    return get_string($identifier, 'hippotrack', $modulename);
}

/**
 * Delete question reference data.
 *
 * @param int $hippotrackid The id of hippotrack.
 */
function hippotrack_delete_references($hippotrackid): void {
    global $DB;
    $slots = $DB->get_records('hippotrack_slots', ['hippotrackid' => $hippotrackid]);
    foreach ($slots as $slot) {
        $params = [
            'itemid' => $slot->id,
            'component' => 'mod_hippotrack',
            'questionarea' => 'slot'
        ];
        // Delete any set references.
        $DB->delete_records('question_set_references', $params);
        // Delete any references.
        $DB->delete_records('question_references', $params);
    }
}

/**
 * Implement the calculate_question_stats callback.
 *
 * This enables hippotrack statistics to be shown in statistics columns in the database.
 *
 * @param context $context return the statistics related to this context (which will be a hippotrack context).
 * @return all_calculated_for_qubaid_condition|null The statistics for this hippotrack, if available, else null.
 */
function mod_hippotrack_calculate_question_stats(context $context): ?all_calculated_for_qubaid_condition {
    global $CFG;
    require_once($CFG->dirroot . '/mod/hippotrack/report/statistics/report.php');
    $cm = get_coursemodule_from_id('hippotrack', $context->instanceid);
    $report = new hippotrack_statistics_report();
    return $report->calculate_questions_stats_for_question_bank($cm->instance, false, false);
}
