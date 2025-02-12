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
 * Contain the form to manage datasets.
 *
 * @package     mod_hippotrack
 * @copyright   2025 Lionel Di Marco <LDiMarco@chu-grenoble.fr>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

 require_once("$CFG->libdir/formslib.php");

class manage_datasets_form extends moodleform {
    public function definition() {
        $mform = $this->_form;

        // Add text fields
        $mform->addElement('text', 'name', 'Nom');
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', 'Nom requis', 'required');

        $mform->addElement('text', 'sigle', 'Sigle');
        $mform->setType('sigle', PARAM_TEXT);
        $mform->addRule('sigle', 'Sigle requis', 'required');

        $mform->addElement('text', 'rotation', 'Rotation');
        $mform->setType('rotation', PARAM_INT);
        $mform->addRule('rotation', 'Rotation requise', 'required');

        $mform->addElement('text', 'inclinaison', 'Inclinaison');
        $mform->setType('inclinaison', PARAM_INT);
        $mform->addRule('inclinaison', 'Inclinaison requise', 'required');

        // Add file pickers
        $mform->addElement('filepicker', 'vue_anterieure', 'Vue Antérieure', null, array('accepted_types' => '*'));
        $mform->addElement('filepicker', 'vue_laterale', 'Vue Latérale', null, array('accepted_types' => '*'));

        // Add hidden field for dataset_id (used for editing)
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        // Add submit and cancel buttons
        $this->add_action_buttons(true, 'Submit', "Enregistrer");
        // $mform->addElement('submit', 'submitbutton', 'Valider');
    }
    
    public function set_data($default_values) {
        parent::set_data($default_values);
    }
}