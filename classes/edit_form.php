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

use moodleform;

global $CFG;
require_once($CFG->libdir . '/formslib.php');

class edit_form extends moodleform
{
	public $cmid;

	function __construct($pageURL, $cmid, $id)
	{

		$this->cmid = $cmid;
		$this->id = $id;
		parent::__construct($pageURL);
	}

	public function definition()
	{
		$mform = $this->_form;
		$cmid = $this->_customdata['cmid'] ?? null;

		// Define difficulty select options
		$difficulty_options = [
			'Fa' => get_string('easy', 'mod_hippotrack'),
			'Di' => get_string('hard', 'mod_hippotrack')
		];

		// Define type select options
		$type_options = [
			'MCQ' => get_string('multiple_choice', 'mod_hippotrack'),
			'TF' => get_string('true_false', 'mod_hippotrack'),
			'SA' => get_string('short_answer', 'mod_hippotrack')
		];

		// Default number of questions
		$default_questions = 2;

		$mform->addElement('html', '<div id="question-container">');

		for ($i = 0; $i < $default_questions; $i++) {
			$mform->addElement('html', '<div class="question-group">');

			// Difficulty Select
			$mform->addElement('select', "difficulty[$i]", get_string('difficulty_type', 'mod_hippotrack', $i), $difficulty_options);

			// Question Type Select
			$mform->addElement('select', "question_type[$i]", get_string('question_type', 'mod_hippotrack', $i), $type_options);

			// Remove Button
			$mform->addElement('button', "remove_question_$i", get_string('remove_question', 'mod_hippotrack'), ['class' => 'remove-question-btn']);

			$mform->addElement('html', '</div>');
		}

		$mform->addElement('html', '</div>');

		// Add "Add More" button
		$mform->addElement('button', 'add_question', get_string('add_question', 'mod_hippotrack'), ['class' => 'add-question-btn']);

		// Add cmid as a hidden field
		$mform->addElement('hidden', 'cmid', $cmid);
		// $mform->setType('cmid', PARAM_INT);

		// Add submit buttons
		$this->add_action_buttons();
	}
}

