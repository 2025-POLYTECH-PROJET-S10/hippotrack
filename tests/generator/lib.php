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

defined('MOODLE_INTERNAL') || die();

/**
 * HippoTrack module test data generator class
 *
 * @package mod_hippotrack
 * @copyright 2012 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_hippotrack_generator extends testing_module_generator {

    public function create_instance($record = null, array $options = null) {
        global $CFG;

        require_once($CFG->dirroot.'/mod/hippotrack/locallib.php');
        $record = (object)(array)$record;

        $defaulthippotracksettings = array(
            'timeopen'               => 0,
            'timeclose'              => 0,
            'preferredbehaviour'     => 'deferredfeedback',
            'attempts'               => 0,
            'attemptonlast'          => 0,
            'grademethod'            => HIPPOTRACK_GRADEHIGHEST,
            'decimalpoints'          => 2,
            'questiondecimalpoints'  => -1,
            'attemptduring'          => 1,
            'correctnessduring'      => 1,
            'marksduring'            => 1,
            'specificfeedbackduring' => 1,
            'generalfeedbackduring'  => 1,
            'rightanswerduring'      => 1,
            'overallfeedbackduring'  => 0,
            'attemptimmediately'          => 1,
            'correctnessimmediately'      => 1,
            'marksimmediately'            => 1,
            'specificfeedbackimmediately' => 1,
            'generalfeedbackimmediately'  => 1,
            'rightanswerimmediately'      => 1,
            'overallfeedbackimmediately'  => 1,
            'attemptopen'            => 1,
            'correctnessopen'        => 1,
            'marksopen'              => 1,
            'specificfeedbackopen'   => 1,
            'generalfeedbackopen'    => 1,
            'rightansweropen'        => 1,
            'overallfeedbackopen'    => 1,
            'attemptclosed'          => 1,
            'correctnessclosed'      => 1,
            'marksclosed'            => 1,
            'specificfeedbackclosed' => 1,
            'generalfeedbackclosed'  => 1,
            'rightanswerclosed'      => 1,
            'overallfeedbackclosed'  => 1,
            'questionsperpage'       => 1,
            'shuffleanswers'         => 1,
            'sumgrades'              => 0,
            'grade'                  => 100,
            'timecreated'            => time(),
            'timemodified'           => time(),
            'timelimit'              => 0,
            'overduehandling'        => 'autosubmit',
            'graceperiod'            => 86400,
            'hippotrackpassword'           => '',
            'subnet'                 => '',
            'browsersecurity'        => '',
            'delay1'                 => 0,
            'delay2'                 => 0,
            'showuserpicture'        => 0,
            'showblocks'             => 0,
            'navmethod'              => HIPPOTRACK_NAVMETHOD_FREE,
        );

        foreach ($defaulthippotracksettings as $name => $value) {
            if (!isset($record->{$name})) {
                $record->{$name} = $value;
            }
        }

        if (isset($record->gradepass)) {
            $record->gradepass = unformat_float($record->gradepass);
        }

        return parent::create_instance($record, (array)$options);
    }

    /**
     * Create a hippotrack attempt for a particular user at a particular course.
     *
     * @param int $hippotrackid the hippotrack id (from the mdl_quit table, not cmid).
     * @param int $userid the user id.
     * @param array $forcedrandomquestions slot => questionid. Optional,
     *      used with random questions, to control which one is 'randomly' selected in that slot.
     * @param array $forcedvariants slot => variantno. Optional. Optional,
     *      used with question where get_num_variants is > 1, to control which
     *      variants is 'randomly' selected.
     * @return stdClass the new attempt.
     */
    public function create_attempt($hippotrackid, $userid, array $forcedrandomquestions = [],
            array $forcedvariants = []) {
        // Build hippotrack object and load questions.
        $hippotrackobj = hippotrack::create($hippotrackid, $userid);

        $attemptnumber = 1;
        $attempt = null;

        if ($attempts = hippotrack_get_user_attempts($hippotrackid, $userid, 'all', true)) {
            // There is/are already an attempt/some attempts.
            // Take the last attempt.
            $attempt = end($attempts);
            // Take the attempt number of the last attempt and increase it.
            $attemptnumber = $attempt->attempt + 1;
        }

        return hippotrack_prepare_and_start_new_attempt($hippotrackobj, $attemptnumber, $attempt, false,
                $forcedrandomquestions, $forcedvariants);
    }

    /**
     * Submit responses to a hippotrack attempt.
     *
     * To be realistic, you should ensure that $USER is set to the user whose attempt
     * it is before calling this.
     *
     * @param int $attemptid the id of the attempt which is being
     * @param array $responses array responses to submit. See description on
     *      {@link core_question_generator::get_simulated_post_data_for_questions_in_usage()}.
     * @param bool $checkbutton if simulate a click on the check button for each question, else simulate save.
     *      This should only be used with behaviours that have a check button.
     * @param bool $finishattempt if true, the attempt will be submitted.
     */
    public function submit_responses($attemptid, array $responses, $checkbutton, $finishattempt) {
        $questiongenerator = $this->datagenerator->get_plugin_generator('core_question');

        $attemptobj = hippotrack_attempt::create($attemptid);

        $postdata = $questiongenerator->get_simulated_post_data_for_questions_in_usage(
                $attemptobj->get_question_usage(), $responses, $checkbutton);

        $attemptobj->process_submitted_actions(time(), false, $postdata);

        // Bit if a hack for interactive behaviour.
        // TODO handle this in a more plugin-friendly way.
        if ($checkbutton) {
            $postdata = [];
            foreach ($responses as $slot => $notused) {
                $qa = $attemptobj->get_question_attempt($slot);
                if ($qa->get_behaviour() instanceof qbehaviour_interactive && $qa->get_behaviour()->is_try_again_state()) {
                    $postdata[$qa->get_control_field_name('sequencecheck')] = (string)$qa->get_sequence_check_count();
                    $postdata[$qa->get_flag_field_name()] = (string)(int)$qa->is_flagged();
                    $postdata[$qa->get_behaviour_field_name('tryagain')] = 1;
                }
            }

            if ($postdata) {
                $attemptobj->process_submitted_actions(time(), false, $postdata);
            }
        }

        if ($finishattempt) {
            $attemptobj->process_finish(time(), false);
        }
    }

    /**
     * Create a hippotrack override (either user or group).
     *
     * @param array $data must specify hippotrackid, and one of userid or groupid.
     */
    public function create_override(array $data): void {
        global $DB;

        // Validate.
        if (!isset($data['hippotrack'])) {
            throw new coding_exception('Must specify hippotrack (id) when creating a hippotrack override.');
        }

        if (!isset($data['userid']) && !isset($data['groupid'])) {
            throw new coding_exception('Must specify one of userid or groupid when creating a hippotrack override.');
        }

        if (isset($data['userid']) && isset($data['groupid'])) {
            throw new coding_exception('Cannot specify both userid and groupid when creating a hippotrack override.');
        }

        // Create the override.
        $DB->insert_record('hippotrack_overrides', (object) $data);

        // Update any associated calendar events, if necessary.
        hippotrack_update_events($DB->get_record('hippotrack', ['id' => $data['hippotrack']], '*', MUST_EXIST));
    }
}
