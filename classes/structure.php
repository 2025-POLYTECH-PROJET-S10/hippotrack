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
 * Defines the \mod_hippotrack\structure class.
 *
 * @package   mod_hippotrack
 * @copyright 2013 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_hippotrack;
use mod_hippotrack\question\bank\qbank_helper;

defined('MOODLE_INTERNAL') || die();

/**
 * HippoTrack structure class.
 *
 * The structure of the hippotrack. That is, which questions it is built up
 * from. This is used on the Edit hippotrack page (edit.php) and also when
 * starting an attempt at the hippotrack (startattempt.php). Once an attempt
 * has been started, then the attempt holds the specific set of questions
 * that that student should answer, and we no longer use this class.
 *
 * @copyright 2014 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class structure {
    /** @var \hippotrack the hippotrack this is the structure of. */
    protected $hippotrackobj = null;

    /**
     * @var \stdClass[] the questions in this hippotrack. Contains the row from the questions
     * table, with the data from the hippotrack_slots table added, and also question_categories.contextid.
     */
    protected $questions = array();

    /** @var \stdClass[] hippotrack_slots.slot => the hippotrack_slots rows for this hippotrack, agumented by sectionid. */
    protected $slotsinorder = array();

    /**
     * @var \stdClass[] currently a dummy. Holds data that will match the
     * hippotrack_sections, once it exists.
     */
    protected $sections = array();

    /** @var bool caches the results of can_be_edited. */
    protected $canbeedited = null;

    /** @var bool caches the results of can_add_random_question. */
    protected $canaddrandom = null;

    /** @var bool tracks whether tags have been loaded */
    protected $hasloadedtags = false;

    /**
     * @var \stdClass[] the tags for slots. Indexed by slot id.
     */
    protected $slottags = array();

    /**
     * Create an instance of this class representing an empty hippotrack.
     * @return structure
     */
    public static function create() {
        return new self();
    }

    /**
     * Create an instance of this class representing the structure of a given hippotrack.
     * @param \hippotrack $hippotrackobj the hippotrack.
     * @return structure
     */
    public static function create_for_hippotrack($hippotrackobj) {
        $structure = self::create();
        $structure->hippotrackobj = $hippotrackobj;
        $structure->populate_structure();
        return $structure;
    }

    /**
     * Whether there are any questions in the hippotrack.
     * @return bool true if there is at least one question in the hippotrack.
     */
    public function has_questions() {
        return !empty($this->questions);
    }

    /**
     * Get the number of questions in the hippotrack.
     * @return int the number of questions in the hippotrack.
     */
    public function get_question_count() {
        return count($this->questions);
    }

    /**
     * Get the information about the question with this id.
     * @param int $questionid The question id.
     * @return \stdClass the data from the questions table, augmented with
     * question_category.contextid, and the hippotrack_slots data for the question in this hippotrack.
     */
    public function get_question_by_id($questionid) {
        return $this->questions[$questionid];
    }

    /**
     * Get the information about the question in a given slot.
     * @param int $slotnumber the index of the slot in question.
     * @return \stdClass the data from the questions table, augmented with
     * question_category.contextid, and the hippotrack_slots data for the question in this hippotrack.
     */
    public function get_question_in_slot($slotnumber) {
        return $this->questions[$this->slotsinorder[$slotnumber]->questionid];
    }

    /**
     * Get the information about the question name in a given slot.
     * @param int $slotnumber the index of the slot in question.
     * @return \stdClass the data from the questions table, augmented with
     */
    public function get_question_name_in_slot($slotnumber) {
        return $this->questions[$this->slotsinorder[$slotnumber]->name];
    }

    /**
     * Get the displayed question number (or 'i') for a given slot.
     * @param int $slotnumber the index of the slot in question.
     * @return string the question number ot display for this slot.
     */
    public function get_displayed_number_for_slot($slotnumber) {
        return $this->slotsinorder[$slotnumber]->displayednumber;
    }

    /**
     * Get the page a given slot is on.
     * @param int $slotnumber the index of the slot in question.
     * @return int the page number of the page that slot is on.
     */
    public function get_page_number_for_slot($slotnumber) {
        return $this->slotsinorder[$slotnumber]->page;
    }

    /**
     * Get the slot id of a given slot slot.
     * @param int $slotnumber the index of the slot in question.
     * @return int the page number of the page that slot is on.
     */
    public function get_slot_id_for_slot($slotnumber) {
        return $this->slotsinorder[$slotnumber]->id;
    }

    /**
     * Get the question type in a given slot.
     * @param int $slotnumber the index of the slot in question.
     * @return string the question type (e.g. multichoice).
     */
    public function get_question_type_for_slot($slotnumber) {
        return $this->questions[$this->slotsinorder[$slotnumber]->questionid]->qtype;
    }

    /**
     * Whether it would be possible, given the question types, etc. for the
     * question in the given slot to require that the previous question had been
     * answered before this one is displayed.
     * @param int $slotnumber the index of the slot in question.
     * @return bool can this question require the previous one.
     */
    public function can_question_depend_on_previous_slot($slotnumber) {
        return $slotnumber > 1 && $this->can_finish_during_the_attempt($slotnumber - 1);
    }

    /**
     * Whether it is possible for another question to depend on this one finishing.
     * Note that the answer is not exact, because of random questions, and sometimes
     * questions cannot be depended upon because of hippotrack options.
     * @param int $slotnumber the index of the slot in question.
     * @return bool can this question finish naturally during the attempt?
     */
    public function can_finish_during_the_attempt($slotnumber) {
        if ($this->hippotrackobj->get_navigation_method() == HIPPOTRACK_NAVMETHOD_SEQ) {
            return false;
        }

        if ($this->slotsinorder[$slotnumber]->section->shufflequestions) {
            return false;
        }

        if (in_array($this->get_question_type_for_slot($slotnumber), array('random', 'missingtype'))) {
            return \question_engine::can_questions_finish_during_the_attempt(
                    $this->hippotrackobj->get_hippotrack()->preferredbehaviour);
        }

        if (isset($this->slotsinorder[$slotnumber]->canfinish)) {
            return $this->slotsinorder[$slotnumber]->canfinish;
        }

        try {
            $quba = \question_engine::make_questions_usage_by_activity('mod_hippotrack', $this->hippotrackobj->get_context());
            $tempslot = $quba->add_question(\question_bank::load_question(
                    $this->slotsinorder[$slotnumber]->questionid));
            $quba->set_preferred_behaviour($this->hippotrackobj->get_hippotrack()->preferredbehaviour);
            $quba->start_all_questions();

            $this->slotsinorder[$slotnumber]->canfinish = $quba->can_question_finish_during_attempt($tempslot);
            return $this->slotsinorder[$slotnumber]->canfinish;
        } catch (\Exception $e) {
            // If the question fails to start, this should not block editing.
            return false;
        }
    }

    /**
     * Whether it would be possible, given the question types, etc. for the
     * question in the given slot to require that the previous question had been
     * answered before this one is displayed.
     * @param int $slotnumber the index of the slot in question.
     * @return bool can this question require the previous one.
     */
    public function is_question_dependent_on_previous_slot($slotnumber) {
        return $this->slotsinorder[$slotnumber]->requireprevious;
    }

    /**
     * Is a particular question in this attempt a real question, or something like a description.
     * @param int $slotnumber the index of the slot in question.
     * @return bool whether that question is a real question.
     */
    public function is_real_question($slotnumber) {
        return $this->get_question_in_slot($slotnumber)->length != 0;
    }

    /**
     * Does the current user have '...use' capability over the question(s) in a given slot?
     *
     *
     * @param int $slotnumber the index of the slot in question.
     * @return bool true if they have the required capability.
     */
    public function has_use_capability(int $slotnumber): bool {
        $slot = $this->slotsinorder[$slotnumber];
        if (is_numeric($slot->questionid)) {
            // Non-random question.
            return question_has_capability_on($this->get_question_by_id($slot->questionid), 'use');
        } else {
            // Random question.
            $context = \context::instance_by_id($slot->contextid);
            return has_capability('moodle/question:useall', $context);
        }
    }

    /**
     * Get the course id that the hippotrack belongs to.
     * @return int the course.id for the hippotrack.
     */
    public function get_courseid() {
        return $this->hippotrackobj->get_courseid();
    }

    /**
     * Get the course module id of the hippotrack.
     * @return int the course_modules.id for the hippotrack.
     */
    public function get_cmid() {
        return $this->hippotrackobj->get_cmid();
    }

    /**
     * Get id of the hippotrack.
     * @return int the hippotrack.id for the hippotrack.
     */
    public function get_hippotrackid() {
        return $this->hippotrackobj->get_hippotrackid();
    }

    /**
     * Get the hippotrack object.
     * @return \stdClass the hippotrack settings row from the database.
     */
    public function get_hippotrack() {
        return $this->hippotrackobj->get_hippotrack();
    }

    /**
     * HippoTrackzes can only be repaginated if they have not been attempted, the
     * questions are not shuffled, and there are two or more questions.
     * @return bool whether this hippotrack can be repaginated.
     */
    public function can_be_repaginated() {
        return $this->can_be_edited() && $this->get_question_count() >= 2;
    }

    /**
     * HippoTrackzes can only be edited if they have not been attempted.
     * @return bool whether the hippotrack can be edited.
     */
    public function can_be_edited() {
        if ($this->canbeedited === null) {
            $this->canbeedited = !hippotrack_has_attempts($this->hippotrackobj->get_hippotrackid());
        }
        return $this->canbeedited;
    }

    /**
     * This hippotrack can only be edited if they have not been attempted.
     * Throw an exception if this is not the case.
     */
    public function check_can_be_edited() {
        if (!$this->can_be_edited()) {
            $reportlink = hippotrack_attempt_summary_link_to_reports($this->get_hippotrack(),
                    $this->hippotrackobj->get_cm(), $this->hippotrackobj->get_context());
            throw new \moodle_exception('cannoteditafterattempts', 'hippotrack',
                    new \moodle_url('/mod/hippotrack/edit.php', array('cmid' => $this->get_cmid())), $reportlink);
        }
    }

    /**
     * How many questions are allowed per page in the hippotrack.
     * This setting controls how frequently extra page-breaks should be inserted
     * automatically when questions are added to the hippotrack.
     * @return int the number of questions that should be on each page of the
     * hippotrack by default.
     */
    public function get_questions_per_page() {
        return $this->hippotrackobj->get_hippotrack()->questionsperpage;
    }

    /**
     * Get hippotrack slots.
     * @return \stdClass[] the slots in this hippotrack.
     */
    public function get_slots() {
        return array_column($this->slotsinorder, null, 'id');
    }

    /**
     * Is this slot the first one on its page?
     * @param int $slotnumber the index of the slot in question.
     * @return bool whether this slot the first one on its page.
     */
    public function is_first_slot_on_page($slotnumber) {
        if ($slotnumber == 1) {
            return true;
        }
        return $this->slotsinorder[$slotnumber]->page != $this->slotsinorder[$slotnumber - 1]->page;
    }

    /**
     * Is this slot the last one on its page?
     * @param int $slotnumber the index of the slot in question.
     * @return bool whether this slot the last one on its page.
     */
    public function is_last_slot_on_page($slotnumber) {
        if (!isset($this->slotsinorder[$slotnumber + 1])) {
            return true;
        }
        return $this->slotsinorder[$slotnumber]->page != $this->slotsinorder[$slotnumber + 1]->page;
    }

    /**
     * Is this slot the last one in its section?
     * @param int $slotnumber the index of the slot in question.
     * @return bool whether this slot the last one on its section.
     */
    public function is_last_slot_in_section($slotnumber) {
        return $slotnumber == $this->slotsinorder[$slotnumber]->section->lastslot;
    }

    /**
     * Is this slot the only one in its section?
     * @param int $slotnumber the index of the slot in question.
     * @return bool whether this slot the only one on its section.
     */
    public function is_only_slot_in_section($slotnumber) {
        return $this->slotsinorder[$slotnumber]->section->firstslot ==
                $this->slotsinorder[$slotnumber]->section->lastslot;
    }

    /**
     * Is this slot the last one in the hippotrack?
     * @param int $slotnumber the index of the slot in question.
     * @return bool whether this slot the last one in the hippotrack.
     */
    public function is_last_slot_in_hippotrack($slotnumber) {
        end($this->slotsinorder);
        return $slotnumber == key($this->slotsinorder);
    }

    /**
     * Is this the first section in the hippotrack?
     * @param \stdClass $section the hippotrack_sections row.
     * @return bool whether this is first section in the hippotrack.
     */
    public function is_first_section($section) {
        return $section->firstslot == 1;
    }

    /**
     * Is this the last section in the hippotrack?
     * @param \stdClass $section the hippotrack_sections row.
     * @return bool whether this is first section in the hippotrack.
     */
    public function is_last_section($section) {
        return $section->id == end($this->sections)->id;
    }

    /**
     * Does this section only contain one slot?
     * @param \stdClass $section the hippotrack_sections row.
     * @return bool whether this section contains only one slot.
     */
    public function is_only_one_slot_in_section($section) {
        return $section->firstslot == $section->lastslot;
    }

    /**
     * Get the final slot in the hippotrack.
     * @return \stdClass the hippotrack_slots for for the final slot in the hippotrack.
     */
    public function get_last_slot() {
        return end($this->slotsinorder);
    }

    /**
     * Get a slot by it's id. Throws an exception if it is missing.
     * @param int $slotid the slot id.
     * @return \stdClass the requested hippotrack_slots row.
     * @throws \coding_exception
     */
    public function get_slot_by_id($slotid) {
        foreach ($this->slotsinorder as $slot) {
            if ($slot->id == $slotid) {
                return $slot;
            }
        }

        throw new \coding_exception('The \'slotid\' could not be found.');
    }

    /**
     * Get a slot by it's slot number. Throws an exception if it is missing.
     *
     * @param int $slotnumber The slot number
     * @return \stdClass
     * @throws \coding_exception
     */
    public function get_slot_by_number($slotnumber) {
        if (!array_key_exists($slotnumber, $this->slotsinorder)) {
            throw new \coding_exception('The \'slotnumber\' could not be found.');
        }
        return $this->slotsinorder[$slotnumber];
    }

    /**
     * Check whether adding a section heading is possible
     * @param int $pagenumber the number of the page.
     * @return boolean
     */
    public function can_add_section_heading($pagenumber) {
        // There is a default section heading on this page,
        // do not show adding new section heading in the Add menu.
        if ($pagenumber == 1) {
            return false;
        }
        // Get an array of firstslots.
        $firstslots = array();
        foreach ($this->sections as $section) {
            $firstslots[] = $section->firstslot;
        }
        foreach ($this->slotsinorder as $slot) {
            if ($slot->page == $pagenumber) {
                if (in_array($slot->slot, $firstslots)) {
                    return false;
                }
            }
        }
        // Do not show the adding section heading on the last add menu.
        if ($pagenumber == 0) {
            return false;
        }
        return true;
    }

    /**
     * Get all the slots in a section of the hippotrack.
     * @param int $sectionid the section id.
     * @return int[] slot numbers.
     */
    public function get_slots_in_section($sectionid) {
        $slots = array();
        foreach ($this->slotsinorder as $slot) {
            if ($slot->section->id == $sectionid) {
                $slots[] = $slot->slot;
            }
        }
        return $slots;
    }

    /**
     * Get all the sections of the hippotrack.
     * @return \stdClass[] the sections in this hippotrack.
     */
    public function get_sections() {
        return $this->sections;
    }

    /**
     * Get a particular section by id.
     * @return \stdClass the section.
     */
    public function get_section_by_id($sectionid) {
        return $this->sections[$sectionid];
    }

    /**
     * Get the number of questions in the hippotrack.
     * @return int the number of questions in the hippotrack.
     */
    public function get_section_count() {
        return count($this->sections);
    }

    /**
     * Get the overall hippotrack grade formatted for display.
     * @return string the maximum grade for this hippotrack.
     */
    public function formatted_hippotrack_grade() {
        return hippotrack_format_grade($this->get_hippotrack(), $this->get_hippotrack()->grade);
    }

    /**
     * Get the maximum mark for a question, formatted for display.
     * @param int $slotnumber the index of the slot in question.
     * @return string the maximum mark for the question in this slot.
     */
    public function formatted_question_grade($slotnumber) {
        return hippotrack_format_question_grade($this->get_hippotrack(), $this->slotsinorder[$slotnumber]->maxmark);
    }

    /**
     * Get the number of decimal places for displyaing overall hippotrack grades or marks.
     * @return int the number of decimal places.
     */
    public function get_decimal_places_for_grades() {
        return $this->get_hippotrack()->decimalpoints;
    }

    /**
     * Get the number of decimal places for displyaing question marks.
     * @return int the number of decimal places.
     */
    public function get_decimal_places_for_question_marks() {
        return hippotrack_get_grade_format($this->get_hippotrack());
    }

    /**
     * Get any warnings to show at the top of the edit page.
     * @return string[] array of strings.
     */
    public function get_edit_page_warnings() {
        $warnings = array();

        if (hippotrack_has_attempts($this->hippotrackobj->get_hippotrackid())) {
            $reviewlink = hippotrack_attempt_summary_link_to_reports($this->hippotrackobj->get_hippotrack(),
                    $this->hippotrackobj->get_cm(), $this->hippotrackobj->get_context());
            $warnings[] = get_string('cannoteditafterattempts', 'hippotrack', $reviewlink);
        }

        return $warnings;
    }

    /**
     * Get the date information about the current state of the hippotrack.
     * @return string[] array of two strings. First a short summary, then a longer
     * explanation of the current state, e.g. for a tool-tip.
     */
    public function get_dates_summary() {
        $timenow = time();
        $hippotrack = $this->hippotrackobj->get_hippotrack();

        // Exact open and close dates for the tool-tip.
        $dates = array();
        if ($hippotrack->timeopen > 0) {
            if ($timenow > $hippotrack->timeopen) {
                $dates[] = get_string('hippotrackopenedon', 'hippotrack', userdate($hippotrack->timeopen));
            } else {
                $dates[] = get_string('hippotrackwillopen', 'hippotrack', userdate($hippotrack->timeopen));
            }
        }
        if ($hippotrack->timeclose > 0) {
            if ($timenow > $hippotrack->timeclose) {
                $dates[] = get_string('hippotrackclosed', 'hippotrack', userdate($hippotrack->timeclose));
            } else {
                $dates[] = get_string('hippotrackcloseson', 'hippotrack', userdate($hippotrack->timeclose));
            }
        }
        if (empty($dates)) {
            $dates[] = get_string('alwaysavailable', 'hippotrack');
        }
        $explanation = implode(', ', $dates);

        // Brief summary on the page.
        if ($timenow < $hippotrack->timeopen) {
            $currentstatus = get_string('hippotrackisclosedwillopen', 'hippotrack',
                    userdate($hippotrack->timeopen, get_string('strftimedatetimeshort', 'langconfig')));
        } else if ($hippotrack->timeclose && $timenow <= $hippotrack->timeclose) {
            $currentstatus = get_string('hippotrackisopenwillclose', 'hippotrack',
                    userdate($hippotrack->timeclose, get_string('strftimedatetimeshort', 'langconfig')));
        } else if ($hippotrack->timeclose && $timenow > $hippotrack->timeclose) {
            $currentstatus = get_string('hippotrackisclosed', 'hippotrack');
        } else {
            $currentstatus = get_string('hippotrackisopen', 'hippotrack');
        }

        return array($currentstatus, $explanation);
    }

    /**
     * Set up this class with the structure for a given hippotrack.
     */
    protected function populate_structure() {
        global $DB;

        $slots = qbank_helper::get_question_structure($this->hippotrackobj->get_hippotrackid(), $this->hippotrackobj->get_context());

        $this->questions = [];
        $this->slotsinorder = [];
        foreach ($slots as $slotdata) {
            $this->questions[$slotdata->questionid] = $slotdata;

            $slot = clone($slotdata);
            $slot->hippotrackid = $this->hippotrackobj->get_hippotrackid();
            $this->slotsinorder[$slot->slot] = $slot;
        }

        // Get hippotrack sections in ascending order of the firstslot.
        $this->sections = $DB->get_records('hippotrack_sections', ['hippotrackid' => $this->hippotrackobj->get_hippotrackid()], 'firstslot');
        $this->populate_slots_with_sections();
        $this->populate_question_numbers();
    }

    /**
     * Fill in the section ids for each slot.
     */
    public function populate_slots_with_sections() {
        $sections = array_values($this->sections);
        foreach ($sections as $i => $section) {
            if (isset($sections[$i + 1])) {
                $section->lastslot = $sections[$i + 1]->firstslot - 1;
            } else {
                $section->lastslot = count($this->slotsinorder);
            }
            for ($slot = $section->firstslot; $slot <= $section->lastslot; $slot += 1) {
                $this->slotsinorder[$slot]->section = $section;
            }
        }
    }

    /**
     * Number the questions.
     */
    protected function populate_question_numbers() {
        $number = 1;
        foreach ($this->slotsinorder as $slot) {
            if ($this->questions[$slot->questionid]->length == 0) {
                $slot->displayednumber = get_string('infoshort', 'hippotrack');
            } else {
                $slot->displayednumber = $number;
                $number += 1;
            }
        }
    }

    /**
     * Get the version options to show on the Questions page for a particular question.
     *
     * @param int $slotnumber which slot to get the choices for.
     * @return \stdClass[] other versions of this question. Each object has fields versionid,
     *       version and selected. Array is returned most recent version first.
     */
    public function get_version_choices_for_slot(int $slotnumber): array {
        $slot = $this->get_slot_by_number($slotnumber);

        // Get all the versions which exist.
        $versions = qbank_helper::get_version_options($slot->questionid);
        $latestversion = reset($versions);

        // Format the choices for display.
        $versionoptions = [];
        foreach ($versions as $version) {
            $version->selected = $version->version === $slot->requestedversion;

            if ($version->version === $latestversion->version) {
                $version->versionvalue = get_string('questionversionlatest', 'hippotrack', $version->version);
            } else {
                $version->versionvalue = get_string('questionversion', 'hippotrack', $version->version);
            }

            $versionoptions[] = $version;
        }

        // Make a choice for 'Always latest'.
        $alwaysuselatest = new \stdClass();
        $alwaysuselatest->versionid = 0;
        $alwaysuselatest->version = 0;
        $alwaysuselatest->versionvalue = get_string('alwayslatest', 'hippotrack');
        $alwaysuselatest->selected = $slot->requestedversion === null;
        array_unshift($versionoptions, $alwaysuselatest);

        return $versionoptions;
    }

    /**
     * Move a slot from its current location to a new location.
     *
     * After callig this method, this class will be in an invalid state, and
     * should be discarded if you want to manipulate the structure further.
     *
     * @param int $idmove id of slot to be moved
     * @param int $idmoveafter id of slot to come before slot being moved
     * @param int $page new page number of slot being moved
     * @param bool $insection if the question is moving to a place where a new
     *      section starts, include it in that section.
     * @return void
     */
    public function move_slot($idmove, $idmoveafter, $page) {
        global $DB;

        $this->check_can_be_edited();

        $movingslot = $this->get_slot_by_id($idmove);
        if (empty($movingslot)) {
            throw new \moodle_exception('Bad slot ID ' . $idmove);
        }
        $movingslotnumber = (int) $movingslot->slot;

        // Empty target slot means move slot to first.
        if (empty($idmoveafter)) {
            $moveafterslotnumber = 0;
        } else {
            $moveafterslotnumber = (int) $this->get_slot_by_id($idmoveafter)->slot;
        }

        // If the action came in as moving a slot to itself, normalise this to
        // moving the slot to after the previous slot.
        if ($moveafterslotnumber == $movingslotnumber) {
            $moveafterslotnumber = $moveafterslotnumber - 1;
        }

        $followingslotnumber = $moveafterslotnumber + 1;
        // Prevent checking against non-existance slot when already at the last slot.
        if ($followingslotnumber == $movingslotnumber && !$this->is_last_slot_in_hippotrack($followingslotnumber)) {
            $followingslotnumber += 1;
        }

        // Check the target page number is OK.
        if ($page == 0 || $page === '') {
            $page = 1;
        }
        if (($moveafterslotnumber > 0 && $page < $this->get_page_number_for_slot($moveafterslotnumber)) ||
                $page < 1) {
            throw new \coding_exception('The target page number is too small.');
        } else if (!$this->is_last_slot_in_hippotrack($moveafterslotnumber) &&
                $page > $this->get_page_number_for_slot($followingslotnumber)) {
            throw new \coding_exception('The target page number is too large.');
        }

        // Work out how things are being moved.
        $slotreorder = array();
        if ($moveafterslotnumber > $movingslotnumber) {
            // Moving down.
            $slotreorder[$movingslotnumber] = $moveafterslotnumber;
            for ($i = $movingslotnumber; $i < $moveafterslotnumber; $i++) {
                $slotreorder[$i + 1] = $i;
            }

            $headingmoveafter = $movingslotnumber;
            if ($this->is_last_slot_in_hippotrack($moveafterslotnumber) ||
                    $page == $this->get_page_number_for_slot($moveafterslotnumber + 1)) {
                // We are moving to the start of a section, so that heading needs
                // to be included in the ones that move up.
                $headingmovebefore = $moveafterslotnumber + 1;
            } else {
                $headingmovebefore = $moveafterslotnumber;
            }
            $headingmovedirection = -1;

        } else if ($moveafterslotnumber < $movingslotnumber - 1) {
            // Moving up.
            $slotreorder[$movingslotnumber] = $moveafterslotnumber + 1;
            for ($i = $moveafterslotnumber + 1; $i < $movingslotnumber; $i++) {
                $slotreorder[$i] = $i + 1;
            }

            if ($page == $this->get_page_number_for_slot($moveafterslotnumber + 1)) {
                // Moving to the start of a section, don't move that section.
                $headingmoveafter = $moveafterslotnumber + 1;
            } else {
                // Moving tot the end of the previous section, so move the heading down too.
                $headingmoveafter = $moveafterslotnumber;
            }
            $headingmovebefore = $movingslotnumber + 1;
            $headingmovedirection = 1;
        } else {
            // Staying in the same place, but possibly changing page/section.
            if ($page > $movingslot->page) {
                $headingmoveafter = $movingslotnumber;
                $headingmovebefore = $movingslotnumber + 2;
                $headingmovedirection = -1;
            } else if ($page < $movingslot->page) {
                $headingmoveafter = $movingslotnumber - 1;
                $headingmovebefore = $movingslotnumber + 1;
                $headingmovedirection = 1;
            } else {
                return; // Nothing to do.
            }
        }

        if ($this->is_only_slot_in_section($movingslotnumber)) {
            throw new \coding_exception('You cannot remove the last slot in a section.');
        }

        $trans = $DB->start_delegated_transaction();

        // Slot has moved record new order.
        if ($slotreorder) {
            update_field_with_unique_index('hippotrack_slots', 'slot', $slotreorder,
                    array('hippotrackid' => $this->get_hippotrackid()));
        }

        // Page has changed. Record it.
        if ($movingslot->page != $page) {
            $DB->set_field('hippotrack_slots', 'page', $page,
                    array('id' => $movingslot->id));
        }

        // Update section fist slots.
        hippotrack_update_section_firstslots($this->get_hippotrackid(), $headingmovedirection,
                $headingmoveafter, $headingmovebefore);

        // If any pages are now empty, remove them.
        $emptypages = $DB->get_fieldset_sql("
                SELECT DISTINCT page - 1
                  FROM {hippotrack_slots} slot
                 WHERE hippotrackid = ?
                   AND page > 1
                   AND NOT EXISTS (SELECT 1 FROM {hippotrack_slots} WHERE hippotrackid = ? AND page = slot.page - 1)
              ORDER BY page - 1 DESC
                ", array($this->get_hippotrackid(), $this->get_hippotrackid()));

        foreach ($emptypages as $emptypage) {
            $DB->execute("
                    UPDATE {hippotrack_slots}
                       SET page = page - 1
                     WHERE hippotrackid = ?
                       AND page > ?
                    ", array($this->get_hippotrackid(), $emptypage));
        }

        $trans->allow_commit();

        // Log slot moved event.
        $event = \mod_hippotrack\event\slot_moved::create([
            'context' => $this->hippotrackobj->get_context(),
            'objectid' => $idmove,
            'other' => [
                'hippotrackid' => $this->hippotrackobj->get_hippotrackid(),
                'previousslotnumber' => $movingslotnumber,
                'afterslotnumber' => $moveafterslotnumber,
                'page' => $page
             ]
        ]);
        $event->trigger();
    }

    /**
     * Refresh page numbering of hippotrack slots.
     * @param \stdClass[] $slots (optional) array of slot objects.
     * @return \stdClass[] array of slot objects.
     */
    public function refresh_page_numbers($slots = array()) {
        global $DB;
        // Get slots ordered by page then slot.
        if (!count($slots)) {
            $slots = $DB->get_records('hippotrack_slots', array('hippotrackid' => $this->get_hippotrackid()), 'slot, page');
        }

        // Loop slots. Start Page number at 1 and increment as required.
        $pagenumbers = array('new' => 0, 'old' => 0);

        foreach ($slots as $slot) {
            if ($slot->page !== $pagenumbers['old']) {
                $pagenumbers['old'] = $slot->page;
                ++$pagenumbers['new'];
            }

            if ($pagenumbers['new'] == $slot->page) {
                continue;
            }
            $slot->page = $pagenumbers['new'];
        }

        return $slots;
    }

    /**
     * Refresh page numbering of hippotrack slots and save to the database.
     * @param \stdClass $hippotrack the hippotrack object.
     * @return \stdClass[] array of slot objects.
     */
    public function refresh_page_numbers_and_update_db() {
        global $DB;
        $this->check_can_be_edited();

        $slots = $this->refresh_page_numbers();

        // Record new page order.
        foreach ($slots as $slot) {
            $DB->set_field('hippotrack_slots', 'page', $slot->page,
                    array('id' => $slot->id));
        }

        return $slots;
    }

    /**
     * Remove a slot from a hippotrack
     *
     * @param int $slotnumber The number of the slot to be deleted.
     * @throws \coding_exception
     */
    public function remove_slot($slotnumber) {
        global $DB;

        $this->check_can_be_edited();

        if ($this->is_only_slot_in_section($slotnumber) && $this->get_section_count() > 1) {
            throw new \coding_exception('You cannot remove the last slot in a section.');
        }

        $slot = $DB->get_record('hippotrack_slots', array('hippotrackid' => $this->get_hippotrackid(), 'slot' => $slotnumber));
        if (!$slot) {
            return;
        }
        $maxslot = $DB->get_field_sql('SELECT MAX(slot) FROM {hippotrack_slots} WHERE hippotrackid = ?', array($this->get_hippotrackid()));

        $trans = $DB->start_delegated_transaction();
        // Delete the reference if its a question.
        $questionreference = $DB->get_record('question_references',
                ['component' => 'mod_hippotrack', 'questionarea' => 'slot', 'itemid' => $slot->id]);
        if ($questionreference) {
            $DB->delete_records('question_references', ['id' => $questionreference->id]);
        }
        // Delete the set reference if its a random question.
        $questionsetreference = $DB->get_record('question_set_references',
                ['component' => 'mod_hippotrack', 'questionarea' => 'slot', 'itemid' => $slot->id]);
        if ($questionsetreference) {
            $DB->delete_records('question_set_references',
                ['id' => $questionsetreference->id, 'component' => 'mod_hippotrack', 'questionarea' => 'slot']);
        }
        $DB->delete_records('hippotrack_slots', array('id' => $slot->id));
        for ($i = $slot->slot + 1; $i <= $maxslot; $i++) {
            $DB->set_field('hippotrack_slots', 'slot', $i - 1,
                    array('hippotrackid' => $this->get_hippotrackid(), 'slot' => $i));
            $this->slotsinorder[$i]->slot = $i - 1;
            $this->slotsinorder[$i - 1] = $this->slotsinorder[$i];
            unset($this->slotsinorder[$i]);
        }

        hippotrack_update_section_firstslots($this->get_hippotrackid(), -1, $slotnumber);
        foreach ($this->sections as $key => $section) {
            if ($section->firstslot > $slotnumber) {
                $this->sections[$key]->firstslot--;
            }
        }
        $this->populate_slots_with_sections();
        $this->populate_question_numbers();
        $this->unset_question($slot->id);

        $this->refresh_page_numbers_and_update_db();

        $trans->allow_commit();

        // Log slot deleted event.
        $event = \mod_hippotrack\event\slot_deleted::create([
            'context' => $this->hippotrackobj->get_context(),
            'objectid' => $slot->id,
            'other' => [
                'hippotrackid' => $this->get_hippotrackid(),
                'slotnumber' => $slotnumber,
            ]
        ]);
        $event->trigger();
    }

    /**
     * Unset the question object after deletion.
     *
     * @param int $slotid
     */
    public function unset_question($slotid) {
        foreach ($this->questions as $key => $question) {
            if ($question->slotid === $slotid) {
                unset($this->questions[$key]);
            }
        }
    }

    /**
     * Change the max mark for a slot.
     *
     * Saves changes to the question grades in the hippotrack_slots table and any
     * corresponding question_attempts.
     * It does not update 'sumgrades' in the hippotrack table.
     *
     * @param \stdClass $slot row from the hippotrack_slots table.
     * @param float $maxmark the new maxmark.
     * @return bool true if the new grade is different from the old one.
     */
    public function update_slot_maxmark($slot, $maxmark) {
        global $DB;

        if (abs($maxmark - $slot->maxmark) < 1e-7) {
            // Grade has not changed. Nothing to do.
            return false;
        }

        $trans = $DB->start_delegated_transaction();
        $previousmaxmark = $slot->maxmark;
        $slot->maxmark = $maxmark;
        $DB->update_record('hippotrack_slots', $slot);
        \question_engine::set_max_mark_in_attempts(new \qubaids_for_hippotrack($slot->hippotrackid),
                $slot->slot, $maxmark);
        $trans->allow_commit();

        // Log slot mark updated event.
        // We use $num + 0 as a trick to remove the useless 0 digits from decimals.
        $event = \mod_hippotrack\event\slot_mark_updated::create([
            'context' => $this->hippotrackobj->get_context(),
            'objectid' => $slot->id,
            'other' => [
                'hippotrackid' => $this->get_hippotrackid(),
                'previousmaxmark' => $previousmaxmark + 0,
                'newmaxmark' => $maxmark + 0
            ]
        ]);
        $event->trigger();

        return true;
    }

    /**
     * Set whether the question in a particular slot requires the previous one.
     * @param int $slotid id of slot.
     * @param bool $requireprevious if true, set this question to require the previous one.
     */
    public function update_question_dependency($slotid, $requireprevious) {
        global $DB;
        $DB->set_field('hippotrack_slots', 'requireprevious', $requireprevious, array('id' => $slotid));

        // Log slot require previous event.
        $event = \mod_hippotrack\event\slot_requireprevious_updated::create([
            'context' => $this->hippotrackobj->get_context(),
            'objectid' => $slotid,
            'other' => [
                'hippotrackid' => $this->get_hippotrackid(),
                'requireprevious' => $requireprevious ? 1 : 0
            ]
        ]);
        $event->trigger();
    }

    /**
     * Add/Remove a pagebreak.
     *
     * Saves changes to the slot page relationship in the hippotrack_slots table and reorders the paging
     * for subsequent slots.
     *
     * @param int $slotid id of slot which we will add/remove the page break before.
     * @param int $type repaginate::LINK or repaginate::UNLINK.
     * @return \stdClass[] array of slot objects.
     */
    public function update_page_break($slotid, $type) {
        global $DB;

        $this->check_can_be_edited();

        $hippotrackslots = $DB->get_records('hippotrack_slots', array('hippotrackid' => $this->get_hippotrackid()), 'slot');
        $repaginate = new \mod_hippotrack\repaginate($this->get_hippotrackid(), $hippotrackslots);
        $repaginate->repaginate_slots($hippotrackslots[$slotid]->slot, $type);
        $slots = $this->refresh_page_numbers_and_update_db();

        if ($type == repaginate::LINK) {
            // Log page break created event.
            $event = \mod_hippotrack\event\page_break_deleted::create([
                'context' => $this->hippotrackobj->get_context(),
                'objectid' => $slotid,
                'other' => [
                    'hippotrackid' => $this->get_hippotrackid(),
                    'slotnumber' => $hippotrackslots[$slotid]->slot
                ]
            ]);
            $event->trigger();
        } else {
            // Log page deleted created event.
            $event = \mod_hippotrack\event\page_break_created::create([
                'context' => $this->hippotrackobj->get_context(),
                'objectid' => $slotid,
                'other' => [
                    'hippotrackid' => $this->get_hippotrackid(),
                    'slotnumber' => $hippotrackslots[$slotid]->slot
                ]
            ]);
            $event->trigger();
        }

        return $slots;
    }

    /**
     * Add a section heading on a given page and return the sectionid
     * @param int $pagenumber the number of the page where the section heading begins.
     * @param string|null $heading the heading to add. If not given, a default is used.
     */
    public function add_section_heading($pagenumber, $heading = null) {
        global $DB;
        $section = new \stdClass();
        if ($heading !== null) {
            $section->heading = $heading;
        } else {
            $section->heading = get_string('newsectionheading', 'hippotrack');
        }
        $section->hippotrackid = $this->get_hippotrackid();
        $slotsonpage = $DB->get_records('hippotrack_slots', array('hippotrackid' => $this->get_hippotrackid(), 'page' => $pagenumber), 'slot DESC');
        $firstslot = end($slotsonpage);
        $section->firstslot = $firstslot->slot;
        $section->shufflequestions = 0;
        $sectionid = $DB->insert_record('hippotrack_sections', $section);

        // Log section break created event.
        $event = \mod_hippotrack\event\section_break_created::create([
            'context' => $this->hippotrackobj->get_context(),
            'objectid' => $sectionid,
            'other' => [
                'hippotrackid' => $this->get_hippotrackid(),
                'firstslotnumber' => $firstslot->slot,
                'firstslotid' => $firstslot->id,
                'title' => $section->heading,
            ]
        ]);
        $event->trigger();

        return $sectionid;
    }

    /**
     * Change the heading for a section.
     * @param int $id the id of the section to change.
     * @param string $newheading the new heading for this section.
     */
    public function set_section_heading($id, $newheading) {
        global $DB;
        $section = $DB->get_record('hippotrack_sections', array('id' => $id), '*', MUST_EXIST);
        $section->heading = $newheading;
        $DB->update_record('hippotrack_sections', $section);

        // Log section title updated event.
        $firstslot = $DB->get_record('hippotrack_slots', array('hippotrackid' => $this->get_hippotrackid(), 'slot' => $section->firstslot));
        $event = \mod_hippotrack\event\section_title_updated::create([
            'context' => $this->hippotrackobj->get_context(),
            'objectid' => $id,
            'other' => [
                'hippotrackid' => $this->get_hippotrackid(),
                'firstslotid' => $firstslot ? $firstslot->id : null,
                'firstslotnumber' => $firstslot ? $firstslot->slot : null,
                'newtitle' => $newheading
            ]
        ]);
        $event->trigger();
    }

    /**
     * Change the shuffle setting for a section.
     * @param int $id the id of the section to change.
     * @param bool $shuffle whether this section should be shuffled.
     */
    public function set_section_shuffle($id, $shuffle) {
        global $DB;
        $section = $DB->get_record('hippotrack_sections', array('id' => $id), '*', MUST_EXIST);
        $section->shufflequestions = $shuffle;
        $DB->update_record('hippotrack_sections', $section);

        // Log section shuffle updated event.
        $event = \mod_hippotrack\event\section_shuffle_updated::create([
            'context' => $this->hippotrackobj->get_context(),
            'objectid' => $id,
            'other' => [
                'hippotrackid' => $this->get_hippotrackid(),
                'firstslotnumber' => $section->firstslot,
                'shuffle' => $shuffle
            ]
        ]);
        $event->trigger();
    }

    /**
     * Remove the section heading with the given id
     * @param int $sectionid the section to remove.
     */
    public function remove_section_heading($sectionid) {
        global $DB;
        $section = $DB->get_record('hippotrack_sections', array('id' => $sectionid), '*', MUST_EXIST);
        if ($section->firstslot == 1) {
            throw new \coding_exception('Cannot remove the first section in a hippotrack.');
        }
        $DB->delete_records('hippotrack_sections', array('id' => $sectionid));

        // Log page deleted created event.
        $firstslot = $DB->get_record('hippotrack_slots', array('hippotrackid' => $this->get_hippotrackid(), 'slot' => $section->firstslot));
        $event = \mod_hippotrack\event\section_break_deleted::create([
            'context' => $this->hippotrackobj->get_context(),
            'objectid' => $sectionid,
            'other' => [
                'hippotrackid' => $this->get_hippotrackid(),
                'firstslotid' => $firstslot->id,
                'firstslotnumber' => $firstslot->slot
            ]
        ]);
        $event->trigger();
    }

    /**
     * Whether the current user can add random questions to the hippotrack or not.
     * It is only possible to add a random question if the user has the moodle/question:useall capability
     * on at least one of the contexts related to the one where we are currently editing questions.
     *
     * @return bool
     */
    public function can_add_random_questions() {
        if ($this->canaddrandom === null) {
            $hippotrackcontext = $this->hippotrackobj->get_context();
            $relatedcontexts = new \core_question\local\bank\question_edit_contexts($hippotrackcontext);
            $usablecontexts = $relatedcontexts->having_cap('moodle/question:useall');

            $this->canaddrandom = !empty($usablecontexts);
        }

        return $this->canaddrandom;
    }


    /**
     * Retrieve the list of slot tags for the given slot id.
     *
     * @param  int $slotid The id for the slot
     * @return \stdClass[] The list of slot tag records
     * @deprecated since Moodle 4.0 MDL-71573
     * @todo Final deprecation on Moodle 4.4 MDL-72438
     */
    public function get_slot_tags_for_slot_id($slotid) {
        debugging('Function get_slot_tags_for_slot_id() has been deprecated and the structure
         for this method have been moved to filtercondition in question_set_reference table, please
          use the new structure instead.', DEBUG_DEVELOPER);
        // All the associated code for this method have been removed to get rid of accidental call or errors.
        return [];
    }
}
