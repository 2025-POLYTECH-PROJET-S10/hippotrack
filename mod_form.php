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
 * The main hippotrack configuration form.
 *
 * @package     mod_hippotrack
 * @copyright   2025 Lionel Di Marco <LDiMarco@chu-grenoble.fr>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Module instance settings form.
 *
 * @package     mod_hippotrack
 * @copyright   2025 Lionel Di Marco <LDiMarco@chu-grenoble.fr>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_hippotrack_mod_form extends moodleform_mod
{

	/**
	 * Defines forms elements
	 */
	public function definition()
	{

		global $CFG, $DB, $PAGE, $USER, $COURSE;

		$mform = &$this->_form;

		//Add header
		$mform->addElement('header', 'general', "Option du cours:");
		$mform->addElement('text', 'name', 'Name', array('size' => '20'));
		$mform->setType('name', PARAM_TEXT);// $mform -> addElement('course', $COURSE -> id);

		// Adding the standard "Description" field.
		$this->standard_intro_elements('Description');

		// Add standard elements, common to all modules.
		$this->standard_coursemodule_elements();
		// Add standard buttons, common to all modules.
		$this->add_action_buttons();

	}
}
