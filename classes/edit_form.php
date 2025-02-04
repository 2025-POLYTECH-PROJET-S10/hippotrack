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
 * @package    mod_hippotrack
 * @copyright   2025 Lionel Di Marco <LDiMarco@chu-grenoble.fr>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_hippotrack;

use html_writer;
use Exception;
use moodle_exception;
use moodle_url;
use ArrayIterator;
use stdClass;

global $CFG;
require_once($CFG->libdir . '/formslib.php');

// $PAGE->requires->js('/mod/hippotrack/amd/src/edit_question.js');

class edit_form extends \moodleform
{
	public $cmid;

	function __construct($pageURL, $cmid)
	{
		$this->cmid = $cmid;
		parent::__construct($pageURL);
	}

	/**
	 * Defines forms elements
	 */
	public function definition()
	{
		global $CFG, $DB, $PAGE, $USER, $COURSE;

		$mform = &$this->_form;

		// $questionsArray[] = $mform->createElement('textarea', "number_of_question", get_string('number_of_question', 'mod_hippotrack'), 'rows="2"');

		$repeatableOption = array();

		// //Add question 
		// $mform->addElement('textarea', "number_of_question", get_string('number_of_question', 'mod_hippotrack'), 'rows="2"');
		// // $mform->setType("question_title", PARAM_TEXT);

		echo get_string('edit_form_main_text', 'mod_hippotrack');
		// Adding another correct select element for difficulty
		$difficulty_options = [
			'Fa' => get_string('easy', 'mod_hippotrack'),
			'Di' => get_string('hard', 'mod_hippotrack')
		];


		$questionsArray[] = $mform->createElement('select', 'difficulty', get_string('difficulty_type', 'mod_hippotrack'), $difficulty_options);
		// $mform->setType('difficulty', PARAM_ALPHANUMEXT);


		$this->repeat_elements(
			$questionsArray, //  Array of elements or groups of elements that are to be repeated
			2,// number of times to repeat elements initially
			$repeatableOption,// an options array
			'option_repeats',// name for hidden element storing no of repeats in this form
			'option_add_fields',//name for button to add more fields
			1,//how many fields to add at a time
			get_string('add_exercise', 'mod_hippotrack'),//name of button, {no} is replaced by no of blanks that will be added.
			true,//if true, don't call closeHeaderBefore($addfieldsname).
			'delete_answer',// if specified, treats the no-submit button with this name as a "delete element" button in each of the elements.
		);

		// Add standard buttons, common to all modules.
		$this->add_action_buttons();
	}



	// /**
	//  * Add the answers text spot in the form for a question
	//  */
	// public function add_answer()
	// {
	// 	global $DB, $PAGE, $USER, $COURSE;
	// 	$mform = &$this->_form;

	// 	$answerArray[] = $mform->createElement('textarea', "answer_text", "Answer {no}:", 'rows="3"');
	// 	$mform->setType("answer_text", PARAM_TEXT);

	// 	$answerArray[] = $mform->createElement('submit', "delete_answer", "Remove answer", [], false);

	// 	//Add the is correct check box
	// 	$answerArray[] = $mform->createElement(
	// 		'advcheckbox',
	// 		'is_correct',
	// 		get_string('is_correct', 'mod_hippotrack'),
	// 		get_string('is_answer_correct', 'mod_hippotrack'),
	// 		array('group' => 1),
	// 		array(0, 1)
	// 	);

	// 	$answerArray[] = $mform->createElement('html', '<br />');

	// 	$answer_DB = $DB->get_records('hippotrack_answers', ['questionid' => $this->questionid]);
	// 	$repeatAnswerNumber = count($answer_DB);
	// 	if ($repeatAnswerNumber < 2) {
	// 		$repeatAnswerNumber = 2;
	// 	}
	// 	$repeatableOption = array();


	// 	$this->repeat_elements(
	// 		$answerArray, //  Array of elements or groups of elements that are to be repeated
	// 		$repeatAnswerNumber,// number of times to repeat elements initially
	// 		$repeatableOption,// an options array
	// 		'option_repeats',// name for hidden element storing no of repeats in this form
	// 		'option_add_fields',//name for button to add more fields
	// 		1,//how many fields to add at a time
	// 		get_string('add_answer', 'mod_hippotrack'),//name of button, {no} is replaced by no of blanks that will be added.
	// 		true,//if true, don't call closeHeaderBefore($addfieldsname).
	// 		'delete_answer',// if specified, treats the no-submit button with this name as a "delete element" button in each of the elements.
	// 	);

	// }

}